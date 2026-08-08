@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1 class="title">Dashboard</h1>
    <div id="ev-dashboard-root"
        data-api-url="{{ route('dashboard.data') }}"
        data-fuel-prices-url="{{ route('fuel-prices.index') }}"
        data-vehicles='@json($vehicles->map(fn ($v) => ["id" => $v->id, "name" => $v->name]))'></div>
@endsection

@push('scripts')
    @vite('resources/js/dashboard.jsx')
@endpush
