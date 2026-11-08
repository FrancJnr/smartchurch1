@extends('layouts.app')
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-2 mb-4"><h2>Users</h2><div><a class="btn btn-outline-primary me-2" href="{{ route('roles.index') }}">Configure roles</a><a class="btn btn-primary" href="{{ route('users.create') }}">Add user</a></div></div>
<div class="card table-responsive"><table class="table mb-0"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@foreach($users as $account)
<tr><td>{{ $account->name }}</td><td>{{ $account->email }}</td><td>{{ $account->role?->name ?? 'Unassigned' }}</td><td><span class="badge {{ $account->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $account->is_active ? 'Active' : 'Inactive' }}</span></td><td><a href="{{ route('users.edit', $account) }}">Edit user</a></td></tr>
@endforeach
</tbody></table></div><div class="mt-3">{{ $users->links('pagination::bootstrap-5') }}</div>
@endsection
