@extends('layouts.app')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="mb-1">Giving overview</h2>
        <p class="text-muted mb-0">PCEA Kinoo Parish Imani · Weekly giving summary</p>
    </div>
    @can('givings.create')
<a href="{{ route('givings.create') }}" class="btn btn-primary">Record Giving</a>
@endcan

</div>
<div class="row g-3 mb-4">
    @foreach([
        ['label' => 'Members', 'value' => number_format($members), 'note' => 'Registered members'],
        ['label' => 'Total giving', 'value' => 'KES '.number_format($total, 2), 'note' => 'All recorded giving'],
        ['label' => 'This week', 'value' => 'KES '.number_format($weekly, 2), 'note' => $weekStart->format('d M').' – '.$weekEnd->format('d M Y')],
        ['label' => 'This month', 'value' => 'KES '.number_format($monthly, 2), 'note' => now()->format('F Y')],
    ] as $metric)
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted mb-2">{{ $metric['label'] }}</div>
                    <div class="h4 fw-semibold mb-2">{{ $metric['value'] }}</div>
                    <small class="text-muted">{{ $metric['note'] }}</small>
                </div>
            </div>
        </div>
    @endforeach
</div>
@include('reports.charts')
<div class="card border-0 shadow-sm">
    <div class="card-body border-bottom">
        <h3 class="h5 mb-1">Giving by week</h3>
        <p class="text-muted small mb-0">Monday–Sunday · Grouped by giving date, including backdated entries. Expand a week to see its giving types.</p>
    </div>
    @forelse($weeks as $week)
        @php
            $start = \Illuminate\Support\Carbon::parse($week->week_start);
            $end = $start->copy()->addDays(6);
            $maximum = max(1, (float) $weeks->max('total'));
        @endphp
        <div class="p-3 p-md-4 {{ $loop->last ? '' : 'border-bottom' }}">
            <div class="row align-items-center g-3">
                <div class="col-md-4">
                    <div class="fw-semibold">{{ $start->format('d M Y') }} – {{ $end->format('d M Y') }}</div>
                    <small class="text-muted">{{ number_format($week->contributors) }} {{ $week->contributors == 1 ? 'contributor' : 'contributors' }} · {{ number_format($week->entries) }} giving {{ $week->entries == 1 ? 'entry' : 'entries' }}</small>
                </div>
                <div class="col-md-5">
                    <div class="fw-semibold mb-2">KES {{ number_format($week->total, 2) }}</div>
                    <div class="progress" style="height: 6px" aria-hidden="true">
                        <div class="progress-bar" style="width: {{ round((float) $week->total / $maximum * 100, 2) }}%"></div>
                    </div>
                </div>
                <div class="col-md-3 text-md-end">
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('reports.index', ['from' => $start->toDateString(), 'to' => $end->toDateString()]) }}">View week’s report</a>
                </div>
            </div>
            <details class="mt-3">
                <summary class="text-primary">Giving type breakdown</summary>
                <div class="table-responsive mt-2">
                    <table class="table table-sm mb-0">
                        <thead><tr><th scope="col">Giving type</th><th scope="col" class="text-end">Amount (KES)</th></tr></thead>
                        <tbody>
                            @foreach($typesByWeek->get($week->week_start, collect())->sortBy('givingType.name') as $type)
                                <tr><td>{{ $type->givingType->name }}</td><td class="text-end">{{ number_format($type->total, 2) }}</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr><th scope="row">Weekly total</th><th class="text-end">{{ number_format($week->total, 2) }}</th></tr></tfoot>
                    </table>
                </div>
            </details>
        </div>
    @empty
        <div class="p-5 text-center">
            <h4 class="h5">No giving recorded yet</h4>
            <p class="text-muted">Record the first contribution to see weekly totals here.</p>
            @can('givings.create')
<a href="{{ route('givings.create') }}" class="btn btn-primary">Record Giving</a>
@endcan

        </div>
    @endforelse
</div>
<div class="mt-4">{{ $weeks->links('pagination::bootstrap-5') }}</div>
@endsection
