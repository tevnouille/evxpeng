@extends('layouts.app')

@section('title', 'Courbe de recharge')

@section('content')
    <h1 class="title">Courbe de recharge</h1>

    @if (! $curve)
        <div class="notification is-warning is-light">
            Aucun véhicule n'a de courbe de recharge associée.
            Associez-en une depuis <a href="{{ route('reference-data.vehicles.index') }}">Administration &rarr; Véhicules</a>.
        </div>
    @else
        <div class="columns">
            @if ($vehicles->count() > 1)
                <div class="column is-narrow">
                    <div class="field">
                        <label class="label">Véhicule</label>
                        <div class="control">
                            <div class="select">
                                <select onchange="window.location.search = new URLSearchParams({vehicule: this.value, borne: '{{ $cap }}'}).toString()">
                                    @foreach ($vehicles as $v)
                                        <option value="{{ $v->id }}" @selected($v->id === $vehicle->id)>
                                            {{ $v->name }}{{ $v->is_default ? ' (par défaut)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="column is-narrow">
                <div class="field">
                    <label class="label">Puissance de la borne</label>
                    <div class="control">
                        <div class="select">
                            <select onchange="window.location.search = new URLSearchParams({vehicule: '{{ $vehicle->id }}', borne: this.value}).toString()">
                                <option value="" @selected($cap === null)>Sans limite (courbe véhicule)</option>
                                @foreach ($chargerPowers as $power)
                                    <option value="{{ $power }}" @selected($cap === $power)>
                                        {{ str_replace('.', ',', rtrim(rtrim(number_format($power, 1, '.', ''), '0'), '.')) }} kW
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @if ($capAuto !== null)
                        <p class="help is-success">
                            Déduite de la puissance mesurée
                            ({{ str_replace('.', ',', (string) round(abs((float) $telemetry->power_kw), 1)) }} kW).
                            Vous pouvez la changer.
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <div class="box">
            <h2 class="title is-5">
                {{ $vehicle->name }}
                <span class="has-text-grey is-size-6">{{ $curve['name'] }} &middot; {{ $curve['model_year'] }}</span>
            </h2>

            <div class="columns is-multiline">
                <div class="column is-3">
                    <p class="heading">{{ $cap !== null ? 'Puissance max (borne)' : 'Puissance max (DC)' }}</p>
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

            @if ($cap === null)
                <p class="has-text-grey is-size-7">
                    Plage de charge optimale : <strong>{{ $curve['optimal_range'] }}</strong> &middot;
                    Taux C maximal : <strong>{{ $curve['max_c_rate'] }}</strong>
                </p>
            @else
                <p class="has-text-grey is-size-7">
                    Durées et puissances recalculées pour une borne limitée à
                    <strong>{{ str_replace('.', ',', rtrim(rtrim(number_format($cap, 1, '.', ''), '0'), '.')) }} kW</strong> :
                    à énergie égale, une puissance bridée allonge d'autant la durée du segment concerné.
                    Les segments où la voiture demandait déjà moins que la borne sont inchangés.
                </p>
            @endif

            @if (! empty($curve['consumption_wltp_kwh_100km']))
                <p class="has-text-grey is-size-7 mt-2">
                    Autonomie WLTP : <strong>{{ $curve['range_wltp_km'] }} km</strong> &middot;
                    Consommation WLTP : <strong>{{ str_replace('.', ',', (string) $curve['consumption_wltp_kwh_100km']) }} kWh/100 km</strong>,
                    soit <strong>{{ str_replace('.', ',', (string) $curve['consumption_wltp_losses_kwh_100km']) }} kWh/100 km</strong> pertes de charge comprises.
                    C'est cette seconde valeur qui est comparable aux kWh facturés à la borne, et donc à la consommation
                    à renseigner dans <a href="{{ route('reference-data.vehicles.index') }}">la fiche du véhicule</a>
                    pour l'estimation des kilomètres.
                </p>
            @endif
        </div>

        @if ($telemetry)
            <div class="box" id="tlm-live"
                data-url="{{ route('charging-curves.state', ['vehicle' => $vehicle, 'borne' => $cap]) }}"
                data-charging="{{ $telemetry->is_charging ? '1' : '0' }}">
                <h2 class="title is-5">
                    Niveau actuel
                    <span id="tlm-state" class="tag is-medium {{ $telemetry->is_charging ? 'is-success' : 'is-light' }} ml-2">
                        {{ $telemetry->is_charging ? 'en charge' : 'stationné' }}
                    </span>
                </h2>

                <div class="columns is-multiline">
                    <div class="column is-3">
                        <p class="heading">Batterie</p>
                        <p class="title is-2" id="tlm-soc">{{ $currentSoc !== null ? $currentSoc . ' %' : '—' }}</p>
                        <progress class="progress is-primary is-small" id="tlm-progress" value="{{ $currentSoc ?? 0 }}" max="100"></progress>
                    </div>

                    @if ($currentPoint)
                        <div class="column is-3">
                            <p class="heading">Énergie disponible</p>
                            <p class="title is-4" id="tlm-available">{{ str_replace('.', ',', (string) $currentPoint['kwh']) }} kWh</p>
                            <p class="has-text-grey is-size-7">
                                sur {{ str_replace('.', ',', (string) $curve['battery_net_kwh']) }} kWh utiles
                            </p>
                        </div>
                        <div class="column is-6">
                            <p class="heading">Temps de recharge restant</p>
                            <div class="tags are-medium mt-2">
                                <span class="tag">80 % &nbsp;<strong id="tlm-to80">{{ $currentPoint['to_80'] ?? 'atteint' }}</strong></span>
                                <span class="tag">90 % &nbsp;<strong id="tlm-to90">{{ $currentPoint['to_90'] ?? 'atteint' }}</strong></span>
                                <span class="tag">100 % &nbsp;<strong id="tlm-to100">{{ $currentPoint['to_100'] ?? 'atteint' }}</strong></span>
                            </div>
                            <p class="has-text-grey is-size-7">
                                Durées théoriques sur borne rapide, d'après la courbe ci-dessous.
                            </p>
                        </div>
                    @endif
                </div>

                <div id="tlm-charging" class="notification is-success is-light @if (! $telemetry->is_charging) is-hidden @endif">
                    <div class="columns is-multiline is-mobile">
                        <div class="column is-3">
                            <p class="heading">Puissance</p>
                            <p class="title is-4"><span id="tlm-power">—</span> kW</p>
                            <p class="has-text-grey is-size-7" id="tlm-power-sense">&nbsp;</p>
                        </div>
                        <div class="column is-3">
                            <p class="heading">Chargé sur cette session</p>
                            <p class="title is-4"><span id="tlm-session-kwh">—</span> kWh</p>
                            <p class="has-text-grey is-size-7" id="tlm-session-detail">&nbsp;</p>
                        </div>
                        <div class="column is-3">
                            <p class="heading">Température batterie</p>
                            <p class="title is-4"><span id="tlm-batt-temp">—</span> °C</p>
                        </div>
                        <div class="column is-3">
                            <p class="heading">Durée</p>
                            <p class="title is-4" id="tlm-session-duration">—</p>
                        </div>
                    </div>

                    <div class="columns is-multiline is-mobile mb-0">
                        <div class="column is-12">
                            <p class="heading">Temps restant à la puissance mesurée</p>
                            <div class="tags are-medium mt-2" id="tlm-live-remaining"></div>
                            <p class="has-text-grey is-size-7">
                                Calculé depuis la puissance réellement délivrée, contrairement aux durées théoriques
                                ci-dessus qui viennent de la courbe du véhicule.
                            </p>
                        </div>
                    </div>
                </div>

                <p class="has-text-grey is-size-7">
                    Relevé <span id="tlm-recorded">{{ $telemetry->recorded_at->diffForHumans() }}
                    ({{ $telemetry->recorded_at->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }})</span>
                    via A Better Routeplanner.
                    <span id="tlm-refreshed"></span>
                    @if ($telemetry->lat && $telemetry->lon)
                        &middot;
                        <a href="https://www.openstreetmap.org/?mlat={{ $telemetry->lat }}&mlon={{ $telemetry->lon }}#map=15/{{ $telemetry->lat }}/{{ $telemetry->lon }}"
                           target="_blank" rel="noopener">voir la position</a>
                    @endif
                </p>
            </div>
        @endif

        @if ($curve['estimated'])
            <div class="notification is-warning is-light">
                <strong>Données estimées.</strong> evkx.net indique que cette courbe est estimée à partir des données
                constructeur et de batteries comparables, et non relevée sur un véhicule.
                À prendre comme un ordre de grandeur : les puissances et les durées peuvent s'écarter sensiblement
                de ce que tu constateras en charge réelle.
            </div>
        @endif

        <div class="box">
            <h2 class="title is-5">Puissance de charge selon le niveau de batterie</h2>
            <canvas id="curve-power-chart"
                data-labels='@json(collect($curve['points'])->pluck('soc'))'
                data-values='@json(collect($curve['points'])->pluck('kw_effective'))'
                data-current-soc="{{ $currentSoc ?? '' }}"
                data-current-kw="{{ $currentPoint['kw_effective'] ?? '' }}"
                data-charging="{{ $telemetry && $telemetry->is_charging ? '1' : '0' }}"></canvas>
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
                            <th class="has-text-right">Temps cumulé</th>
                            <th class="has-text-right">Énergie chargée</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($curve['points'] as $point)
                            <tr @class(['is-selected' => $currentSoc === $point['soc']])>
                                <td>{{ $point['soc'] }} %</td>
                                <td class="has-text-right">{{ str_replace('.', ',', (string) $point['kw_effective']) }} kW</td>
                                <td class="has-text-right">{{ str_replace('.', ',', (string) $point['battery_gross_kwh']) }} kWh</td>
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
                            <tr @class(['is-selected' => $currentSoc === $point['soc']])>
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
