<?php

namespace Tests\Unit;

use App\Services\SmsGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdvantaSmsGatewayTest extends TestCase
{
    public function test_sends_to_advanta_and_returns_message_id(): void
    {
        config()->set('advanta.api_key', 'test-key');
        config()->set('advanta.partner_id', '123');
        config()->set('advanta.sender_id', 'CHURCH');
        config()->set('advanta.endpoint', 'https://quicksms.advantasms.com/api/services/sendsms/');
        Http::fake(['quicksms.advantasms.com/*' => Http::response([
            'responses' => [[
                'respose-code' => 200,
                'response-description' => 'Success',
                'messageid' => 8290842,
            ]],
        ], 200)]);

        $id = app(SmsGateway::class)->send('0712345678', 'Test acknowledgement');
        $this->assertSame('8290842', $id);
        Http::assertSent(fn ($request) =>
            $request['mobile'] === '254712345678'
            && $request['partnerID'] === '123'
            && $request['shortcode'] === 'CHURCH'
            && $request['message'] === 'Test acknowledgement'
        );
    }

    public function test_provider_rejection_is_not_success(): void
    {
        config()->set('advanta.api_key', 'test-key');
        config()->set('advanta.partner_id', '123');
        config()->set('advanta.sender_id', 'CHURCH');
        config()->set('advanta.endpoint', 'https://quicksms.advantasms.com/api/services/sendsms/');
        Http::fake(['quicksms.advantasms.com/*' => Http::response([
            'responses' => [['respose-code' => 1004, 'response-description' => 'Low bulk credits']],
        ], 200)]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Low bulk credits');
        app(SmsGateway::class)->send('254712345678', 'Test');
    }
}
