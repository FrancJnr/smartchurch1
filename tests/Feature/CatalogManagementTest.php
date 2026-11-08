<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\GivingType;
use App\Models\Group;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function memberData(): array
    {
        return ['name' => 'Test Member', 'ekanisa_number' => 'IMANI-123', 'phone' => '0712345678', 'status' => 'active'];
    }

    public function test_dynamic_lists_populate_member_form_and_save_independent_memberships(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $this->post(route('catalogs.store', 'districts'), ['name' => 'North'])->assertSessionHasNoErrors();
        $this->post(route('catalogs.store', 'groups'), ['name' => 'Youth'])->assertSessionHasNoErrors();
        $district = District::sole();
        $group = Group::sole();
        $this->get(route('members.create'))->assertOk()->assertSee('North')->assertSee('Youth');

        $this->post(route('members.store'), [...$this->memberData(), 'district_id' => $district->id, 'group_id' => $group->id])->assertRedirect(route('members.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('members', ['ekanisa_number' => 'IMANI-123', 'district_id' => $district->id, 'group_id' => $group->id]);
        $this->get(route('members.index'))->assertOk()->assertSee('North')->assertSee('Youth');
    }

    public function test_archived_memberships_can_be_kept_but_cannot_be_assigned_to_new_members(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $district = District::factory()->create(['name' => 'Old district', 'is_active' => false]);
        $group = Group::factory()->create(['name' => 'Old group', 'is_active' => false]);
        $member = Member::create([...$this->memberData(), 'district_id' => $district->id, 'group_id' => $group->id]);
        $this->get(route('members.create'))->assertDontSee('Old district')->assertDontSee('Old group');
        $this->get(route('members.edit', $member))->assertOk()->assertSee('Old district (archived)')->assertSee('Old group (archived)');
        $this->put(route('members.update', $member), [...$this->memberData(), 'district_id' => $district->id, 'group_id' => $group->id])->assertSessionHasNoErrors();

        $this->post(route('members.store'), [...$this->memberData(), 'ekanisa_number' => 'NEW', 'district_id' => $district->id, 'group_id' => $group->id])->assertSessionHasErrors(['district_id', 'group_id']);

        $this->assertDatabaseCount('members', 1);
        $this->put(route('members.update', $member), [...$this->memberData(), 'district_id' => null, 'group_id' => null])->assertSessionHasNoErrors();
        $this->assertNull($member->fresh()->district_id);
        $this->assertNull($member->fresh()->group_id);
    }

    public function test_unknown_memberships_are_rejected(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $this->post(route('members.store'), [...$this->memberData(), 'district_id' => 999, 'group_id' => 999])->assertSessionHasErrors(['district_id', 'group_id']);
        $this->assertDatabaseCount('members', 0);
    }

    public function test_giving_types_can_be_added_renamed_archived_and_reactivated(): void
    {
        $this->freezeTime();
        $this->actingAs(User::factory()->administrator()->create());
        Queue::fake();
        $this->post(route('catalogs.store', 'giving-types'), ['name' => 'Building fund'])->assertSessionHasNoErrors();
        $type = GivingType::sole();
        $this->get(route('givings.create'))->assertOk()->assertSee('Building fund');
        $this->put(route('catalogs.update', ['giving-types', $type->id]), ['name' => 'Church building', 'is_active' => 0])->assertSessionHasNoErrors();
        $this->get(route('givings.create'))->assertDontSee('Church building')->assertSee('No active giving types');
        Member::create($this->memberData());
        $data = ['ekanisa_number' => 'IMANI-123', 'items' => [['giving_type_id' => $type->id, 'amount' => '100.25']], 'payment_method' => 'Cash', 'giving_date' => today()->toDateString()];
        $this->post(route('givings.store'), $data)->assertSessionHasErrors('items.0.giving_type_id');
        $this->assertDatabaseCount('givings', 0);
        Queue::assertNothingPushed();
        $this->put(route('catalogs.update', ['giving-types', $type->id]), ['name' => 'Church building', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->post(route('givings.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('givings', 1);
        $this->put(route('catalogs.update', ['giving-types', $type->id]), ['name' => 'Church building', 'is_active' => 0])->assertSessionHasNoErrors();
        $this->get(route('reports.index'))->assertOk()->assertSee('Church building')->assertSee('100.25');
    }

    public function test_duplicate_names_are_rejected_and_labels_are_escaped(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $district = District::factory()->create(['name' => '<script>alert(1)</script>']);
        $this->post(route('catalogs.store', 'districts'), ['name' => $district->name])->assertSessionHasErrors('name');
        $this->get(route('catalogs.index', 'districts'))->assertOk()->assertSee($district->name)->assertDontSee($district->name, false);
        $this->get(route('members.create'))->assertOk()->assertSee($district->name)->assertDontSee($district->name, false);
        $this->assertDatabaseCount('districts', 1);
    }
}
