<?php

namespace App\Jobs;

use App\Models\SmsLog;
use App\Services\SmsGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendGivingSms implements ShouldQueue
{
    use Queueable;

    // An ambiguous timeout may mean the SMS was accepted; never blindly retry.
    public int $tries = 1;

    public function __construct(public int $logId) {}

    public function handle(SmsGateway $gateway): void
    {
        $log = SmsLog::findOrFail($this->logId);
        if ($log->status !== 'pending') {
            return;
        }

        $log->update(['status' => 'processing']);

        try {
            $messageId = $gateway->send($log->phone, $log->message);
            $log->update([
                'status' => 'accepted', // Gateway accepted, not handset-delivered.
                'provider_reference' => $messageId,
                'sent_at' => now(),
                'error_message' => null,
            ]);
        } catch (Throwable $e) {
            $isExplicitRejection = str_contains($e->getMessage(), 'Advanta rejected SMS')
                || str_contains($e->getMessage(), 'Invalid Kenyan mobile number')
                || str_contains($e->getMessage(), 'credentials or sender ID are missing');
            $log->update([
                'status' => $isExplicitRejection ? 'failed' : 'needs_review',
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
            ]);
            Log::warning('Advanta giving SMS failed or needs reconciliation', [
                'sms_log_id' => $log->id,
                'status' => $isExplicitRejection ? 'failed' : 'needs_review',
                'error_type' => get_class($e),
            ]);
            // Do not throw and retry automatically: provider could have accepted.
        }
    }
}
