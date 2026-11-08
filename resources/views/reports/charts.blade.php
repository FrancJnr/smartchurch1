<section aria-labelledby="giving-charts-title" class="mb-4">
<div class="card card-body mb-3">
    <div class="d-flex flex-wrap justify-content-between gap-2"><h3 class="h5" id="giving-charts-title">Giving trends & membership</h3><strong>Period total: KES {{ number_format($charts['total'], 2) }}</strong></div>
    <form class="row g-2 align-items-end" action="{{ route('dashboard') }}" method="get">
        <div class="col-sm-4"><label for="chart-from" class="form-label">Chart start date</label><input id="chart-from" class="form-control" type="date" name="chart_from" value="{{ $chartFrom->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
        <div class="col-sm-4"><label for="chart-to" class="form-label">Chart end date</label><input id="chart-to" class="form-control" type="date" name="chart_to" value="{{ $chartTo->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
        <div class="col-sm-4"><button class="btn btn-primary">Update charts</button><a class="btn btn-light" href="{{ route('dashboard') }}">Reset</a></div>
    </form>
    <p class="small text-muted mt-3 mb-0">Charts use giving dates within the selected period (up to 366 days). Group and district totals use members’ current assignments. Unassigned members are included.</p>
</div>
<div class="card card-body mb-3">
    <h4 class="h6">Weekly giving trend · KES</h4>
    <p class="small text-muted">Monday–Sunday. First and last weeks include only dates inside your selected period.</p>
    @php
        $trend = $charts['trend'];
        $peak = max(1, $trend->max('total'));
        $points = $trend->map(fn ($point, $index) => [
            'x' => 85 + $index * (700 / max(1, $trend->count() - 1)),
            'y' => 200 - $point['total'] / $peak * 165,
            'label' => $point['label'],
            'total' => $point['total'],
        ]);
        $line = $points->map(fn ($point) => $point['x'].','.$point['y'])->implode(' ');
    @endphp
    <div class="chart-scroll">
        <svg class="giving-trend-chart" viewBox="0 0 840 250" role="img" aria-labelledby="trend-title trend-description">
            <title id="trend-title">Weekly giving from {{ $chartFrom->format('d M Y') }} to {{ $chartTo->format('d M Y') }}</title>
            <desc id="trend-description">Line chart of weekly giving totals. The exact amounts are available in the table below.</desc>
            @foreach([0, .5, 1] as $ratio)
            <line x1="85" x2="800" y1="{{ 200 - 165 * $ratio }}" y2="{{ 200 - 165 * $ratio }}" stroke="#e3e8f1"/>
            <text x="76" y="{{ 204 - 165 * $ratio }}" text-anchor="end" font-size="11" fill="#64748b">{{ number_format($peak * $ratio, 0) }}</text>
            @endforeach
            <polygon points="85,200 {{ $line }} {{ $points->last()['x'] }},200" fill="#3b71ca" opacity=".09"/>
            <polyline points="{{ $line }}" fill="none" stroke="#3b71ca" stroke-width="3" stroke-linejoin="round"/>
            @foreach($points as $point)
            <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" fill="#3b71ca"><title>Week of {{ $point['label'] }}: KES {{ number_format($point['total'], 2) }}</title></circle>
            @endforeach
            <text x="85" y="230" font-size="12" fill="#64748b">{{ $trend->first()['label'] }}</text>
            <text x="800" y="230" text-anchor="end" font-size="12" fill="#64748b">{{ $trend->count() > 1 ? $trend->last()['label'] : '' }}</text>
        </svg>
    </div>
    <details><summary class="text-primary">View weekly chart data</summary><div class="table-responsive mt-2"><table class="table table-sm"><thead><tr><th>Week beginning (Monday)</th><th class="text-end">Giving (KES)</th></tr></thead><tbody>@foreach($trend as $point)<tr><td>{{ $point['label'] }}</td><td class="text-end">{{ number_format($point['total'], 2) }}</td></tr>@endforeach</tbody></table></div></details>
</div>
<div class="row g-3">
@foreach(['districts' => 'Giving per district', 'groups' => 'Giving per group'] as $dimension => $title)
<div class="col-lg-6"><div class="card card-body h-100"><h4 class="h6">{{ $title }} · KES</h4>
    @php($maxTotal = max(1, $charts[$dimension]->max('total')))
    @forelse($charts[$dimension] as $entry)
    <div class="mb-3"><div class="d-flex justify-content-between gap-2 small mb-1"><span class="text-break">{{ $entry['label'] }}</span><strong class="text-nowrap">{{ number_format($entry['total'], 2) }}</strong></div><div class="membership-chart-track" aria-hidden="true"><div class="membership-chart-bar {{ $dimension === 'groups' ? 'group-bar' : '' }}" style="width: {{ round($entry['total'] / $maxTotal * 100, 2) }}%"></div></div></div>
    @empty
    <p class="text-muted mb-0">No giving in this period.</p>
    @endforelse
    <div class="border-top pt-2 mt-auto d-flex justify-content-between small"><span>Total</span><strong>KES {{ number_format($charts[$dimension]->sum('total'), 2) }}</strong></div>
</div></div>
@endforeach
</div>
</section>
