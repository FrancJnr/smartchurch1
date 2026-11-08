<?php

namespace Tests\Feature;

use App\Jobs\SendBulkSms;
use App\Models\Group;
use App\Models\Member;
use App\Models\SmsCampaign;
use App\Models\SmsRecipient;
use App\Models\User;
use App\Services\SmsGateway;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SendBulkSmsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function queuedRecipient(): SmsRecipient
    {
        $this->freezeTime();
        config()->set(['advanta.api_key' => 'test-key', 'advanta.partner_id' => '123', 'advanta.sender_id' => 'TEST']);
        Http::preventStrayRequests();
        $author = User::factory()->administrator()->create();
        $group = Group::factory()->create();
        $member = Member::create(['name' => 'Test Member', 'ekanisa_number' => 'E-1', 'phone' => '0712345678', 'status' => 'active', 'group_id' => $group->id]);
        $campaign = SmsCampaign::factory()->create(['created_by' => $author->id, 'audience' => 'group', 'group_id' => $group->id, 'message' => 'The meeting starts at 2pm.', 'status' => 'queued']);

        return SmsRecipient::factory()->create(['sms_campaign_id' => $campaign->id, 'member_id' => $member->id, 'member_name' => $member->name, 'phone' => '254712345678']);
    }

    public function test_job_sends_the_exact_message_once_and_records_provider_acceptance(): void
    {
        $recipient = $this->queuedRecipient();
        Http::fake([config('advanta.endpoint') => Http::response(['responses' => [['respose-code' => 200, 'messageid' => 'ref-123']]])]);
        $job = new SendBulkSms($recipient->id);

        $job->handle(app(SmsGateway::class));
        $job->handle(app(SmsGateway::class));

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['mobile'] === '254712345678' && $request['message'] === 'The meeting starts at 2pm.' && $request['shortcode'] === 'TEST');
        $this->assertDatabaseHas('sms_recipients', ['id' => $recipient->id, 'status' => 'accepted', 'provider_reference' => 'ref-123', 'error_message' => null]);
        $this->assertNotNull($recipient->fresh()->sent_at);
    }

    #[TestWith(['inactive'])]
    #[TestWith(['moved'])]
    #[TestWith(['phone-changed'])]
    #[TestWith(['sender-disabled'])]
    public function test_changed_recipient_or_sender_is_skipped_without_http(string $change): void
    {
        $recipient = $this->queuedRecipient();
        Http::fake();
        match ($change) {
            'inactive' => $recipient->member->update(['status' => 'inactive']),
            'moved' => $recipient->member->update(['group_id' => null]),
            'phone-changed' => $recipient->member->update(['phone' => '0712345679']),
            'sender-disabled' => $recipient->campaign->creator->update(['is_active' => false]),
        };

        (new SendBulkSms($recipient->id))->handle(app(SmsGateway::class));

        $this->assertSame('skipped', $recipient->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_provider_rejection_is_failed_and_never_automatically_resent(): void
    {
        $recipient = $this->queuedRecipient();
        Http::fake([config('advanta.endpoint') => Http::response(['responses' => [['respose-code' => 1004, 'response-description' => 'Low bulk credits']]])]);
        $job = new SendBulkSms($recipient->id);

        $job->handle(app(SmsGateway::class));
        $job->handle(app(SmsGateway::class));

        $this->assertSame('failed', $recipient->fresh()->status);
        $this->assertStringContainsString('Low bulk credits', $recipient->fresh()->error_message);
        Http::assertSentCount(1);
    }

    public function test_uncertain_response_needs_review_and_is_not_retried(): void
    {
        $recipient = $this->queuedRecipient();
        Http::fake([config('advanta.endpoint') => Http::response(['unknown' => true])]);
        $job = new SendBulkSms($recipient->id);

        $job->handle(app(SmsGateway::class));
        $job->handle(app(SmsGateway::class));

        $this->assertSame('needs_review', $recipient->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_network_failure_is_uncertain(): void
    {
        $recipient = $this->queuedRecipient();
        Http::fake([config('advanta.endpoint') => Http::failedConnection()]);

        (new SendBulkSms($recipient->id))->handle(app(SmsGateway::class));

        $this->assertSame('needs_review', $recipient->fresh()->status);
        $this->assertNull($recipient->fresh()->provider_reference);
    }

    public function test_failure_before_processing_is_recorded_without_claiming_delivery(): void
    {
        $recipient = $this->queuedRecipient();
        Http::fake();

        (new SendBulkSms($recipient->id))->failed(new \RuntimeException('Worker failed before handling'));

        $this->assertSame('failed', $recipient->fresh()->status);
        $this->assertNull($recipient->fresh()->provider_reference);
        Http::assertNothingSent();
    }

    public function test_drafts_and_already_processing_recipients_cannot_be_sent(): void
    {
        $recipient = $this->queuedRecipient();
        Http::fake();
        $recipient->campaign->update(['status' => 'draft']);
        $job = new SendBulkSms($recipient->id);
        $job->handle(app(SmsGateway::class));
        $this->assertSame('pending', $recipient->fresh()->status);
        $recipient->campaign->update(['status' => 'queued']);
        $recipient->update(['status' => 'processing']);
        $job->handle(app(SmsGateway::class));
        Http::assertNothingSent();
        $job->failed(new \RuntimeException('timeout'));
        $this->assertSame('needs_review', $recipient->fresh()->status);
    }
}
