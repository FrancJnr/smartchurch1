@extends('layouts.app')
@section('content')
<h2>{{ $member->exists ? 'Edit Member' : 'Register Member' }}</h2>
<form method="post" action="{{ $member->exists ? route('members.update', $member) : route('members.store') }}" class="card card-body">
@csrf
@if($member->exists)
@method('PUT')
@endif
<label for="member-number" class="form-label">Existing eKanisa number</label><input id="member-number" required maxlength="50" name="ekanisa_number" class="form-control mb-3" value="{{ old('ekanisa_number', $member->ekanisa_number) }}">
<label for="member-name" class="form-label">Full name</label><input id="member-name" required maxlength="150" name="name" class="form-control mb-3" value="{{ old('name', $member->name) }}">
<label for="member-phone" class="form-label">Phone (e.g. +254712345678)</label><input id="member-phone" required name="phone" class="form-control mb-3" value="{{ old('phone', $member->phone) }}">
<div class="row"><div class="col-md-6">
<label for="member-district" class="form-label">District</label><select id="member-district" name="district_id" class="form-select mb-3"><option value="">Unassigned</option>@foreach($districts as $district)<option value="{{ $district->id }}" @selected(old('district_id', $member->district_id) == $district->id)>{{ $district->name }}{{ $district->is_active ? '' : ' (archived)' }}</option>@endforeach</select>
</div><div class="col-md-6">
<label for="member-group" class="form-label">Group</label><select id="member-group" name="group_id" class="form-select mb-3"><option value="">Unassigned</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected(old('group_id', $member->group_id) == $group->id)>{{ $group->name }}{{ $group->is_active ? '' : ' (archived)' }}</option>@endforeach</select>
</div></div>
@can('settings.manage')
<p class="small"><a href="{{ route('catalogs.index', 'districts') }}">Manage districts</a> · <a href="{{ route('catalogs.index', 'groups') }}">Manage groups</a></p>
@endcan
<label for="member-status" class="form-label">Status</label><select id="member-status" name="status" class="form-select mb-3">@foreach(['active','inactive'] as $status)<option value="{{ $status }}" @selected(old('status', $member->status ?: 'active') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
<button class="btn btn-primary">Save Member</button>
</form>
@endsection
