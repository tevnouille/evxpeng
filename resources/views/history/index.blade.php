@extends('layouts.app')

@section('title', 'Historique')

@section('content')
    <h1 class="title">Historique</h1>

    <form method="GET" action="{{ route('history.index') }}" class="field has-addons mb-5">
        <div class="control">
            <div class="select">
                <select name="year" onchange="this.form.submit()">
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="control">
            <button type="submit" class="button">Afficher</button>
        </div>
    </form>

    @php
        $chartLabels = collect($months)->values();
        $chartKwh = collect($months)->keys()->map(fn ($n) => $statsByMonth[$n]['kwh'] ?? 0)->values();
        $chartCost = collect($months)->keys()->map(fn ($n) => $statsByMonth[$n]['cost'] ?? 0)->values();
    @endphp

    <div class="columns is-multiline mb-5">
        <div class="column is-6">
            <div class="box">
                <h2 class="title is-5">kWh par mois</h2>
                <canvas id="history-kwh-chart" data-labels='@json($chartLabels)' data-values='@json($chartKwh)'></canvas>
            </div>
        </div>
        <div class="column is-6">
            <div class="box">
                <h2 class="title is-5">Coût facturé par mois (€)</h2>
                <canvas id="history-cost-chart" data-labels='@json($chartLabels)' data-values='@json($chartCost)'></canvas>
            </div>
        </div>
    </div>

    <div class="columns is-multiline">
        @foreach ($months as $number => $name)
            @php($stats = $statsByMonth[$number] ?? ['count' => 0, 'kwh' => 0, 'cost' => 0])
            <div class="column is-3-desktop is-4-tablet is-6-mobile">
                <a href="{{ route('history.show', ['year' => $year, 'month' => $number]) }}" class="box has-text-centered has-text-link" style="display: block;">
                    <p class="title is-5">{{ $name }}</p>
                    <p class="has-text-grey">{{ $stats['count'] }} recharge(s)</p>
                    @if ($stats['count'] > 0)
                        <p class="has-text-grey">{{ number_format($stats['kwh'], 2, ',', ' ') }} kWh</p>
                        <p class="has-text-grey">{{ number_format($stats['cost'], 2, ',', ' ') }} €</p>
                    @endif
                </a>
            </div>
        @endforeach
    </div>
@endsection

@push('scripts')
    @vite('resources/js/history.js')
@endpush
