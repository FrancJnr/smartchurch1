@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4"><h2>Communications</h2>@can('communications.send')<a class="btn btn-primary" href="{{ route('communications.create') }}">Compose SMS</a>@endcan</div>
<p class="text-muted">Bulk messages to members, groups and districts. Accepted means the SMS provider accepted the message; handset delivery is not confirmed.</p>
<div class="card table-responsive"><table class="table mb-0"><thead><tr><th>Created</th><th>Audience</th><th>Message</th><th>Author</th><th>Status</th><th>Accepted</th><th></th></tr></thead><tbody>
@forelse($campaigns as $campaign)
<tr><td class="text-nowrap">{{ $campaign->created_at->format('d M Y H:i') }}</td><td>{{ $campaign->audience_label }}</td><td>{{ \Illuminate\Support\Str::limit($campaign->message, 70) }}</td><td>{{ $campaign->creator->name }}</td><td>{{ $campaign->status === 'draft' ? 'Draft — not sent' : ($campaign->unresolved_count > 0 ? 'In progress' : 'Processed') }}</td><td>{{ $campaign->accepted_count }} / {{ $campaign->recipients_count }}</td><td><a href="{{ route('communications.show', $campaign) }}">{{ $campaign->status === 'draft' ? 'Preview' : 'Results' }}</a></td></tr>
@empty
<tr><td colspan="7" class="text-center text-muted py-4">No bulk messages yet.</td></tr>
@endforelse
</tbody></table></div><div class="mt-3">{{ $campaigns->links('pagination::bootstrap-5') }}</div>
@endsection
