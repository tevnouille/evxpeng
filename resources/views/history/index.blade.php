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
