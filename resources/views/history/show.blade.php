@extends('layouts.app')

@section('title', $monthName . ' ' . $year)

@section('content')
    <div class="level">
        <div class="level-left">
            <h1 class="title">{{ $monthName }} {{ $year }}</h1>
        </div>
        <div class="level-right">
            <a href="{{ route('history.index', ['year' => $year]) }}" class="button is-light">&larr; Retour aux mois</a>
        </div>
    </div>

    @if ($stats['sessions_count'] > 0)
        <div class="columns is-mobile is-multiline mb-1">
            <div class="column">
                <div class="box has-text-centered">
                    <p class="heading">Total kWh</p>
                    <p class="title is-4">{{ number_format($stats['kwh'], 2, ',', ' ') }}</p>
                </div>
            </div>
            <div class="column">
                <div class="box has-text-centered">
                    <p class="heading">Total facturé</p>
                    <p class="title is-4">{{ number_format($stats['cost'], 2, ',', ' ') }} €</p>
                </div>
            </div>
            <div class="column">
                <div class="box has-text-centered">
                    <p class="heading">Coût moyen / kWh</p>
                    <p class="title is-4">{{ $stats['avg_cost_per_kwh'] !== null ? number_format($stats['avg_cost_per_kwh'], 4, ',', ' ') . ' €' : '—' }}</p>
                </div>
            </div>
            <div class="column">
                <div class="box has-text-centered">
                    <p class="heading">Nombre de recharges</p>
                    <p class="title is-4">{{ $stats['sessions_count'] }}</p>
                </div>
            </div>
        </div>

        <h2 class="title is-6 mb-2">Équivalent carburant</h2>
        <div class="columns is-mobile is-multiline mb-1">
            <div class="column">
                <div class="box has-text-centered">
                    <p class="heading">Essence équivalente</p>
                    <p class="title is-4">{{ number_format($fuelEquivalent['essence_liters'], 2, ',', ' ') }} L</p>
                </div>
            </div>
            <div class="column">
                <div class="box has-text-centered">
                    <p class="heading">Coût essence équivalent</p>
                    <p class="title is-4">{{ number_format($fuelEquivalent['essence_cost'], 2, ',', ' ') }} €</p>
                </div>
            </div>
            <div class="column">
                <div class="box has-text-centered">
                    <p class="heading">{{ $fuelEquivalent['savings_essence'] >= 0 ? 'Économie vs essence' : 'Surcoût vs essence' }}</p>
                    <p class="title is-4">{{ number_format(abs($fuelEquivalent['savings_essence']), 2, ',', ' ') }} €</p>
                </div>
            </div>
            <div class="column">
                <div class="box has-text-centered">
                    <p class="heading">Diesel équivalent</p>
                    <p class="title is-4">{{ number_format($fuelEquivalent['diesel_liters'], 2, ',', ' ') }} L</p>
                </div>
            </div>
            <div class="column">
                <div class="box has-text-centered">
                    <p class="heading">Coût diesel équivalent</p>
                    <p class="title is-4">{{ number_format($fuelEquivalent['diesel_cost'], 2, ',', ' ') }} €</p>
                </div>
            </div>
            <div class="column">
                <div class="box has-text-centered">
                    <p class="heading">{{ $fuelEquivalent['savings_diesel'] >= 0 ? 'Économie vs diesel' : 'Surcoût vs diesel' }}</p>
                    <p class="title is-4">{{ number_format(abs($fuelEquivalent['savings_diesel']), 2, ',', ' ') }} €</p>
                </div>
            </div>
        </div>
        <p class="is-size-7 has-text-grey mb-5">
            Consommation par véhicule (Administration → Véhicules), par défaut 15 kWh / 6 L essence / 6 L diesel pour 100 km.
            Prix appliqués par date de recharge réelle
            (moyenne pondérée obtenue : {{ $fuelEquivalent['avg_essence_price'] ?? '—' }} €/L essence, {{ $fuelEquivalent['avg_diesel_price'] ?? '—' }} €/L diesel) —
            {{ $fuelEquivalent['known_price_sessions'] }}/{{ $fuelEquivalent['total_sessions'] }} recharge(s) avec un prix du jour connu
            @if ($fuelEquivalent['estimated'])
                , le reste utilise l'estimation par défaut (1,95 €/L)
            @endif
            .
            <a href="{{ route('fuel-prices.index') }}">Voir l'historique des prix carburants →</a>
        </p>
    @endif

    @include('charging_sessions._sessions_table', ['emptyMessage' => 'Aucune recharge ce mois-ci.'])
@endsection
