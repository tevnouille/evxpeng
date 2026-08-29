@extends('layouts.app')

@section('title', $monthName . ' ' . $year)

@section('content')
    <div class="level">
        <div class="level-left">
            <h1 class="title">{{ $monthName }} {{ $year }}</h1>
        </div>
        <div class="level-right">
            <div class="buttons">
                <a href="{{ route('history.show', $previous) }}" class="button is-light" title="{{ $previousMonthName }} {{ $previous['year'] }}">&larr; Mois précédent</a>
                <a href="{{ route('history.index', ['year' => $year]) }}" class="button is-light">Retour aux mois</a>
                <a href="{{ route('history.show', $next) }}" class="button is-light" title="{{ $nextMonthName }} {{ $next['year'] }}">Mois suivant &rarr;</a>
            </div>
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
            Consommation propre à chaque véhicule (Administration → Véhicules) — aucune valeur par défaut :
            une recharge dont le véhicule n'a pas de consommation renseignée n'est pas comptée dans cet équivalent
            @if ($fuelEquivalent['configured_sessions'] < $fuelEquivalent['total_sessions'])
                ({{ $fuelEquivalent['configured_sessions'] }}/{{ $fuelEquivalent['total_sessions'] }} recharge(s) concernée(s))
            @endif
            .
            Prix appliqués par date de recharge réelle
            (moyenne pondérée obtenue : {{ $fuelEquivalent['avg_essence_price'] ?? '—' }} €/L essence, {{ $fuelEquivalent['avg_diesel_price'] ?? '—' }} €/L diesel) —
            {{ $fuelEquivalent['known_price_sessions'] }}/{{ $fuelEquivalent['total_sessions'] }} recharge(s) avec un prix du jour connu
            @if ($fuelEquivalent['estimated'])
                , le reste utilise l'estimation par défaut (1,95 €/L)
            @endif
            .
            <a href="{{ route('fuel-prices.index') }}">Voir l'historique des prix carburants →</a>
        </p>

        <div class="box">
            <h2 class="title is-5">Recharges par jour</h2>
            <canvas id="history-combined-chart"
                data-label-prefix="Jour "
                data-labels='@json($dailyLabels)'
                data-kwh='@json($dailyKwh)'
                data-cost='@json($dailyCost)'></canvas>
        </div>

        <div class="box">
            <h2 class="title is-5">Par fournisseur</h2>
            <div class="table-container">
                <table class="table is-fullwidth is-striped is-hoverable">
                    <thead>
                        <tr>
                            <th>Fournisseur</th>
                            <th class="has-text-right">Recharges</th>
                            <th class="has-text-right">kWh</th>
                            <th class="has-text-right">Coût</th>
                            <th class="has-text-right">Coût / kWh</th>
                            <th class="has-text-right">Part du coût</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($statsByProvider as $providerName => $row)
                            <tr>
                                <td>{{ $providerName }}</td>
                                <td class="has-text-right">{{ $row['count'] }}</td>
                                <td class="has-text-right">{{ number_format($row['kwh'], 2, ',', ' ') }}</td>
                                <td class="has-text-right">{{ number_format($row['cost'], 2, ',', ' ') }} €</td>
                                <td class="has-text-right">
                                    {{ $row['avg_cost_per_kwh'] !== null ? number_format($row['avg_cost_per_kwh'], 4, ',', ' ') . ' €' : '—' }}
                                </td>
                                <td class="has-text-right">
                                    {{ $row['cost_share'] !== null ? number_format($row['cost_share'], 1, ',', ' ') . ' %' : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total</th>
                            <th class="has-text-right">{{ $stats['sessions_count'] }}</th>
                            <th class="has-text-right">{{ number_format($stats['kwh'], 2, ',', ' ') }}</th>
                            <th class="has-text-right">{{ number_format($stats['cost'], 2, ',', ' ') }} €</th>
                            <th class="has-text-right">
                                {{ $stats['avg_cost_per_kwh'] !== null ? number_format($stats['avg_cost_per_kwh'], 4, ',', ' ') . ' €' : '—' }}
                            </th>
                            <th class="has-text-right">100 %</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif

    @include('charging_sessions._sessions_table', ['emptyMessage' => 'Aucune recharge ce mois-ci.'])
@endsection

@push('scripts')
    @vite('resources/js/history.js')
@endpush
