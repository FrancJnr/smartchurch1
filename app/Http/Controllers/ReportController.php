<?php

namespace App\Http\Controllers;

use App\Models\Giving;
use App\Models\GivingType;
use App\Models\Member;
use App\Services\GivingAnalytics;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function dashboard(Request $request): View
    {
        $filters = $request->validate([
            'chart_from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'chart_to' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);
        $chartFrom = Carbon::parse($filters['chart_from'] ?? today()->startOfWeek(CarbonInterface::MONDAY)->subWeeks(11)->toDateString());
        $chartTo = Carbon::parse($filters['chart_to'] ?? today()->toDateString());
        if ($chartFrom->gt($chartTo) || $chartFrom->diffInDays($chartTo) > 366) {
            throw ValidationException::withMessages(['chart_to' => 'Choose an end date on or after the start date, within a 366-day range.']);
        }
        $charts = app(GivingAnalytics::class)->summarize($chartFrom, $chartTo);
        $weekStart = today()->startOfWeek(CarbonInterface::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);
        $weekExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "date(giving_date, '-' || ((cast(strftime('%w', giving_date) as integer) + 6) % 7) || ' days')"
            : 'DATE_SUB(giving_date, INTERVAL WEEKDAY(giving_date) DAY)';

        $weeks = Giving::query()
            ->selectRaw($weekExpression.' AS week_start, SUM(amount) AS total, COUNT(*) AS entries, COUNT(DISTINCT member_id) AS contributors')
            ->groupByRaw($weekExpression)
            ->orderByDesc('week_start')
            ->paginate(12)->withQueryString();

        $typesByWeek = collect();
        if ($weeks->isNotEmpty()) {
            $typesByWeek = Giving::with('givingType')
                ->whereBetween('giving_date', [
                    $weeks->last()->week_start,
                    Carbon::parse($weeks->first()->week_start)->addDays(6)->toDateString(),
                ])
                ->select('giving_type_id')
                ->selectRaw($weekExpression.' AS week_start, SUM(amount) AS total')
                ->groupByRaw($weekExpression)
                ->groupBy('giving_type_id')
                ->get()
                ->groupBy('week_start');
        }

        return view('reports.dashboard', [
            'charts' => $charts,
            'chartFrom' => $chartFrom,
            'chartTo' => $chartTo,
            'members' => Member::count(),
            'total' => Giving::sum('amount'),
            'weekly' => Giving::whereBetween('giving_date', [$weekStart->toDateString(), $weekEnd->toDateString()])->sum('amount'),
            'monthly' => Giving::whereYear('giving_date', today()->year)->whereMonth('giving_date', today()->month)->sum('amount'),
            'weeks' => $weeks,
            'typesByWeek' => $typesByWeek,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
        ]);
    }

    public function index(Request $r)
    {
        $d = $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'giving_type_id' => 'nullable|exists:giving_types,id', 'ekanisa_number' => 'nullable|string']);
        $q = Giving::query()->when($d['from'] ?? null, fn ($q, $v) => $q->whereDate('giving_date', '>=', $v))->when($d['to'] ?? null, fn ($q, $v) => $q->whereDate('giving_date', '<=', $v))->when($d['giving_type_id'] ?? null, fn ($q, $v) => $q->where('giving_type_id', $v))->when($d['ekanisa_number'] ?? null, fn ($q, $v) => $q->whereHas('member', fn ($m) => $m->where('ekanisa_number', $v)));

        return view('reports.index', ['total' => (clone $q)->sum('amount'), 'count' => (clone $q)->count(), 'givings' => $q->with(['member', 'givingType'])->orderByDesc('giving_date')->paginate(30)->withQueryString(), 'types' => GivingType::orderBy('name')->get()]);
    }

    public function export(Request $r)
    {
        $d = $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'giving_type_id' => 'nullable|exists:giving_types,id', 'ekanisa_number' => 'nullable|string']);
        $q = Giving::with(['member', 'givingType'])->when($d['from'] ?? null, fn ($q, $v) => $q->whereDate('giving_date', '>=', $v))->when($d['to'] ?? null, fn ($q, $v) => $q->whereDate('giving_date', '<=', $v))->when($d['giving_type_id'] ?? null, fn ($q, $v) => $q->where('giving_type_id', $v))->when($d['ekanisa_number'] ?? null, fn ($q, $v) => $q->whereHas('member', fn ($m) => $m->where('ekanisa_number', $v)));

        return response()->streamDownload(function () use ($q) {
            $f = fopen('php://output', 'w');
            fputcsv($f, ['Date', 'Receipt', 'eKanisa', 'Member', 'Giving Type', 'Amount KES', 'Method']);
            foreach ($q->orderBy('id')->cursor() as $g) {
                fputcsv($f, [$g->giving_date->format('Y-m-d'), $g->transaction_reference, $g->member->ekanisa_number, $g->member->name, $g->givingType->name, $g->amount, $g->payment_method]);
            }fclose($f);
        }, 'church-giving-report.csv', ['Content-Type' => 'text/csv']);
    }
}
