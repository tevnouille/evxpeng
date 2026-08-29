@extends('layouts.app')

@section('title', 'Courbe de recharge')

@section('content')
    <h1 class="title">Courbe de recharge</h1>

    @if ($curves->isEmpty())
        <div class="notification is-warning is-light">Aucune courbe de recharge disponible.</div>
    @else
        @if ($curves->count() > 1)
            <div class="field">
                <label class="label">Modèle</label>
                <div class="control">
                    <div class="select">
                        <select onchange="window.location.href = '{{ route('charging-curves.index') }}?modele=' + this.value">
                            @foreach ($curves as $c)
                                <option value="{{ $c['slug'] }}" @selected($c['slug'] === $curve['slug'])>{{ $c['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        @endif

        <div class="box">
            <h2 class="title is-5">{{ $curve['name'] }} <span class="has-text-grey is-size-6">{{ $curve['model_year'] }}</span></h2>

            <div class="columns is-multiline">
                <div class="column is-3">
                    <p class="heading">Puissance max (DC)</p>
                    <p class="title is-4">{{ $curve['max_power_kw'] }} kW</p>
                </div>
                <div class="column is-3">
                    <p class="heading">Batterie (brute)</p>
                    <p class="title is-4">{{ str_replace('.', ',', (string) $curve['battery_kwh']) }} kWh</p>
                    <p class="has-text-grey is-size-7">
                        {{ str_replace('.', ',', (string) $curve['battery_net_kwh']) }} kWh utiles
                        @if (! empty($curve['battery_note']))
                            &middot; {{ $curve['battery_note'] }}
                        @endif
                    </p>
                </div>
                <div class="column is-3">
                    <p class="heading">10 → 80 %</p>
                    <p class="title is-4">{{ $curve['time_10_80'] }}</p>
                    <p class="has-text-grey is-size-7">{{ str_replace('.', ',', (string) $curve['avg_10_80_kw']) }} kW en moyenne</p>
                </div>
                <div class="column is-3">
                    <p class="heading">0 → 100 %</p>
                    <p class="title is-4">{{ $curve['time_0_100'] }}</p>
                    <p class="has-text-grey is-size-7">{{ str_replace('.', ',', (string) $curve['avg_0_100_kw']) }} kW en moyenne</p>
                </div>
            </div>

            <p class="has-text-grey is-size-7">
                Plage de charge optimale : <strong>{{ $curve['optimal_range'] }}</strong> &middot;
                Taux C maximal : <strong>{{ $curve['max_c_rate'] }}</strong>
            </p>
        </div>

        @if ($curve['estimated'])
            <div class="notification is-warning is-light">
                <strong>Données estimées.</strong> evkx.net indique que cette courbe est estimée à partir des données
                constructeur et de batteries comparables, et non mesurée. Le pic annoncé ({{ $curve['max_power_kw'] }} kW,
                soit {{ $curve['max_c_rate'] }}) est nettement supérieur à ce que relèvent les essais réels sur ce modèle :
                à prendre comme un ordre de grandeur, pas comme une mesure.
            </div>
        @endif

        <div class="box">
            <h2 class="title is-5">Puissance de charge selon le niveau de batterie</h2>
            <canvas id="curve-power-chart"
                data-labels='@json(collect($curve['points'])->pluck('soc'))'
                data-values='@json(collect($curve['points'])->pluck('kw'))'></canvas>
        </div>

        <div class="box">
            <h2 class="title is-5">Détail de 0 à 100 %</h2>
            <div class="table-scroll">
                <table class="table is-fullwidth is-striped is-narrow is-hoverable">
                    <thead>
                        <tr>
                            <th>SoC</th>
                            <th class="has-text-right">Puissance</th>
                            <th class="has-text-right">Capacité brute</th>
                            <th class="has-text-right">Capacité nette</th>
                            <th class="has-text-right">Temps cumulé</th>
                            <th class="has-text-right">Énergie chargée</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($curve['points'] as $point)
                            <tr>
                                <td>{{ $point['soc'] }} %</td>
                                <td class="has-text-right">{{ str_replace('.', ',', (string) $point['kw']) }} kW</td>
                                <td class="has-text-right">{{ str_replace('.', ',', (string) $point['battery_gross_kwh']) }} kWh</td>
                                <td class="has-text-right">{{ str_replace('.', ',', (string) $point['battery_net_kwh']) }} kWh</td>
                                <td class="has-text-right">{{ $point['time'] }}</td>
                                <td class="has-text-right">{{ str_replace('.', ',', (string) $point['kwh']) }} kWh</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="box">
            <h2 class="title is-5">Temps de recharge restant</h2>
            <p class="has-text-grey is-size-7 mb-4">
                Depuis un niveau de batterie donné, temps nécessaire pour atteindre 80, 90 ou 100 %.
            </p>

            <canvas id="curve-remaining-chart" class="mb-5"
                data-labels='@json(collect($curve['points'])->pluck('soc'))'
                data-to80='@json(collect($curve['points'])->pluck('to_80_minutes'))'
                data-to90='@json(collect($curve['points'])->pluck('to_90_minutes'))'
                data-to100='@json(collect($curve['points'])->pluck('to_100_minutes'))'></canvas>

            <div class="table-scroll">
                <table class="table is-fullwidth is-striped is-narrow is-hoverable">
                    <thead>
                        <tr>
                            <th>Niveau actuel</th>
                            <th class="has-text-right">Jusqu'à 80 %</th>
                            <th class="has-text-right">Jusqu'à 90 %</th>
                            <th class="has-text-right">Jusqu'à 100 %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($curve['points'] as $point)
                            <tr>
                                <td>{{ $point['soc'] }} %</td>
                                <td class="has-text-right">{{ $point['to_80'] ?? '—' }}</td>
                                <td class="has-text-right">{{ $point['to_90'] ?? '—' }}</td>
                                <td class="has-text-right">{{ $point['to_100'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <p class="has-text-grey is-size-7">
            Source : <a href="{{ $curve['source_url'] }}" target="_blank" rel="noopener">evkx.net</a>
            (mise à jour du {{ \Carbon\Carbon::parse($curve['source_updated'])->format('d/m/Y') }}).
        </p>
    @endif
@endsection

@push('scripts')
    @vite('resources/js/charging-curve.js')
@endpush
