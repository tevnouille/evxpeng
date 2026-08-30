@extends('layouts.app')

@section('title', 'Planificateur')

@section('content')
    <h1 class="title">Planificateur</h1>

    @if (! $vehicle)
        <div class="notification is-warning is-light">
            Aucun véhicule n'a de courbe de recharge associée. Renseignez-en une depuis
            <a href="{{ route('reference-data.vehicles.index') }}">Administration &rarr; Véhicules</a>.
        </div>
    @elseif ($stationCount === 0)
        <div class="notification is-warning is-light">
            La base des bornes est vide. Lancez l'import de la base nationale IRVE :
            <code>php artisan irve:import</code>.
        </div>
    @else
        <form method="GET" class="box">
            <div class="columns is-multiline">
                <div class="column is-6">
                    <div class="field">
                        <label class="label" for="depart">Départ</label>
                        <div class="control" style="position: relative;">
                            <input class="input" type="text" id="depart" name="depart"
                                   value="{{ $form['from'] }}" placeholder="Villabé, une adresse, ou 48.5836, 2.4436" required
                                   data-address-input autocomplete="off">
                            <input type="hidden" name="depart_lat" value="{{ request()->query('depart_lat') }}">
                            <input type="hidden" name="depart_lon" value="{{ request()->query('depart_lon') }}">
                            <div class="dropdown-content" data-suggestions hidden
                                 style="position: absolute; z-index: 30; width: 100%; max-height: 16rem; overflow-y: auto;"></div>
                        </div>
                    </div>
                </div>

                <div class="column is-6">
                    <div class="field">
                        <label class="label" for="arrivee">Arrivée</label>
                        <div class="control" style="position: relative;">
                            <input class="input" type="text" id="arrivee" name="arrivee"
                                   value="{{ $form['to'] }}" placeholder="Lyon, Marseille, une adresse…" required
                                   data-address-input autocomplete="off">
                            <input type="hidden" name="arrivee_lat" value="{{ request()->query('arrivee_lat') }}">
                            <input type="hidden" name="arrivee_lon" value="{{ request()->query('arrivee_lon') }}">
                            <div class="dropdown-content" data-suggestions hidden
                                 style="position: absolute; z-index: 30; width: 100%; max-height: 16rem; overflow-y: auto;"></div>
                        </div>
                    </div>
                </div>

                @if ($vehicles->count() > 1)
                    <div class="column is-3">
                        <div class="field">
                            <label class="label" for="vehicule">Véhicule</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select id="vehicule" name="vehicule">
                                        @foreach ($vehicles as $v)
                                            <option value="{{ $v->id }}" @selected($v->id === $vehicle->id)>{{ $v->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <input type="hidden" name="vehicule" value="{{ $vehicle->id }}">
                @endif

                <div class="column is-3">
                    <div class="field">
                        <label class="label" for="puissance_min">Puissance mini de borne</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select id="puissance_min" name="puissance_min">
                                    @foreach ($minPowers as $power)
                                        <option value="{{ $power }}" @selected((float) $power === $form['min_power'])>
                                            {{ $power > 0 ? $power.' kW et plus' : 'Toutes puissances' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="column is-2">
                    <div class="field">
                        <label class="label" for="soc_depart">Charge au départ</label>
                        <div class="control has-icons-right">
                            <input class="input" type="number" id="soc_depart" name="soc_depart" min="1" max="100" step="1"
                                   value="{{ (int) round($form['start_soc']) }}">
                            <span class="icon is-small is-right">%</span>
                        </div>
                        @if ($form['telemetry_soc'] !== null)
                            <p class="help">Relevé actuel : {{ rtrim(rtrim(number_format($form['telemetry_soc'], 1, ',', ' '), '0'), ',') }} %</p>
                        @endif
                    </div>
                </div>

                <div class="column is-2">
                    <div class="field">
                        <label class="label" for="soc_arrivee">Charge à l'arrivée</label>
                        <div class="control has-icons-right">
                            <input class="input" type="number" id="soc_arrivee" name="soc_arrivee" min="0" max="80" step="1"
                                   value="{{ (int) round($form['arrival_soc']) }}">
                            <span class="icon is-small is-right">%</span>
                        </div>
                    </div>
                </div>

                <div class="column is-2">
                    <div class="field">
                        <label class="label" for="soc_max">Charge max</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select id="soc_max" name="soc_max">
                                    @foreach ($maxSocs as $soc)
                                        <option value="{{ $soc }}" @selected((float) $soc === $form['max_soc'])>{{ $soc }} %</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <p class="help">Atteinte à la borne.</p>
                    </div>
                </div>

                <div class="column is-4">
                    <div class="field">
                        <label class="label" for="reseaux">Réseaux préférés</label>
                        <div class="control mb-2">
                            <input class="input is-small" type="search" data-network-filter
                                   placeholder="Filtrer : electra, ionity, total…" autocomplete="off"
                                   aria-controls="reseaux">
                        </div>
                        <div class="control">
                            <div class="select is-multiple is-fullwidth">
                                <select id="reseaux" name="reseaux[]" multiple size="7">
                                    @foreach ($networks as $network)
                                        <option value="{{ $network->network }}" @selected(in_array($network->network, $form['networks'], true))>{{ $network->network }} ({{ number_format($network->stations, 0, ',', ' ') }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <label class="checkbox mt-3">
                            <input type="checkbox" name="reseaux_only" value="1" @checked($form['networks_only'])>
                            N'utiliser que ces réseaux
                        </label>
                        <p class="help">
                            Facultatif. Sans la case cochée, un réseau sélectionné est simplement favorisé&nbsp;:
                            si aucune de ses bornes n'est atteignable, le plan prend la meilleure autre.
                            Ctrl/&#8984; + clic pour en choisir plusieurs.
                        </p>
                    </div>
                </div>

                <div class="column is-2">
                    <div class="field">
                        <label class="label" for="detour">Détour max</label>
                        <div class="control has-icons-right">
                            <input class="input" type="number" id="detour" name="detour" min="1" max="30" step="1"
                                   value="{{ (int) round($form['max_detour_km']) }}">
                            <span class="icon is-small is-right">km</span>
                        </div>
                        <p class="help">À vol d'oiseau depuis l'itinéraire.</p>
                    </div>
                </div>

                <div class="column is-2">
                    <div class="field">
                        <label class="label" for="reserve">Réserve mini</label>
                        <div class="control has-icons-right">
                            <input class="input" type="number" id="reserve" name="reserve" min="0" max="40" step="1"
                                   value="{{ (int) round($form['reserve_soc']) }}">
                            <span class="icon is-small is-right">%</span>
                        </div>
                        <p class="help">Niveau sous lequel on refuse de descendre.</p>
                    </div>
                </div>

                <div class="column is-2">
                    <div class="field">
                        <label class="label" for="consommation">Consommation</label>
                        <div class="control">
                            <input class="input" type="number" id="consommation" name="consommation" min="5" max="40" step="0.1"
                                   value="{{ rtrim(rtrim(number_format($form['consumption'], 1, '.', ''), '0'), '.') }}">
                        </div>
                        <p class="help">kWh / 100 km</p>
                    </div>
                </div>

                <div class="column is-2 is-flex is-align-items-flex-end">
                    <div class="field">
                        <div class="control">
                            <button class="button is-link" type="submit">Calculer</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        @if ($plan && ! ($plan['ok'] ?? false))
            <div class="notification is-warning is-light">
                <strong>Plan impossible.</strong> {{ $plan['error'] }}
                @if (isset($plan['candidates_count']))
                    <br>{{ number_format($plan['candidates_count'], 0, ',', ' ') }} borne(s) retenue(s) le long de l'itinéraire avec ces filtres.
                @endif
            </div>
        @endif

        @if ($plan && ($plan['ok'] ?? false))
            <div class="box">
                <div class="columns is-multiline has-text-centered">
                    <div class="column is-2">
                        <p class="heading">Distance</p>
                        <p class="title is-4">{{ number_format($plan['distance_km'], 0, ',', ' ') }} km</p>
                    </div>
                    <div class="column is-2">
                        <p class="heading">Durée totale</p>
                        <p class="title is-4">{{ \App\Support\Duration::human($plan["total_minutes"]) }}</p>
                    </div>
                    <div class="column is-2">
                        <p class="heading">Dont conduite</p>
                        <p class="title is-5">{{ \App\Support\Duration::human($plan['driving_minutes']) }}</p>
                    </div>
                    <div class="column is-2">
                        <p class="heading">Dont recharge</p>
                        <p class="title is-5">{{ \App\Support\Duration::human($plan['charging_minutes']) }}</p>
                    </div>
                    <div class="column is-2">
                        <p class="heading">Arrêts</p>
                        <p class="title is-4">{{ count($plan['stops']) }}</p>
                    </div>
                    <div class="column is-2">
                        <p class="heading">Arrivée</p>
                        <p class="title is-4">{{ number_format($plan['arrival_soc'], 0, ',', ' ') }} %</p>
                    </div>
                </div>

                <p class="has-text-grey is-size-7">
                    {{ $plan['from']['label'] }} &rarr; {{ $plan['to']['label'] }} ·
                    {{ number_format($plan['energy_kwh'], 1, ',', ' ') }} kWh consommés à
                    {{ rtrim(rtrim(number_format($plan['consumption'], 1, ',', ' '), '0'), ',') }} kWh/100 km ·
                    autonomie théorique {{ $plan['range_km'] }} km ·
                    {{ number_format($plan['candidates_count'], 0, ',', ' ') }} bornes candidates le long du trajet.
                </p>
            </div>

            <div class="box">
                <div id="planner-map" style="height: 460px;"
                     data-geometry="{{ json_encode($plan['geometry']) }}"
                     data-stops="{{ json_encode(collect($plan['stops'])->map(fn ($stop) => $stop['station'] + [
                        'km' => $stop['km'],
                        'minutes' => $stop['minutes'],
                        'soc_in' => $stop['soc_in'],
                        'soc_out' => $stop['soc_out'],
                        'detour_km' => $stop['detour_km'],
                     ])) }}"
                     data-from="{{ json_encode([$plan['from']['lat'], $plan['from']['lon'], $plan['from']['label']]) }}"
                     data-to="{{ json_encode([$plan['to']['lat'], $plan['to']['lon'], $plan['to']['label']]) }}"></div>
            </div>

            @if ($plan['stops'] === [])
                <div class="notification is-success is-light">
                    Aucun arrêt nécessaire : le trajet passe d'une traite, arrivée à {{ number_format($plan['arrival_soc'], 0, ',', ' ') }} %.
                </div>
            @else
                <div class="box">
                    <h2 class="subtitle">Arrêts de recharge</h2>
                    <div class="table-container">
                        <table class="table is-fullwidth is-striped is-narrow">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Borne</th>
                                    <th>Réseau</th>
                                    <th class="has-text-right">Km</th>
                                    <th class="has-text-right">Détour</th>
                                    <th class="has-text-right">Puissance</th>
                                    <th class="has-text-right">Arrivée</th>
                                    <th class="has-text-right">Départ</th>
                                    <th class="has-text-right">Énergie</th>
                                    <th class="has-text-right">Durée</th>
                                    <th>Navigation</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($plan['stops'] as $index => $stop)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            <strong>{{ $stop['station']['name'] }}</strong>
                                            <br>
                                            <span class="has-text-grey is-size-7">
                                                {{ $stop['station']['address'] ?: $stop['station']['city'] }}
                                            </span>
                                        </td>
                                        <td>{{ $stop['station']['network'] ?: $stop['station']['operator'] ?: '—' }}</td>
                                        <td class="has-text-right">{{ number_format($stop['km'], 0, ',', ' ') }}</td>
                                        <td class="has-text-right">{{ number_format($stop['detour_km'], 1, ',', ' ') }} km</td>
                                        <td class="has-text-right">
                                            {{ rtrim(rtrim(number_format($stop['power_kw'], 1, ',', ' '), '0'), ',') }} kW
                                            @if ($stop['effective_kw'] < $stop['power_kw'])
                                                <br><span class="has-text-grey is-size-7">bridée à {{ rtrim(rtrim(number_format($stop['effective_kw'], 1, ',', ' '), '0'), ',') }} kW</span>
                                            @endif
                                        </td>
                                        <td class="has-text-right">{{ number_format($stop['soc_in'], 0, ',', ' ') }} %</td>
                                        <td class="has-text-right">{{ number_format($stop['soc_out'], 0, ',', ' ') }} %</td>
                                        <td class="has-text-right">{{ number_format($stop['energy_kwh'], 1, ',', ' ') }} kWh</td>
                                        <td class="has-text-right">
                                            <strong>{{ \App\Support\Duration::human($stop['minutes']) }}</strong>
                                            @if ($stop['average_kw'])
                                                <br><span class="has-text-grey is-size-7">{{ number_format($stop['average_kw'], 0, ',', ' ') }} kW moy.</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{-- Les libelles IRVE sont parfois trompeurs : ces liens montrent
                                                 la position reelle, qui elle fait foi. --}}
                                            <div class="buttons are-small">
                                                <a class="button is-small is-link is-light" target="_blank" rel="noopener"
                                                   href="https://www.google.com/maps/search/?api=1&query={{ $stop['station']['lat'] }},{{ $stop['station']['lon'] }}">Maps</a>
                                                <a class="button is-small is-link is-light" target="_blank" rel="noopener"
                                                   href="https://www.waze.com/ul?ll={{ $stop['station']['lat'] }},{{ $stop['station']['lon'] }}&amp;navigate=yes">Waze</a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <p class="has-text-grey is-size-7">
                Estimation : consommation constante, ni relief, ni météo, ni trafic, et une borne supposée libre
                et à sa puissance nominale. Bornes issues de la base nationale IRVE
                ({{ number_format($stationCount, 0, ',', ' ') }} stations, mise à jour
                {{ $stationsUpdatedAt ? \Carbon\Carbon::parse($stationsUpdatedAt)->format('d/m/Y') : '—' }}),
                itinéraire OSRM, temps de charge issus de la
                <a href="{{ route('charging-curves.index') }}">courbe de recharge</a> du véhicule.
            </p>
        @endif
    @endif
@endsection

@push('scripts')
    @vite('resources/js/planner.js')
@endpush
