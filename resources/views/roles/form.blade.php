@extends('layouts.app')
@section('content')
<h2>{{ $role->exists ? 'Edit role' : 'Add role' }}</h2>
<form class="card card-body" method="post" action="{{ $role->exists ? route('roles.update', $role) : route('roles.store') }}">
@csrf
@if($role->exists)
@method('PUT')
@endif
<label for="role-name" class="form-label">Role name</label><input id="role-name" name="name" required maxlength="100" class="form-control mb-4" value="{{ old('name', $role->name) }}">
<fieldset><legend class="h5">Module permissions</legend>
@foreach($permissions as $key => $label)
<div class="form-check mb-3"><input type="checkbox" class="form-check-input" id="permission-{{ $loop->index }}" name="permissions[]" value="{{ $key }}" @checked(in_array($key, old('permissions', $role->permissions ?? [])))><label class="form-check-label" for="permission-{{ $loop->index }}">{{ $label }}</label></div>
@endforeach
</fieldset>
<p class="small text-muted">Editing members also enables viewing members. Recording giving enables viewing giving. Exporting reports enables viewing reports. Sending bulk SMS enables viewing communications. User/role management can grant full access; reserve it for trusted administrators.</p>
<div><button class="btn btn-primary">Save role</button><a class="btn btn-light ms-2" href="{{ route('roles.index') }}">Cancel</a></div>
</form>
@endsection
