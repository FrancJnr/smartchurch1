<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Giving;
use App\Models\GivingType;
use App\Models\Group;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class GivingAnalyticsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_charts_reconcile_groups_districts_and_zero_weeks_using_giving_dates(): void
    {
        $this->travelTo(Carbon::parse('2026-11-08 12:00:00', 'Africa/Nairobi'));
        $user = User::factory()->administrator()->create();
        $district = District::factory()->create(['name' => 'North']);
        $group = Group::factory()->create(['name' => 'Youth']);
        $member = Member::create(['name' => 'Test Member', 'phone' => '0712345678', 'ekanisa_number' => 'A', 'status' => 'active', 'district_id' => $district->id, 'group_id' => $group->id]);
        $unassigned = Member::create(['name' => 'Unassigned Member', 'phone' => '0712345679', 'ekanisa_number' => 'B', 'status' => 'active']);
        $type = GivingType::create(['name' => 'Tithe']);
        foreach ([[$member, '2026-10-12', '100.10'], [$member, '2026-10-18', '200.20'], [$unassigned, '2026-10-26', '50.05'], [$member, '2026-10-11', '999.00'], [$member, '2026-11-01', '888.00']] as [$giver, $date, $amount]) {
            Giving::create(['member_id' => $giver->id, 'giving_type_id' => $type->id, 'amount' => $amount, 'payment_method' => 'Cash', 'giving_date' => $date, 'recorded_by' => $user->id, 'transaction_reference' => 'GIV-'.Str::ulid()]);
        }

        $response = $this->actingAs($user)->get(route('dashboard', ['chart_from' => '2026-10-12', 'chart_to' => '2026-10-31']));

        $response->assertOk()->assertSee('Giving per district')->assertSee('Giving per group')->assertSee('Period total: KES 350.35');
        $charts = $response->viewData('charts');
        $this->assertSame([300.3, 0, 50.05], $charts['trend']->pluck('total')->all());
        $this->assertSame(['2026-10-12', '2026-10-19', '2026-10-26'], $charts['trend']->pluck('start')->all());
        $this->assertSame(['North', 'Unassigned'], $charts['districts']->pluck('label')->all());
        $this->assertSame(['Youth', 'Unassigned'], $charts['groups']->pluck('label')->all());
        $this->assertSame([300.3, 50.05], $charts['districts']->pluck('total')->all());
        $this->assertSame([300.3, 50.05], $charts['groups']->pluck('total')->all());
    }

    public function test_empty_charts_show_zero_weeks_and_no_giving_message(): void
    {
        $this->freezeTime();
        $response = $this->actingAs(User::factory()->administrator()->create())->get(route('dashboard'));
        $response->assertOk()->assertSee('No giving in this period.');
        $this->assertCount(12, $response->viewData('charts')['trend']);
        $this->assertSame(0, $response->viewData('charts')['total']);
    }

    public function test_invalid_chart_ranges_are_rejected(): void
    {
        $this->travelTo(Carbon::parse('2026-11-08 12:00:00', 'Africa/Nairobi'));
        $this->actingAs(User::factory()->administrator()->create());
        $this->get(route('dashboard', ['chart_from' => '2026-10-31', 'chart_to' => '2026-10-01']))->assertSessionHasErrors('chart_to');
        $this->get(route('dashboard', ['chart_from' => '2020-01-01', 'chart_to' => '2026-10-01']))->assertSessionHasErrors('chart_to');
        $this->get(route('dashboard', ['chart_to' => '2030-01-01']))->assertSessionHasErrors('chart_to');
    }
}
