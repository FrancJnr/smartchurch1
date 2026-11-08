@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between mb-4"><h2>Roles and access</h2><a class="btn btn-primary" href="{{ route('roles.create') }}">Add role</a></div>
<p class="text-muted">Access is enforced on every request. The protected Administrator role always retains full access.</p>
<div class="row g-3">@foreach($roles as $role)<div class="col-md-6"><div class="card card-body h-100"><div class="d-flex justify-content-between"><h3 class="h5">{{ $role->name }}</h3><span>{{ $role->users_count }} users</span></div><ul class="small">
@if($role->is_system)
<li>Full access to all modules</li>
@else
@forelse($role->permissions ?? [] as $permission)<li>{{ \App\Models\Role::PERMISSIONS[$permission] ?? $permission }}</li>@empty<li>No module permissions</li>@endforelse
@endif
</ul>@unless($role->is_system)<a href="{{ route('roles.edit', $role) }}">Edit permissions</a>@endunless</div></div>@endforeach</div>
@endsection
