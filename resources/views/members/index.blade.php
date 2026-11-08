@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between mb-3"><h2>Members</h2>@can('members.manage')<a class="btn btn-primary" href="{{ route('members.create') }}">Register Member</a>@endcan</div>
<form class="mb-3"><label class="visually-hidden" for="member-search">Search members</label><input id="member-search" class="form-control" name="search" placeholder="Search name, phone or eKanisa number" value="{{ request('search') }}"></form>
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>eKanisa</th><th>Name</th><th>Phone</th><th>District</th><th>Group</th><th>Status</th><th></th></tr></thead><tbody>
@foreach($members as $member)
<tr><td>{{ $member->ekanisa_number }}</td><td>{{ $member->name }}</td><td>{{ $member->phone }}</td><td>{{ $member->district?->name ?? 'Unassigned' }}</td><td>{{ $member->group?->name ?? 'Unassigned' }}</td><td>{{ $member->status }}</td><td>@can('members.manage')<a href="{{ route('members.edit', $member) }}">Edit</a>@endcan</td></tr>
@endforeach
</tbody></table></div>{{ $members->links('pagination::bootstrap-5') }}
@endsection
