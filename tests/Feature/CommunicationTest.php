<?php

namespace Tests\Feature;

use App\Jobs\SendBulkSms;
use App\Models\District;
use App\Models\Group;
use App\Models\Member;
use App\Models\Role;
use App\Models\SmsCampaign;
use App\Models\SmsRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CommunicationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function audienceMembers(): array
    {
        $north = District::factory()->create(['name' => 'North']);
        $south = District::factory()->create(['name' => 'South']);
        $youth = Group::factory()->create(['name' => 'Youth']);
        $women = Group::factory()->create(['name' => 'Women']);
        foreach ([
            ['0712345678', $north->id, $youth->id, 'active'],
            ['+254712345678', $north->id, $youth->id, 'active'],
            ['0712345679', $south->id, $women->id, 'active'],
            ['0712345680', $north->id, $youth->id, 'inactive'],
            ['invalid-phone', $north->id, $youth->id, 'active'],
            ['0712345681', null, null, 'active'],
            ['0712345682', $north->id, $women->id, 'active'],
        ] as $index => [$phone, $district, $group, $status]) {
            Member::create(['name' => 'Member '.$index, 'ekanisa_number' => 'E-'.$index, 'phone' => $phone, 'district_id' => $district, 'group_id' => $group, 'status' => $status]);
        }

        return [$north, $youth];
    }

    #[TestWith(['all', 4, 6])]
    #[TestWith(['group', 1, 3])]
    #[TestWith(['district', 2, 4])]
    public function test_preview_snapshots_only_the_selected_active_audience_without_sending(string $audience, int $unique, int $matched): void
    {
        [$district, $group] = $this->audienceMembers();
        $this->actingAs(User::factory()->administrator()->create());
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake();

        $this->post(route('communications.store'), ['audience' => $audience, 'group_id' => $group->id, 'district_id' => $district->id, 'message' => 'Sunday service begins at 9am.'])
            ->assertSessionHasNoErrors()->assertRedirect(route('communications.show', 1));

        $campaign = SmsCampaign::sole();
        $this->assertSame('draft', $campaign->status);
        $this->assertSame($unique, $campaign->recipients()->count());
        $this->assertSame($matched, $campaign->matched_members);
        $this->assertSame(1, $campaign->duplicate_numbers);
        $this->assertSame(1, $campaign->invalid_numbers);
        $this->assertDatabaseMissing('sms_recipients', ['phone' => '254712345680']);
        if ($audience === 'group') {
            $this->assertDatabaseMissing('sms_recipients', ['phone' => '254712345682']);
        }
        if ($audience !== 'all') {
            $this->assertDatabaseMissing('sms_recipients', ['phone' => '254712345679']);
            $this->assertDatabaseMissing('sms_recipients', ['phone' => '254712345681']);
        }
        Queue::assertNothingPushed();
        Http::assertNothingSent();
        $this->get(route('communications.show', $campaign))->assertOk()->assertSee('No messages have been sent.')->assertSee('Sunday service begins at 9am.');
        $this->get(route('communications.index'))->assertOk()->assertSee('Draft');
    }

    public function test_send_is_idempotent_and_uses_the_preview_message_and_recipients(): void
    {
        $author = User::factory()->administrator()->create();
        $campaign = SmsCampaign::factory()->create(['created_by' => $author->id, 'message' => 'Original preview']);
        $recipients = SmsRecipient::factory()->count(2)->create(['sms_campaign_id' => $campaign->id]);
        $this->actingAs($author);
        config()->set(['advanta.api_key' => 'test', 'advanta.partner_id' => '123', 'advanta.sender_id' => 'TEST']);
        Queue::fake();

        $this->post(route('communications.send', $campaign), ['message' => 'Changed text', 'audience' => 'all'])->assertSessionHasNoErrors()->assertRedirect(route('communications.show', $campaign));
        $this->post(route('communications.send', $campaign))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sms_campaigns', ['id' => $campaign->id, 'status' => 'queued', 'message' => 'Original preview']);
        $this->assertNotNull($campaign->fresh()->queued_at);
        Queue::assertPushed(SendBulkSms::class, 2);
        foreach ($recipients as $recipient) {
            Queue::assertPushed(SendBulkSms::class, fn ($job) => $job->recipientId === $recipient->id);
        }
        $this->get(route('communications.show', $campaign))->assertOk()->assertSee('Queued')->assertDontSee('id="send-bulk-form"', false);
    }

    public function test_no_recipients_invalid_selection_or_message_cannot_create_a_campaign(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        Queue::fake();
        $archived = Group::factory()->create(['is_active' => false]);
        $this->post(route('communications.store'), ['audience' => 'all', 'message' => 'Hello'])->assertSessionHasErrors('audience');
        $this->post(route('communications.store'), ['audience' => 'group', 'group_id' => $archived->id, 'message' => 'Hello'])->assertSessionHasErrors('group_id');
        $this->post(route('communications.store'), ['audience' => 'district', 'district_id' => 999, 'message' => 'Hello'])->assertSessionHasErrors('district_id');
        $this->post(route('communications.store'), ['audience' => 'all', 'message' => str_repeat('x', 1601)])->assertSessionHasErrors('message');
        $this->post(route('communications.store'), ['audience' => 'all', 'message' => '   '])->assertSessionHasErrors('message');
        $this->assertDatabaseCount('sms_campaigns', 0);
        Queue::assertNothingPushed();
    }

    public function test_permissions_are_enforced_and_only_the_author_can_send(): void
    {
        $author = User::factory()->administrator()->create();
        $campaign = SmsCampaign::factory()->create(['created_by' => $author->id]);
        $otherAdmin = User::factory()->administrator()->create();
        Queue::fake();
        $this->actingAs($otherAdmin)->post(route('communications.send', $campaign))->assertForbidden();
        $readerRole = Role::factory()->create(['permissions' => ['communications.view']]);
        $reader = User::factory()->create(['role_id' => $readerRole->id]);
        $this->actingAs($reader)->get(route('communications.index'))->assertOk();
        $this->get(route('communications.show', $campaign))->assertOk();
        $this->get(route('communications.create'))->assertForbidden();
        $this->post(route('communications.store'), [])->assertForbidden();
        $this->post(route('communications.send', $campaign))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('communications.index'))->assertForbidden();
        $this->get(route('communications.show', $campaign))->assertForbidden();
        Queue::assertNothingPushed();
        $this->assertSame('draft', $campaign->fresh()->status);
    }

    public function test_missing_credentials_do_not_queue_the_campaign(): void
    {
        $author = User::factory()->administrator()->create();
        $campaign = SmsCampaign::factory()->create(['created_by' => $author->id]);
        config()->set('advanta.api_key', null);
        Queue::fake();
        $this->actingAs($author)->post(route('communications.send', $campaign))->assertSessionHasErrors('message');
        $this->assertSame('draft', $campaign->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_compose_page_uses_dynamic_lists_and_preview_escapes_message_html(): void
    {
        $author = User::factory()->administrator()->create();
        Group::factory()->create(['name' => 'Youth']);
        District::factory()->create(['name' => 'North']);
        Group::factory()->create(['name' => 'Archived group', 'is_active' => false]);
        $this->actingAs($author)->get(route('communications.create'))->assertOk()->assertSee('Youth')->assertSee('North')->assertDontSee('Archived group');
        $campaign = SmsCampaign::factory()->create(['created_by' => $author->id, 'message' => '<script>alert(1)</script>']);
        $this->get(route('communications.show', $campaign))->assertOk()->assertSee($campaign->message)->assertDontSee($campaign->message, false);
    }
}
