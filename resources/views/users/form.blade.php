@extends('layouts.app')
@section('content')
<h2>{{ $account->exists ? 'Edit user' : 'Add user' }}</h2>
<form method="post" action="{{ $account->exists ? route('users.update', $account) : route('users.store') }}" class="card card-body">
@csrf
@if($account->exists)
    @method('PUT')
@endif
<label for="user-name" class="form-label">Name</label><input id="user-name" name="name" required maxlength="255" class="form-control mb-3" value="{{ old('name', $account->name) }}">
<label for="user-email" class="form-label">Email</label><input id="user-email" name="email" type="email" required maxlength="255" class="form-control mb-3" value="{{ old('email', $account->email) }}">
<label for="user-role" class="form-label">Role</label><select id="user-role" name="role_id" required class="form-select mb-3"><option value="">Select a role</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected(old('role_id', $account->role_id) == $role->id)>{{ $role->name }}</option>@endforeach</select>
<label for="user-status" class="form-label">Account status</label><select id="user-status" name="is_active" class="form-select mb-3"><option value="1" @selected(old('is_active', $account->is_active))>Active</option><option value="0" @selected(!old('is_active', $account->is_active))>Inactive</option></select>
<label for="user-password" class="form-label">{{ $account->exists ? 'New password (leave blank to keep current password)' : 'Password' }}</label><input id="user-password" type="password" name="password" autocomplete="new-password" minlength="12" maxlength="255" @required(!$account->exists) class="form-control mb-3">
<label for="user-confirmation" class="form-label">Confirm password</label><input id="user-confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="255" @required(!$account->exists) class="form-control mb-3">
<p class="text-muted small">Use at least 12 characters. Inactive users cannot sign in. Changing a password ends that user's existing sessions.</p>
<div><button class="btn btn-primary" type="submit">Save user</button><a class="btn btn-light ms-2" href="{{ route('users.index') }}">Cancel</a></div>
</form>
@endsection
