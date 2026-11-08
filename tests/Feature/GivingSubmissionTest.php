<?php

namespace Tests\Feature;

use App\Jobs\SendGivingSms;
use App\Models\Giving;
use App\Models\GivingType;
use App\Models\Member;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\SmsGateway;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class GivingSubmissionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function givingData(): array
    {
        $this->freezeTime();
        $this->actingAs(User::factory()->administrator()->create());
        Member::create(['ekanisa_number' => 'IMANI-1', 'name' => 'Test Member', 'phone' => '0712345678', 'status' => 'active']);
        $tithe = GivingType::create(['name' => 'Tithe']);
        $offering = GivingType::create(['name' => 'Offering']);

        return [
            'ekanisa_number' => 'IMANI-1',
            'items' => [
                ['giving_type_id' => $tithe->id, 'amount' => '1000.10'],
                ['giving_type_id' => $offering->id, 'amount' => '250.25'],
            ],
            'payment_method' => 'MPESA',
            'giving_date' => today()->toDateString(),
            'external_reference' => 'PAYMENT-123',
        ];
    }

    public function test_member_picker_lists_only_active_members_and_restores_selection(): void
    {
        $data = $this->givingData();
        Member::create(['ekanisa_number' => 'INACTIVE-1', 'name' => 'Inactive Person', 'phone' => '0712345600', 'status' => 'inactive']);

        $this->withSession(['_old_input' => ['ekanisa_number' => $data['ekanisa_number']]])
            ->get(route('givings.create'))->assertOk()
            ->assertSee('Test Member — IMANI-1 — 0712345678')
            ->assertSee('value="IMANI-1" selected', false)
            ->assertDontSee('INACTIVE-1')->assertDontSee('Inactive Person');
    }

    public function test_inactive_member_cannot_be_submitted_from_a_stale_form(): void
    {
        $data = $this->givingData();
        Member::where('ekanisa_number', $data['ekanisa_number'])->update(['status' => 'inactive']);
        Queue::fake();

        $this->post(route('givings.store'), $data)->assertSessionHasErrors('ekanisa_number');

        $this->assertDatabaseCount('givings', 0);
        Queue::assertNothingPushed();
    }

    public function test_multiple_types_share_one_sms_with_an_exact_total(): void
    {
        $data = $this->givingData();
        Queue::fake();

        $this->post(route('givings.store'), $data)->assertRedirect(route('givings.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('givings', 2);
        $this->assertDatabaseCount('sms_logs', 1);
        $log = SmsLog::sole();
        $first = Giving::orderBy('id')->firstOrFail();
        $this->assertSame('Dear Test Member, thank you for your giving. Tithe: KES 1,000.10; Offering: KES 250.25. Total: KES 1,250.35. Receipt: '.$first->transaction_reference.'. God bless you.', $log->message);
        foreach ($data['items'] as $item) {
            $this->assertDatabaseHas('givings', ['giving_type_id' => $item['giving_type_id'], 'amount' => $item['amount'], 'sms_log_id' => $log->id]);
        }
        $this->assertSame(1, Giving::where('external_reference', 'PAYMENT-123')->count());
        $this->assertSame(2, Giving::where('sms_log_id', $log->id)->count());
        Queue::assertPushed(SendGivingSms::class, fn ($job) => $job->logId === $log->id);
        Queue::assertPushed(SendGivingSms::class, 1);
        $this->get(route('givings.index'))->assertOk()->assertSee('pending');
        $this->get(route('reports.index'))->assertOk()->assertSee('1,250.35');
    }

    public function test_single_type_still_saves_and_includes_total(): void
    {
        $data = $this->givingData();
        $data['items'] = [$data['items'][0]];
        Queue::fake();

        $this->post(route('givings.store'), $data)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('givings', 1);
        $this->assertStringContainsString('Tithe: KES 1,000.10. Total: KES 1,000.10.', SmsLog::sole()->message);
        Queue::assertPushed(SendGivingSms::class, 1);
    }

    #[TestWith(['0'])]
    #[TestWith(['-1'])]
    #[TestWith(['1.001'])]
    #[TestWith(['not-money'])]
    #[TestWith(['1000000000000'])]
    public function test_invalid_line_saves_nothing(string $amount): void
    {
        $data = $this->givingData();
        $data['items'][1]['amount'] = $amount;
        Queue::fake();

        $this->post(route('givings.store'), $data)->assertSessionHasErrors('items.1.amount');

        $this->assertDatabaseCount('givings', 0);
        $this->assertDatabaseCount('sms_logs', 0);
        Queue::assertNothingPushed();
    }

    public function test_duplicate_types_are_rejected_and_rows_are_preserved(): void
    {
        $data = $this->givingData();
        $data['items'][1]['giving_type_id'] = $data['items'][0]['giving_type_id'];
        Queue::fake();

        $this->from(route('givings.create'))->post(route('givings.store'), $data)
            ->assertRedirect(route('givings.create'))->assertSessionHasErrors(['items.1.giving_type_id' => 'Select each giving type only once.']);

        $this->get(route('givings.create'))->assertOk()
            ->assertSee('items[1][amount]', false)->assertSee('250.25');
        $this->assertDatabaseCount('givings', 0);
        Queue::assertNothingPushed();
    }

    public function test_empty_items_and_unknown_types_are_rejected(): void
    {
        $data = $this->givingData();
        Queue::fake();
        $this->post(route('givings.store'), array_replace($data, ['items' => []]))->assertSessionHasErrors('items');
        $data['items'][1]['giving_type_id'] = 999999;
        $this->post(route('givings.store'), $data)->assertSessionHasErrors('items.1.giving_type_id');
        $this->assertDatabaseCount('givings', 0);
        Queue::assertNothingPushed();
    }

    public function test_external_reference_cannot_be_reused_for_another_submission(): void
    {
        $data = $this->givingData();
        Queue::fake();
        $this->post(route('givings.store'), $data)->assertSessionHasNoErrors();

        $this->post(route('givings.store'), $data)->assertSessionHasErrors('external_reference');

        $this->assertDatabaseCount('givings', 2);
        $this->assertDatabaseCount('sms_logs', 1);
        Queue::assertPushed(SendGivingSms::class, 1);
    }

    public function test_gateway_receives_one_combined_message_and_all_lines_share_status(): void
    {
        $data = $this->givingData();
        Queue::fake();
        Http::preventStrayRequests();
        config()->set(['advanta.api_key' => 'test', 'advanta.partner_id' => 'test', 'advanta.sender_id' => 'PCEA_IMANI']);
        Http::fake([config('advanta.endpoint') => Http::response(['responses' => [['respose-code' => 200, 'messageid' => 'test-message']]])]);
        $this->post(route('givings.store'), $data)->assertSessionHasNoErrors();
        $log = SmsLog::sole();

        (new SendGivingSms($log->id))->handle(app(SmsGateway::class));

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['message'] === $log->message && $request['mobile'] === '254712345678');
        foreach (Giving::with('smsLog')->get() as $giving) {
            $this->assertSame('accepted', $giving->smsLog->status);
        }
    }
}
