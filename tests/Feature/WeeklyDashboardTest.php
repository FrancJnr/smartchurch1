<?php

namespace Tests\Feature;

use App\Models\Giving;
use App\Models\GivingType;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class WeeklyDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function recordGiving(User $user, Member $member, GivingType $type, string $date, string $amount): void
    {
        Giving::create([
            'member_id' => $member->id,
            'giving_type_id' => $type->id,
            'amount' => $amount,
            'payment_method' => 'Cash',
            'transaction_reference' => 'GIV-'.Str::ulid(),
            'giving_date' => $date,
            'recorded_by' => $user->id,
        ]);
    }

    public function test_dashboard_groups_by_giving_week_across_year_boundaries(): void
    {
        $this->travelTo(Carbon::parse('2027-01-04 12:00:00', 'Africa/Nairobi'));
        $user = User::factory()->administrator()->create();
        $member = Member::create(['ekanisa_number' => 'IMANI-1', 'name' => 'Member One', 'phone' => '0712345678', 'status' => 'active']);
        $other = Member::create(['ekanisa_number' => 'IMANI-2', 'name' => 'Member Two', 'phone' => '0712345679', 'status' => 'active']);
        $tithe = GivingType::create(['name' => 'Tithe']);
        $offering = GivingType::create(['name' => 'Offering']);
        $this->recordGiving($user, $member, $tithe, '2026-12-28', '1000.10');
        $this->recordGiving($user, $member, $offering, '2027-01-03', '250.25');
        $this->recordGiving($user, $other, $offering, '2026-12-31', '49.65');
        $this->recordGiving($user, $member, $tithe, '2027-01-04', '100.00');
        $this->recordGiving($user, $member, $tithe, '2026-12-27', '60.00');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertSee('28 Dec 2026 – 03 Jan 2027')->assertSee('KES 1,300.00')
            ->assertSee(route('reports.index', ['from' => '2026-12-28', 'to' => '2027-01-03']));
        $weeks = $response->viewData('weeks');
        $this->assertSame(['2027-01-04', '2026-12-28', '2026-12-21'], $weeks->pluck('week_start')->all());
        $previousWeek = $weeks->getCollection()[1];
        $this->assertSame(2, (int) $previousWeek->contributors);
        $this->assertSame(3, (int) $previousWeek->entries);
        $this->assertSame('100.00', number_format($response->viewData('weekly'), 2, '.', ''));
        $breakdown = $response->viewData('typesByWeek')->get('2026-12-28')->keyBy('giving_type_id');
        $this->assertSame('1000.10', number_format($breakdown[$tithe->id]->total, 2, '.', ''));
        $this->assertSame('299.90', number_format($breakdown[$offering->id]->total, 2, '.', ''));
    }

    public function test_empty_dashboard_offers_record_giving(): void
    {
        $this->actingAs(User::factory()->administrator()->create())->get(route('dashboard'))
            ->assertOk()->assertSee('No giving recorded yet')->assertSee('Record Giving');
    }

    public function test_weekly_pagination_keeps_complete_weeks_together(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 12:00:00', 'Africa/Nairobi'));
        $user = User::factory()->administrator()->create();
        $member = Member::create(['ekanisa_number' => 'IMANI-1', 'name' => 'Member One', 'phone' => '0712345678', 'status' => 'active']);
        $type = GivingType::create(['name' => 'Tithe']);
        for ($week = 0; $week < 13; $week++) {
            $this->recordGiving($user, $member, $type, today()->subWeeks($week)->toDateString(), '10.00');
        }
        $this->recordGiving($user, $member, $type, today()->subWeeks(12)->subDay()->toDateString(), '15.00');

        $response = $this->actingAs($user)->get(route('dashboard', ['page' => 2]));

        $response->assertOk()->assertSee('KES 25.00');
        $this->assertSame(13, $response->viewData('weeks')->total());
        $this->assertCount(1, $response->viewData('weeks'));
        $this->assertSame(2, (int) $response->viewData('weeks')->first()->entries);
    }
}
