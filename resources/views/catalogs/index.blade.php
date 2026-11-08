@extends('layouts.app')
@section('content')
<h2>{{ $title }}</h2>
<div class="d-flex gap-2 flex-wrap mb-4">@foreach(['districts' => 'Districts', 'groups' => 'Groups', 'giving-types' => 'Giving types'] as $key => $label)<a class="btn {{ $catalog === $key ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('catalogs.index', $key) }}">{{ $label }}</a>@endforeach</div>
<div class="card card-body mb-4"><form class="row g-3 align-items-end" method="post" action="{{ route('catalogs.store', $catalog) }}">@csrf<div class="col-md-9"><label for="new-name" class="form-label">Add to {{ strtolower($title) }}</label><input id="new-name" name="name" required maxlength="100" class="form-control" value="{{ old('name') }}"></div><div class="col-md-3"><button class="btn btn-primary">Add item</button></div></form></div>
<p class="text-muted small">Archive items to remove them from new selections. Existing memberships, giving and reports are retained.</p>
<div class="card card-body">
@forelse($items as $item)
<form method="post" action="{{ route('catalogs.update', [$catalog, $item->id]) }}" class="row g-2 align-items-end mb-3 pb-3 border-bottom">@csrf @method('PUT')
<div class="col-md-7"><label class="form-label" for="name-{{ $item->id }}">Name</label><input id="name-{{ $item->id }}" name="name" required maxlength="100" class="form-control" value="{{ $item->name }}"></div>
<div class="col-md-3"><label class="form-label" for="status-{{ $item->id }}">Status</label><select id="status-{{ $item->id }}" name="is_active" class="form-select"><option value="1" @selected($item->is_active)>Active</option><option value="0" @selected(!$item->is_active)>Archived</option></select></div>
<div class="col-md-2"><button class="btn btn-outline-primary">Save</button></div>
</form>
@empty
<p class="mb-0">No {{ strtolower($title) }} yet. Add the first item above.</p>
@endforelse
{{ $items->links('pagination::bootstrap-5') }}
</div>
@endsection
