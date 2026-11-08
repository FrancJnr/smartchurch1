@extends('layouts.app')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><h2>{{ $campaign->status === 'draft' ? 'Review bulk SMS' : 'Bulk SMS results' }}</h2><div><a class="btn btn-light" href="{{ route('communications.index') }}">Message history</a><a class="btn btn-outline-primary" href="{{ route('communications.show', $campaign) }}">Refresh status</a></div></div>
<div class="row g-3 mb-4"><div class="col-lg-8"><div class="card card-body h-100"><h3 class="h5">{{ $campaign->audience_label }}</h3><p class="text-muted small">Created by {{ $campaign->creator->name }} · {{ $campaign->created_at->format('d M Y H:i') }}</p><div class="border rounded bg-light p-3" style="white-space: pre-wrap">{{ $campaign->message }}</div><p class="small text-muted mt-3 mb-0">The recipient list was captured when this preview was created. Members who become inactive, leave the selected audience, or change their phone number before sending are skipped.</p></div></div>
<div class="col-lg-4"><div class="card card-body h-100"><h3 class="h5">Recipient summary</h3><dl class="row mb-0"><dt class="col-8">Matched active members</dt><dd class="col-4 text-end">{{ $campaign->matched_members }}</dd><dt class="col-8">Unique recipients</dt><dd class="col-4 text-end">{{ $recipients->total() }}</dd><dt class="col-8">Duplicate numbers excluded</dt><dd class="col-4 text-end">{{ $campaign->duplicate_numbers }}</dd><dt class="col-8">Invalid numbers excluded</dt><dd class="col-4 text-end">{{ $campaign->invalid_numbers }}</dd></dl></div></div></div>
@if($campaign->status === 'draft')
<div class="alert alert-info">This is a preview. No messages have been sent.</div>
@can('communications.send')
@if($campaign->created_by === auth()->id())
<form method="post" action="{{ route('communications.send', $campaign) }}" class="mb-4" id="send-bulk-form">@csrf<button class="btn btn-primary" type="submit" id="send-bulk-button">Send to {{ $recipients->total() }} {{ $recipients->total() === 1 ? 'recipient' : 'recipients' }}</button></form>
@else
<p class="text-muted">Only the author can send this draft.</p>
@endif
@endcan
@else
<div class="row g-2 mb-4">
@foreach(['pending' => 'Queued', 'processing' => 'Sending', 'accepted' => 'Accepted', 'failed' => 'Failed', 'needs_review' => 'Needs review', 'skipped' => 'Skipped'] as $status => $label)
<div class="col-6 col-md-2"><div class="card card-body text-center"><small>{{ $label }}</small><strong class="h4 mb-0">{{ $counts->get($status, 0) }}</strong></div></div>
@endforeach
</div>
<p class="small text-muted">Accepted means provider acceptance, not confirmed delivery. Uncertain outcomes are not retried automatically; check Advanta before sending those messages again.</p>
@endif
<div class="card table-responsive"><table class="table mb-0"><thead><tr><th>Member</th><th>Phone</th><th>Status</th><th>Provider reference</th><th>Details</th></tr></thead><tbody>
@foreach($recipients as $recipient)
<tr><td>{{ $recipient->member_name }}</td><td class="text-nowrap">{{ $recipient->phone }}</td><td>{{ $campaign->status === 'draft' ? 'Ready' : ucfirst(str_replace('_', ' ', $recipient->status)) }}</td><td>{{ $recipient->provider_reference ?? '—' }}</td><td>{{ $recipient->error_message ?? '—' }}</td></tr>
@endforeach
</tbody></table></div><div class="mt-3">{{ $recipients->links('pagination::bootstrap-5') }}</div>
@endsection
@push('scripts')
<script>
const sendBulkForm = document.getElementById('send-bulk-form');
if (sendBulkForm) {
    sendBulkForm.addEventListener('submit', () => {
        const button = document.getElementById('send-bulk-button');
        button.disabled = true;
        button.textContent = 'Queuing…';
    });
}
</script>
@endpush
