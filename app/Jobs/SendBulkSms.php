<?php

namespace App\Jobs;

use App\Models\SmsRecipient;
use App\Services\SmsGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendBulkSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    public function __construct(public int $recipientId) {}

    public function handle(SmsGateway $gateway): void
    {
        $recipient = SmsRecipient::with(['campaign.creator.role', 'member'])->findOrFail($this->recipientId);
        $campaign = $recipient->campaign;
        if ($campaign->status !== 'queued' || $recipient->status !== 'pending') {
            return;
        }
        if (! SmsRecipient::whereKey($recipient->id)->where('status', 'pending')->update(['status' => 'processing'])) {
            return;
        }
        try {
            $member = $recipient->member;
            if (! $campaign->creator->hasPermission('communications.send')) {
                $recipient->update(['status' => 'skipped', 'error_message' => 'The author no longer has permission to send SMS.']);

                return;
            }
            if (! $member || $member->status !== 'active' ||
                ($campaign->audience === 'group' && $member->group_id !== $campaign->group_id) ||
                ($campaign->audience === 'district' && $member->district_id !== $campaign->district_id)) {
                $recipient->update(['status' => 'skipped', 'error_message' => 'Member is no longer active in the selected audience.']);

                return;
            }
            if ($gateway->normalizeKenyanPhone($member->phone) !== $recipient->phone) {
                $recipient->update(['status' => 'skipped', 'error_message' => 'Member phone changed since the preview.']);

                return;
            }
            $reference = $gateway->send($recipient->phone, $campaign->message);
            $recipient->update(['status' => 'accepted', 'provider_reference' => $reference, 'sent_at' => now(), 'error_message' => null]);
        } catch (Throwable $exception) {
            $explicitFailure = str_contains($exception->getMessage(), 'Advanta rejected SMS')
                || str_contains($exception->getMessage(), 'Invalid Kenyan mobile number')
                || str_contains($exception->getMessage(), 'credentials or sender ID are missing');
            $recipient->update([
                'status' => $explicitFailure ? 'failed' : 'needs_review',
                'error_message' => $explicitFailure ? mb_substr($exception->getMessage(), 0, 2000) : 'Provider outcome is uncertain. Check Advanta before sending again.',
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        SmsRecipient::whereKey($this->recipientId)->where('status', 'pending')->update([
            'status' => 'failed',
            'error_message' => 'The queue could not start this send. No SMS was sent by this job.',
        ]);
        SmsRecipient::whereKey($this->recipientId)->where('status', 'processing')->update([
            'status' => 'needs_review',
            'error_message' => 'Sending was interrupted. Check Advanta before sending again.',
        ]);
    }
}
