<?php

namespace App\Services;

use App\Models\Giving;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GivingAnalytics
{
    public function summarize(Carbon $from, Carbon $to): array
    {
        $daily = Giving::whereBetween('giving_date', [$from->toDateString(), $to->toDateString()])
            ->select('giving_date')->selectRaw('SUM(amount) as total')->groupBy('giving_date')->get();
        $totals = [];
        foreach ($daily as $day) {
            $week = $day->giving_date->copy()->startOfWeek(CarbonInterface::MONDAY)->toDateString();
            $totals[$week] = ($totals[$week] ?? 0) + (int) round((float) $day->total * 100);
        }
        $trend = collect();
        for ($week = $from->copy()->startOfWeek(CarbonInterface::MONDAY); $week->lte($to); $week->addWeek()) {
            $trend->push(['start' => $week->toDateString(), 'label' => $week->format('d M Y'), 'total' => ($totals[$week->toDateString()] ?? 0) / 100]);
        }

        return [
            'trend' => $trend,
            'districts' => $this->byMembership('districts', 'district_id', $from, $to),
            'groups' => $this->byMembership('groups', 'group_id', $from, $to),
            'total' => $trend->sum('total'),
        ];
    }

    private function byMembership(string $table, string $foreignKey, Carbon $from, Carbon $to): Collection
    {
        return DB::table('givings')
            ->join('members', 'members.id', '=', 'givings.member_id')
            ->leftJoin($table, $table.'.id', '=', 'members.'.$foreignKey)
            ->whereBetween('givings.giving_date', [$from->toDateString(), $to->toDateString()])
            ->select($table.'.id', $table.'.name as label')
            ->selectRaw('SUM(givings.amount) as total')
            ->groupBy($table.'.id', $table.'.name')->orderByDesc('total')->get()
            ->map(fn ($row): array => ['id' => $row->id, 'label' => $row->label ?? 'Unassigned', 'total' => round((float) $row->total, 2)]);
    }
}
