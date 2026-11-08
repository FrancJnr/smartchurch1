@extends('layouts.app')
@section('content')
<h2>Welcome, {{ auth()->user()->name }}</h2>
<div class="card card-body">
    <p>Your role: <strong>{{ auth()->user()->role?->name ?? 'Not assigned' }}</strong></p>
    <p class="mb-0">Choose an available module from the navigation. Contact an administrator if you need additional access.</p>
</div>
@endsection
