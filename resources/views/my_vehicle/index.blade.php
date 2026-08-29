@extends('layouts.app')

@section('title', 'Ma voiture')

@section('content')
    <h1 class="title">Ma voiture</h1>

    @if (! $vehicle)
        <div class="notification is-warning is-light">
            Aucun véhicule n'est relié à A Better Routeplanner.
            Renseignez un token ABRP depuis
            <a href="{{ route('reference-data.vehicles.index') }}">Administration &rarr; Véhicules</a>.
        </div>
    @else
        @if ($vehicles->count() > 1)
            <div class="field">
                <label class="label">Véhicule</label>
                <div class="control">
                    <div class="select">
                        <select onchange="window.location.href = '{{ route('my-vehicle.index') }}?vehicule=' + this.value">
                            @foreach ($vehicles as $v)
                                <option value="{{ $v->id }}" @selected($v->id === $vehicle->id)>
                                    {{ $v->name }}{{ $v->is_default ? ' (par défaut)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        @endif

        @if (! $telemetry)
            <div class="notification is-info is-light">
                Aucun relevé pour l'instant. La récupération tourne toutes les 5 minutes ;
                lancez <code>php artisan telemetry:poll</code> pour forcer un premier appel.
            </div>
        @else
            <div class="box">
                <h2 class="title is-5">
                    {{ $vehicle->name }}
                    <span class="tag is-medium {{ $telemetry->is_charging ? 'is-success' : 'is-light' }} ml-2">
                        {{ $telemetry->is_charging ? 'en charge' : 'stationné' }}
                    </span>
                </h2>

                <div class="columns is-multiline">
                    <div class="column is-3">
                        <p class="heading">Batterie</p>
                        <p class="title is-1">{{ $soc !== null ? rtrim(rtrim(number_format($soc, 1, ',', ' '), '0'), ',') . ' %' : '—' }}</p>
                        <progress class="progress is-primary" value="{{ $soc ?? 0 }}" max="100"></progress>
                    </div>

                    <div class="column is-3">
                        <p class="heading">Énergie disponible</p>
                        <p class="title is-3">
                            {{ $availableKwh !== null ? str_replace('.', ',', (string) $availableKwh) . ' kWh' : '—' }}
                        </p>
                        @if ($netCapacity)
                            <p class="has-text-grey is-size-7">
                                sur {{ str_replace('.', ',', (string) $netCapacity) }} kWh utiles
                            </p>
                        @else
                            <p class="has-text-grey is-size-7">
                                Associez une courbe de recharge au véhicule pour connaître la capacité.
                            </p>
                        @endif
                    </div>

                    <div class="column is-3">
                        <p class="heading">Autonomie estimée</p>
                        <p class="title is-3">{{ $rangeKm !== null ? $rangeKm . ' km' : '—' }}</p>
                        @if ($rangeKm !== null)
                            <p class="has-text-grey is-size-7">
                                à {{ str_replace('.', ',', (string) $vehicle->kwh_per_100km) }} kWh/100 km
                            </p>
                        @else
                            <p class="has-text-grey is-size-7">
                                Renseignez une consommation sur la fiche du véhicule.
                            </p>
                        @endif
                    </div>

                    @if ($telemetry->odometer !== null)
                        <div class="column is-3">
                            <p class="heading">Odomètre</p>
                            <p class="title is-3">{{ number_format($telemetry->odometer, 0, ',', ' ') }} km</p>
                        </div>
                    @endif

                    @if ($telemetry->soh !== null)
                        <div class="column is-3">
                            <p class="heading">Santé batterie</p>
                            <p class="title is-3">{{ str_replace('.', ',', rtrim(rtrim((string) $telemetry->soh, '0'), '.')) }} %</p>
                            <p class="has-text-grey is-size-7">100 % = aucune dégradation</p>
                        </div>
                    @endif

                    @if ($telemetry->batt_temp !== null)
                        <div class="column is-3">
                            <p class="heading">Température batterie</p>
                            <p class="title is-3">{{ str_replace('.', ',', rtrim(rtrim((string) $telemetry->batt_temp, '0'), '.')) }} °C</p>
                        </div>
                    @endif

                    @if ($telemetry->power_kw !== null)
                        <div class="column is-3">
                            <p class="heading">Puissance instantanée</p>
                            <p class="title is-3">{{ str_replace('.', ',', (string) round(abs((float) $telemetry->power_kw), 1)) }} kW</p>
                            {{-- Convention ABRP : negatif = energie entrante (charge ou regeneration). --}}
                            <p class="has-text-grey is-size-7">
                                {{ (float) $telemetry->power_kw < 0 ? 'entrante (charge ou régénération)' : 'consommée' }}
                            </p>
                        </div>
                    @endif

                    @if ($telemetry->speed !== null)
                        <div class="column is-3">
                            <p class="heading">Vitesse</p>
                            <p class="title is-3">{{ (int) round((float) $telemetry->speed) }} km/h</p>
                            @if ((float) $telemetry->speed < 1)
                                <p class="has-text-grey is-size-7">à l'arrêt</p>
                            @endif
                        </div>
                    @endif

                    @if ($heading !== null)
                        <div class="column is-3">
                            <p class="heading">Cap</p>
                            <p class="title is-3">{{ $headingLabel }}</p>
                            <p class="has-text-grey is-size-7">{{ (int) round((float) $heading) }}°</p>
                        </div>
                    @endif

                    @if ($telemetry->ext_temp !== null)
                        <div class="column is-3">
                            <p class="heading">Température extérieure</p>
                            <p class="title is-3">{{ str_replace('.', ',', rtrim(rtrim((string) $telemetry->ext_temp, '0'), '.')) }} °C</p>
                        </div>
                    @endif

                    <div class="column is-3">
                        <p class="heading">Liaison</p>
                        <p class="title is-4">
                            @if ($isConnected)
                                <span class="tag is-success is-medium">connectée</span>
                            @else
                                <span class="tag is-warning is-medium">déconnectée</span>
                            @endif
                        </p>
                        <p class="has-text-grey is-size-7">
                            source <code>{{ $telemetry->telemetry_type ?? 'inconnue' }}</code>
                        </p>
                    </div>

                    <div class="column is-3">
                        <p class="heading">Dernier relevé</p>
                        <p class="title is-5">{{ $telemetry->recorded_at->diffForHumans() }}</p>
                        <p class="has-text-grey is-size-7">
                            {{ $telemetry->recorded_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                            @if ($telemetry->telemetry_type)
                                &middot; source <code>{{ $telemetry->telemetry_type }}</code>
                            @endif
                        </p>
                    </div>
                </div>

                @if ($telemetry->lat && $telemetry->lon)
                    <p class="has-text-grey is-size-7">
                        Position : {{ number_format($telemetry->lat, 5, ',', ' ') }}, {{ number_format($telemetry->lon, 5, ',', ' ') }}
                        &middot;
                        <a href="https://www.openstreetmap.org/?mlat={{ $telemetry->lat }}&mlon={{ $telemetry->lon }}#map=15/{{ $telemetry->lat }}/{{ $telemetry->lon }}"
                           target="_blank" rel="noopener">ouvrir dans OpenStreetMap</a>
                    </p>

                    {{-- La carte n'est chargee que sur demande : sans ce garde-fou, chaque
                         affichage de la page enverrait la position du vehicule a OSM. --}}
                    <div class="mt-3">
                        <button type="button" class="button is-small is-light" id="map-toggle"
                            data-lat="{{ $telemetry->lat }}" data-lon="{{ $telemetry->lon }}">
                            Afficher la carte
                        </button>
                        <div id="map-container" class="mt-3"></div>
                    </div>
                @endif
            </div>
        @endif

        <div class="box">
            <h2 class="title is-5">Relevé brut</h2>
            <p class="has-text-grey is-size-7 mb-4">
                Réponse complète d'ABRP pour le dernier relevé, sans filtre.
                Ce que renvoie l'API dépend de la source : le cloud du constructeur seul ne fournit que
                le niveau de charge et la position, le dongle OBD y ajoute le compteur, la santé de la
                batterie, la puissance et les températures. Tout champ que la voiture se mettrait à
                remonter apparaîtra ici automatiquement.
                @if ($typecode)
                    Modèle déclaré à ABRP : <code>{{ $typecode }}</code>.
                @endif
            </p>

            <details>
                <summary class="is-clickable has-text-link">Afficher les {{ count($rawTelemetry) + count($rawEnvelope) }} champs</summary>

                <div class="table-container mt-3">
                    <table class="table is-fullwidth is-narrow is-striped">
                        <tbody>
                            @foreach ($rawTelemetry as $key => $value)
                                <tr>
                                    <td><code>{{ $key }}</code></td>
                                    <td class="has-text-right">
                                        {{ $value === null ? '—' : (is_bool($value) ? ($value ? 'oui' : 'non') : $value) }}
                                    </td>
                                </tr>
                            @endforeach
                            @foreach ($rawEnvelope as $key => $value)
                                <tr class="has-text-grey">
                                    <td><code>{{ $key }}</code></td>
                                    <td class="has-text-right">
                                        {{ $value === null ? '—' : (is_bool($value) ? ($value ? 'oui' : 'non') : $value) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </div>

        <div class="box">
            <div class="level is-mobile mb-4">
                <div class="level-left">
                    <h2 class="title is-5 mb-0">Niveau de charge</h2>
                </div>
                <div class="level-right">
                    <div class="select is-small">
                        <select onchange="window.location.href = '{{ route('my-vehicle.index') }}?vehicule={{ $vehicle->id }}&jours=' + this.value">
                            @foreach ([7, 14, 30, 90] as $option)
                                <option value="{{ $option }}" @selected($days === $option)>{{ $option }} jours</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            @if ($pointCount < 2)
                <p class="has-text-grey">
                    Pas encore assez de relevés pour tracer une courbe ({{ $pointCount }} pour l'instant).
                </p>
            @else
                <canvas id="telemetry-soc-chart"
                    data-labels='@json($chartLabels)'
                    data-soc='@json($chartSoc)'
                    data-charging='@json($chartCharging)'></canvas>
                <p class="has-text-grey is-size-7 mt-3">
                    Les points verts correspondent aux relevés pendant lesquels la voiture était en charge.
                </p>
            @endif
        </div>

        <div class="box">
            <h2 class="title is-5">Recharges détectées</h2>
            <p class="has-text-grey is-size-7 mb-4">
                Reconstituées à partir des relevés : ABRP ne fournit pas de sessions.
                L'énergie indiquée est celle <strong>entrée dans la batterie</strong>, calculée depuis
                l'écart de niveau de charge. Elle est inférieure à l'énergie <strong>facturée à la borne</strong>,
                qui inclut les pertes de charge — d'où le bouton de pré-remplissage plutôt qu'un enregistrement direct.
            </p>

            @if (empty($sessions))
                <p class="has-text-grey">Aucune recharge détectée sur les {{ $days }} derniers jours.</p>
            @else
                <div class="table-container">
                    <table class="table is-fullwidth is-striped is-hoverable">
                        <thead>
                            <tr>
                                <th>Début</th>
                                <th>Durée</th>
                                <th class="has-text-right">Niveau</th>
                                <th class="has-text-right">Énergie estimée</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sessions as $session)
                                <tr>
                                    <td>
                                        {{ $session['started_at']->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                        @if ($session['in_progress'])
                                            <span class="tag is-success is-light ml-1">en cours</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ intdiv($session['duration_minutes'], 60) }} h {{ str_pad((string) ($session['duration_minutes'] % 60), 2, '0', STR_PAD_LEFT) }}
                                        @if ($session['samples'] < 2)
                                            <span class="tag is-warning is-light ml-1" title="Un seul relevé pendant la charge : les bornes sont approximatives">1 relevé</span>
                                        @endif
                                    </td>
                                    <td class="has-text-right">
                                        {{ $session['soc_start'] !== null ? (int) $session['soc_start'] . ' %' : '?' }}
                                        &rarr;
                                        {{ $session['soc_end'] !== null ? (int) $session['soc_end'] . ' %' : '?' }}
                                    </td>
                                    <td class="has-text-right">
                                        {{ $session['kwh'] !== null ? str_replace('.', ',', (string) $session['kwh']) . ' kWh' : '—' }}
                                    </td>
                                    <td class="has-text-right">
                                        @if (! $session['in_progress'] && $session['kwh'] !== null)
                                            <a class="button is-small is-link is-light"
                                               href="{{ route('charging-sessions.index', [
                                                   'prefill_vehicle' => $vehicle->id,
                                                   'prefill_date' => $session['started_at']->timezone(config('app.timezone'))->format('Y-m-d'),
                                                   'prefill_kwh' => $session['kwh'],
                                                   'prefill_duration' => sprintf('%02d:%02d', intdiv($session['duration_minutes'], 60), $session['duration_minutes'] % 60),
                                                   'prefill_telemetry_start' => $session['started_at']->format('Y-m-d H:i:s'),
                                               ]) }}">
                                                Pré-remplir
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <p class="has-text-grey is-size-7">
            Données fournies par A Better Routeplanner, qui les obtient du cloud du constructeur.
            Rafraîchissement d'environ une heure véhicule à l'arrêt, plus fréquent en charge :
            ce n'est pas du temps réel.
        </p>
    @endif
@endsection

@push('scripts')
    @vite('resources/js/telemetry.js')
@endpush
