<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Advanta QuickSMS adapter. Returns the provider's acceptance message ID. */
class SmsGateway
{
    public function send(string $phone, string $message): string
    {
        $key = config('advanta.api_key');
        $partner = config('advanta.partner_id');
        $sender = config('advanta.sender_id');
        $url = config('advanta.endpoint');
        if (! $key || ! $partner || ! $sender || ! $url) {
            throw new RuntimeException('Advanta SMS credentials or sender ID are missing.');
        }

        $mobile = $this->normalizeKenyanPhone($phone);
        $response = Http::acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(20)
            // Do not automatically retry sends: timeout could occur after acceptance.
            ->post($url, [
                'apikey' => $key,
                'partnerID' => (string) $partner,
                'shortcode' => $sender,
                'mobile' => $mobile,
                'message' => $message,
            ]);
        $response->throw();

        $item = $response->json('responses.0');
        if (! is_array($item)) {
            throw new RuntimeException('Advanta returned an unrecognized response. Reconcile before resending.');
        }

        // Advanta documentation spells this field "respose-code".
        $code = $item['respose-code'] ?? $item['response-code'] ?? null;
        if ((string) $code !== '200') {
            $description = (string) ($item['response-description'] ?? 'Unspecified gateway rejection');
            throw new RuntimeException("Advanta rejected SMS (code {$code}): {$description}");
        }

        if (empty($item['messageid'])) {
            throw new RuntimeException('Advanta accepted SMS but returned no message ID. Reconcile before resending.');
        }

        return (string) $item['messageid'];
    }

    public function normalizeKenyanPhone(string $phone): string
    {
        $digits = preg_replace('/[\s()+-]/', '', trim($phone));
        if (preg_match('/^0(7|1)\d{8}$/', $digits)) {
            return '254'.substr($digits, 1);
        }
        if (preg_match('/^254(7|1)\d{8}$/', $digits)) {
            return $digits;
        }
        if (preg_match('/^(7|1)\d{8}$/', $digits)) {
            return '254'.$digits;
        }
        throw new RuntimeException('Invalid Kenyan mobile number.');
    }
}
