@extends('layouts.app')

@section('title', 'Ma voiture')

@section('content')
    <h1 class="title">Ma voiture</h1>

    @if (! $vehicle)
        <div class="notification is-warning is-light">
            Aucun véhicule n'est relié au boîtier OBD.
            Renseignez un identifiant MQTT depuis
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

        {{-- Relancer la collecte sans attendre le planificateur : c'est ce qu'on
             veut en arrivant sur une borne ou en descendant de voiture. Le
             compte a rebours, lui, est pilote par resources/js/telemetry.js. --}}
        <div class="level is-mobile mb-4">
            <div class="level-left">
                <form method="POST" action="{{ route('reference-data.sources.refresh', 'telemetrie') }}">
                    @csrf
                    <button type="submit" class="button is-link is-light">
                        Mettre à jour les informations
                    </button>
                </form>
            </div>
            <div class="level-right">
                <span class="has-text-grey is-size-7" id="auto-refresh-status" data-auto-refresh="30">
                    Actualisation automatique dans 30 s
                </span>
            </div>
        </div>

        @if (! $telemetry)
            <div class="notification is-info is-light">
                Aucun relevé pour l'instant. Le boîtier publie en continu et l'application vide sa
                file toutes les 15 secondes ; vérifiez que XPCarData est connecté au dongle et au broker.
            </div>
        @else
            <div class="box">
                <h2 class="title is-5">
                    {{ $vehicle->name }}
                    <span class="tag is-medium {{ $state['tag'] }} ml-2" title="{{ $state['detail'] }}">
                        {{ $state['label'] }}
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
                            {{-- Convention du projet : negatif = energie entrante (charge ou regeneration). --}}
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
                            <span class="tag {{ $link['tag'] }} is-medium">{{ $link['label'] }}</span>
                        </p>
                        {{-- L'etat se juge sur la fraicheur des releves. Un drapeau
                             « source declaree » ne prouve rien : celui d'ABRP restait
                             a « connectée » dongle debranche, d'ou son abandon. --}}
                        <p class="has-text-grey is-size-7">
                            {!! $link['detail'] !!}
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

        {{-- Une colonne par source : ce qui arrive du cloud constructeur et ce
             qu'ajoute le dongle OBD ne se distinguaient nulle part, alors que
             c'est ce qui explique qu'un champ apparaisse puis disparaisse. Les
             champs listes sont ceux reellement observes, pas une liste ecrite
             en dur : une source qui se met a en fournir un de plus s'affiche
             sans modification de code. --}}
        <div class="box">
            <h2 class="title is-5">Sources de données</h2>
            <p class="has-text-grey is-size-7 mb-4">
                Le boîtier n'interroge pas tous les capteurs en même temps : il les sollicite à tour de
                rôle, si bien qu'un relevé isolé ne porte qu'une partie des champs. Les valeurs ci-dessous
                sont donc les <strong>dernières connues</strong> de chaque source, pas forcément du même
                instant. Une source cesse d'apparaître dès qu'elle n'a plus rien envoyé sur la période
                affichée ({{ $days }} jours). Les relevés collectés autrefois via A Better Routeplanner
                restent en base et alimentent toujours les graphiques, mais ce chemin n'existe plus : il
                n'apparaît donc pas ici.
            </p>

            @if (empty($sources))
                <p class="has-text-grey">Aucun relevé sur la période.</p>
            @else
                <div class="columns is-multiline">
                    @foreach ($sources as $source)
                        <div class="column is-6">
                            <div class="box has-background-white-bis">
                                <div class="level is-mobile mb-2">
                                    <div class="level-left">
                                        <div>
                                            <p class="title is-6 mb-1">{{ $source['label'] }}</p>
                                            <code>{{ $source['type'] }}</code>
                                        </div>
                                    </div>
                                    <div class="level-right">
                                        @if ($source['live'])
                                            <span class="tag is-success">en direct</span>
                                        @else
                                            <span class="tag is-light">inactive</span>
                                        @endif
                                    </div>
                                </div>

                                <p class="has-text-grey is-size-7 mb-3">
                                    {{ $source['count'] }} relevé(s) &middot;
                                    dernier {{ $source['last_at']->diffForHumans() }}
                                    ({{ $source['last_at']->timezone(config('app.timezone'))->format('d/m H:i') }})
                                </p>

                                @if (empty($source['latest']))
                                    <p class="has-text-grey is-size-7">Aucun champ exploitable remonté.</p>
                                @else
                                    <table class="table is-fullwidth is-narrow is-striped mb-0">
                                        <tbody>
                                            @foreach ($source['latest'] as $key => $value)
                                                <tr>
                                                    <td><code>{{ $key }}</code></td>
                                                    <td class="has-text-right">
                                                        @if (is_bool($value))
                                                            {{ $value ? 'oui' : 'non' }}
                                                        @elseif (is_array($value))
                                                            <code>{{ json_encode($value, JSON_UNESCAPED_UNICODE) }}</code>
                                                        @elseif (is_float($value))
                                                            {{-- Cinq decimales : la voiture remonte des flottants bruts
                                                                 — calib_ref_cons en aligne douze — mais moins tronquerait
                                                                 les coordonnees a une centaine de metres. --}}
                                                            {{ str_replace('.', ',', rtrim(rtrim(number_format($value, 5, '.', ' '), '0'), '.')) }}
                                                        @else
                                                            {{ str_replace('.', ',', (string) $value) }}
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    <p class="has-text-grey is-size-7 mt-2">
                                        Dernière valeur connue pour chaque champ, pas forcément du même relevé.
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="box">
            <h2 class="title is-5">Relevé brut</h2>
            <p class="has-text-grey is-size-7 mb-4">
                Dernier relevé complet, sans filtre. Ce que le boîtier remonte dépend des capteurs
                qu'il a réussi à interroger : il les sollicite à tour de rôle, si bien qu'un relevé isolé
                ne porte qu'une partie des champs. Tout champ que la voiture se mettrait à remonter
                apparaîtra ici automatiquement.
                @if ($typecode)
                    Modèle déclaré : <code>{{ $typecode }}</code>.
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
                        <select onchange="window.location.href = '{{ route('my-vehicle.index', array_merge(Arr::except(request()->query(), ['jours']), ['vehicule' => $vehicle->id])) }}&jours=' + this.value">
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
            <div class="columns is-vcentered mb-2">
                <div class="column">
                    <h2 class="title is-5 mb-0">Kilomètres et recharges au quotidien</h2>
                </div>
                @if (! empty($months))
                    <div class="column is-narrow">
                        <div class="select is-small">
                            <select onchange="window.location.href = '{{ route('my-vehicle.index', array_merge(Arr::except(request()->query(), ['mois']), ['vehicule' => $vehicle->id])) }}&mois=' + this.value">
                                @foreach ($months as $option)
                                    <option value="{{ $option }}" @selected($month && $month->format('Y-m') === $option)>
                                        {{ \Carbon\CarbonImmutable::createFromFormat('Y-m-d', $option . '-01')->translatedFormat('F Y') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif
            </div>

            @if (empty($activityDays))
                <p class="has-text-grey">Aucun relevé pour l'instant : le tableau se remplira au fil des collectes.</p>
            @else
                <p class="has-text-grey is-size-7 mb-4">
                    Les kilomètres viennent de l'odomètre, que seule une source OBD remonte&nbsp;;
                    un trajet à cheval sur minuit est compté le jour de son arrivée.
                    L'énergie est celle <strong>entrée dans la batterie</strong>, déduite de l'écart
                    de niveau de charge — inférieure à celle facturée à la borne.
                    Un jour sans aucun relevé affiche «&nbsp;—&nbsp;»&nbsp;: ce n'est pas un jour sans rouler.
                </p>

                <div class="table-container">
                    <table class="table is-fullwidth is-narrow is-striped is-hoverable">
                        <thead>
                            <tr>
                                <th>Jour</th>
                                <th class="has-text-right">Distance</th>
                                <th class="has-text-right">Recharges</th>
                                <th class="has-text-right">Énergie rechargée</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($activityDays as $day)
                                <tr @class(['has-text-grey' => ! $day['has_data']])>
                                    <td>{{ $day['date']->translatedFormat('D d/m') }}</td>
                                    <td class="has-text-right">
                                        @if (! $day['has_data'])
                                            —
                                        @elseif (! $day['has_odometer'])
                                            <span title="Aucun relevé d'odomètre ce jour-là">?</span>
                                        @else
                                            {{ number_format($day['km'], 0, ',', ' ') }} km
                                        @endif
                                    </td>
                                    <td class="has-text-right">{{ $day['charges'] ?: '' }}</td>
                                    <td class="has-text-right">
                                        {{ $day['kwh'] > 0 ? str_replace('.', ',', (string) round($day['kwh'], 2)) . ' kWh' : '' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="has-text-weight-bold">
                                <td>Total</td>
                                <td class="has-text-right">{{ number_format($activityTotals['km'], 0, ',', ' ') }} km</td>
                                <td class="has-text-right">{{ $activityTotals['charges'] ?: '' }}</td>
                                <td class="has-text-right">
                                    {{ $activityTotals['kwh'] > 0 ? str_replace('.', ',', (string) $activityTotals['kwh']) . ' kWh' : '' }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <p class="has-text-grey is-size-7">
                    {{ $activityTotals['days_with_data'] }} jour(s) avec au moins un relevé sur le mois.
                    @if ($activityTotals['kwh_per_100km'])
                        Consommation apparente&nbsp;:
                        <strong>{{ str_replace('.', ',', (string) $activityTotals['kwh_per_100km']) }} kWh/100 km</strong>
                        — indicative, l'énergie rechargée et les kilomètres parcourus ne couvrent pas
                        exactement la même période.
                    @endif
                </p>
            @endif
        </div>

        <div class="box">
            <h2 class="title is-5">Recharges détectées</h2>
            <p class="has-text-grey is-size-7 mb-4">
                Publiées par le boîtier, ou reconstituées à partir des relevés quand il n'était pas là.
                Les sessions <span class="tag is-primary is-light">mesurée</span> viennent du boîtier OBD :
                l'énergie y est relevée au compteur de la batterie. Les autres sont reconstituées, et leur
                énergie est <strong>calculée depuis l'écart de niveau de charge</strong> — sur une même
                recharge, ce calcul s'est révélé <strong>29 % sous la valeur mesurée</strong>. Elle est inférieure à l'énergie <strong>facturée à la borne</strong>,
                qui inclut les pertes de charge — d'où le bouton de pré-remplissage plutôt qu'un enregistrement direct.
            </p>

            @if ($hiddenSessions > 0 || $showingAllSessions)
                <p class="has-text-grey is-size-7 mb-4">
                    @if ($showingAllSessions)
                        Toutes les détections sont affichées, seuil compris.
                        <a href="{{ route('my-vehicle.index', ['vehicule' => $vehicle->id, 'jours' => $days]) }}">Masquer les plus petites</a>
                    @else
                        {{ $hiddenSessions }} détection(s) sous
                        {{ (int) \App\Services\PendingTelemetryCharges::MIN_KWH }} kWh masquée(s) —
                        presque toujours de la récupération au freinage prise pour une charge.
                        <a href="{{ route('my-vehicle.index', ['vehicule' => $vehicle->id, 'jours' => $days, 'toutes' => 1]) }}">Tout afficher</a>
                    @endif
                </p>
            @endif

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
                                @php
                                    $ignoredHere = isset($ignoredCharges[$vehicle->id.'|'.$session['started_at']->format('Y-m-d H:i:s')]);
                                @endphp
                                <tr class="{{ $ignoredHere ? 'has-text-grey-light' : '' }}">
                                    <td>
                                        {{ $session['started_at']->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                        @if ($session['in_progress'])
                                            <span class="tag is-success is-light ml-1">en cours</span>
                                        @elseif ($session['measured'])
                                            <span class="tag is-primary is-light ml-1"
                                                  title="Session publiée par le boîtier OBD : énergie relevée au compteur de la batterie, pas déduite du niveau de charge.">mesurée</span>
                                        @elseif ($session['inferred'])
                                            <span class="tag is-warning is-light ml-1"
                                                  title="Aucun relevé pendant la charge : elle est déduite d'un niveau qui a monté alors que le compteur kilométrique n'avait pas bougé.">déduite</span>
                                        @endif
                                        @if ($ignoredHere)
                                            <span class="tag is-light ml-1"
                                                  title="Écartée des propositions de saisie sur la page Recharges.">écartée</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($session['inferred'])
                                            {{-- Ce n'est pas la duree du branchement mais celle du trou de
                                                 mesure : la charge s'est produite quelque part dedans. --}}
                                            <span class="has-text-grey"
                                                  title="Durée inconnue : la charge a eu lieu entre ces deux relevés.">
                                                entre {{ $session['started_at']->timezone(config('app.timezone'))->format('H:i') }}
                                                et {{ $session['ended_at']->timezone(config('app.timezone'))->format('H:i') }}
                                            </span>
                                        @else
                                            {{ intdiv($session['duration_minutes'], 60) }} h {{ str_pad((string) ($session['duration_minutes'] % 60), 2, '0', STR_PAD_LEFT) }}
                                            @if ($session['measured'] && $session['max_power_kw'])
                                                <span class="has-text-grey is-size-7">
                                                    {{ strtoupper($session['charging_type'] ?? '') }}
                                                    {{ str_replace('.', ',', (string) round($session['max_power_kw'], 1)) }} kW max
                                                </span>
                                            @elseif ($session['samples'] < 2)
                                                <span class="tag is-warning is-light ml-1" title="Un seul relevé pendant la charge : les bornes sont approximatives">1 relevé</span>
                                            @endif
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
                                                   'prefill_duration' => $session['inferred'] ? null : sprintf('%02d:%02d', intdiv($session['duration_minutes'], 60), $session['duration_minutes'] % 60),
                                                   'prefill_telemetry_start' => $session['started_at']->format('Y-m-d H:i:s'),
                                                   'prefill_lat' => $session['lat'],
                                                   'prefill_lon' => $session['lon'],
                                               ]) }}">
                                                Pré-remplir
                                            </a>
                                        @endif
                                        @if (! $session['in_progress'])
                                            <form method="POST"
                                                  action="{{ $ignoredHere ? route('detected-charges.restore') : route('detected-charges.ignore') }}"
                                                  class="is-inline">
                                                @csrf
                                                <input type="hidden" name="vehicle_id" value="{{ $vehicle->id }}">
                                                <input type="hidden" name="started_at" value="{{ $session['started_at']->format('Y-m-d H:i:s') }}">
                                                <button type="submit" class="button is-small is-light"
                                                        title="{{ $ignoredHere ? 'La reproposer à la saisie sur la page Recharges.' : 'Ne plus la proposer à la saisie sur la page Recharges.' }}">
                                                    {{ $ignoredHere ? 'Rétablir' : 'Écarter' }}
                                                </button>
                                            </form>
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
            Données publiées par le boîtier OBD sur le broker MQTT de la maison, sans intermédiaire.
            Elles n'arrivent que téléphone présent dans la voiture et XPCarData en marche : hors de ces
            moments, la page montre le dernier état connu, pas l'état actuel.
        </p>
    @endif
@endsection

@push('scripts')
    @vite('resources/js/telemetry.js')
@endpush
