@extends('layouts.app')

@section('title', 'Données Ford')

@section('content')
    <h1 class="title">Données Ford</h1>
    @if ($garage)
        <p class="subtitle is-5 has-text-grey">
            {{ $garage['nickName'] ?? $garage['vehicleType'] ?? '' }}
            @if (! empty($garage['nickName']) && ! empty($garage['vehicleType']))
                <span class="has-text-grey-light">— {{ $garage['vehicleType'] }}</span>
            @endif
        </p>
    @endif
    <p class="subtitle is-6">
        API officielle du constructeur (FordConnect Query), distincte du boîtier OBD. Contrairement à
        Xpeng, elle répond l'état courant à chaque appel : une tâche planifiée (<code>ford:sync</code>)
        enregistre un relevé toutes les 30 minutes. La voiture ne remonte rien tant qu'elle est à
        l'arrêt : Ford renvoie alors le dernier relevé connu.
    </p>

    @if (! $dernierJeton)
        <div class="notification is-warning is-light">
            Aucune autorisation Ford enregistrée.
            @if ($isAdmin)
                <a href="{{ route('my-vehicle.ford.authorize') }}">Autoriser l'accès</a>
                (nécessite de se connecter avec le compte Ford/FordPass propriétaire du véhicule).
            @else
                L'administrateur doit la renouveler.
            @endif
        </div>
    @else
        <form method="POST" action="{{ route('my-vehicle.ford.synchroniser') }}" class="mb-2 is-inline-block mr-2">
            @csrf
            <button type="submit" class="button is-link is-light">Lancer la récupération des données</button>
        </form>
        @if ($isAdmin)
            {{-- Reautoriser reste possible meme avec un jeton deja enregistre --
                 par exemple pour redemander des scopes apres un changement du
                 parametre envoye a l'autorisation (voir FordAuthController). --}}
            <a href="{{ route('my-vehicle.ford.authorize') }}" class="button is-light mb-2">Réautoriser Ford</a>
        @endif
    @endif

    @if ($imageUrl)
        <figure class="image mb-4" style="max-width: 480px;">
            <img src="{{ $imageUrl }}" alt="Photo du véhicule (cache constructeur Ford)">
        </figure>
    @endif

    @if ($dernier)
        @if ($releveAncien)
            <div class="notification is-warning is-light">
                <strong>Données anciennes :</strong> la voiture n'a rien remonté à Ford depuis le
                {{ $dernier->recorded_at->timezone(config('app.timezone'))->translatedFormat('d/m à H:i') }}
                ({{ $dernier->recorded_at->diffForHumans() }}).
                Elle ne transmet rien tant qu'elle est à l'arrêt : les valeurs ci-dessous sont celles de ce
                moment-là, pas l'état actuel.
            </div>
        @endif

        @php
            $statutsPrise = [
                'CONNECTED' => 'Branchée',
                'DISCONNECTED' => 'Débranchée',
            ];
            $tuiles = [
                'xev_soc' => ['Batterie de traction (SoC)', $dernier->xev_soc !== null ? rtrim(rtrim(number_format($dernier->xev_soc, 1, ',', ' '), '0'), ',') . ' %' : '—'],
                'xev_range_km' => ['Autonomie estimée (VE)', $dernier->xev_range_km !== null ? number_format($dernier->xev_range_km, 0, ',', ' ') . ' km' : '—'],
                'plug_status' => ['Prise', $statutsPrise[$dernier->plug_status] ?? $dernier->plug_status ?? '—'],
                'soc' => ['Batterie 12V (charge)', $dernier->soc !== null ? rtrim(rtrim(number_format($dernier->soc, 1, ',', ' '), '0'), ',') . ' %' : '—'],
                'odometre_km' => ['Kilométrage', $dernier->odometre_km !== null ? number_format($dernier->odometre_km, 0, ',', ' ') . ' km' : '—'],
                'outside_temp_c' => ['Température extérieure', $dernier->outside_temp_c !== null ? str_replace('.', ',', (string) round($dernier->outside_temp_c, 1)) . ' °C' : '—'],
                'ignition_status' => ['Contact', $dernier->ignition_status ?? '—'],
            ];
        @endphp

        <div class="box">
            <div class="columns is-mobile is-multiline mb-1">
                @foreach ($tuiles as $colonne => [$libelle, $valeur])
                    <div class="column">
                        <p class="heading">{{ $libelle }}</p>
                        <p class="title is-4 {{ isset($valeursAnciennes[$colonne]) ? 'has-text-grey' : '' }}">{{ $valeur }}</p>
                        {{-- Chaque valeur a son propre horodatage chez Ford, parfois
                             plus ancien que le releve global : dit ici, valeur par
                             valeur (seuil : FordDataController::ANCIENNETE_MINUTES). --}}
                        @if (isset($valeursAnciennes[$colonne]))
                            <p class="is-size-7 has-text-warning-dark"
                               title="Mise à jour par la voiture le {{ $valeursAnciennes[$colonne]->translatedFormat('d/m/Y à H:i') }}">
                                ⚠ valeur de {{ $valeursAnciennes[$colonne]->isToday() ? $valeursAnciennes[$colonne]->format('H:i') : $valeursAnciennes[$colonne]->translatedFormat('d/m H:i') }}
                                ({{ $valeursAnciennes[$colonne]->diffForHumans() }})
                            </p>
                        @endif
                    </div>
                @endforeach
                @if ($dernier->latitude !== null && $dernier->longitude !== null)
                    <div class="column">
                        <p class="heading">Position</p>
                        <p class="title is-6 {{ isset($valeursAnciennes['latitude']) ? 'has-text-grey' : '' }}">
                            <a href="https://www.google.com/maps/search/?api=1&query={{ $dernier->latitude }},{{ $dernier->longitude }}" target="_blank" rel="noopener">Maps</a>
                            ·
                            <a href="https://www.waze.com/ul?ll={{ $dernier->latitude }},{{ $dernier->longitude }}&amp;navigate=yes" target="_blank" rel="noopener">Waze</a>
                        </p>
                    </div>
                @endif
                <div class="column">
                    <p class="heading">Dernier relevé</p>
                    <p class="title is-5">{{ $dernier->recorded_at->diffForHumans() }}</p>
                    <p class="has-text-grey is-size-7">
                        {{ $dernier->recorded_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                    </p>
                </div>
            </div>
            @if ($capaciteBatterie)
                <p class="has-text-grey is-size-7">
                    Capacité de la batterie de traction estimée à 100 % : <strong>{{ str_replace('.', ',', (string) $capaciteBatterie) }} kWh</strong>
                    — moyenne glissante sur 7 jours (énergie restante ÷ SoC), pas une donnée Ford directe.
                </p>
            @endif
        </div>

        @if ($dernier->latitude !== null && $dernier->longitude !== null)
            <div id="ford-map" style="height: 260px; border-radius: 8px;" class="mb-4"
                 data-lat="{{ $dernier->latitude }}" data-lon="{{ $dernier->longitude }}"></div>
        @endif
    @endif

    @if (! empty($recharges))
        <h2 class="title is-5 mt-5">Recharges détectées</h2>
        <p class="has-text-grey is-size-7 mb-2">
            D'après <code>charge_display_status = IN_PROGRESS</code>, publié directement par Ford (contrairement à
            Xpeng, pas besoin de déduire la charge d'une vitesse nulle et d'une puissance positive). À reporter à la
            main dans <a href="{{ route('charging-sessions.index') }}">Recharges</a> si besoin : prix et borne n'y
            figurent pas.
        </p>
        <table class="table is-fullwidth is-striped">
            <thead>
                <tr>
                    <th>Début</th>
                    <th>Fin</th>
                    <th>Durée</th>
                    <th>Batterie</th>
                    <th>Puissance max</th>
                    <th>Énergie estimée</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recharges as $recharge)
                    <tr>
                        <td>{{ $recharge['debut']->timezone(config('app.timezone'))->translatedFormat('d/m/Y H:i') }}</td>
                        <td>{{ $recharge['fin']->timezone(config('app.timezone'))->translatedFormat('d/m/Y H:i') }}</td>
                        <td>{{ $recharge['duree_minutes'] }} min</td>
                        <td>
                            {{ $recharge['soc_debut'] !== null ? round($recharge['soc_debut'], 1) : '?' }} %
                            → {{ $recharge['soc_fin'] !== null ? round($recharge['soc_fin'], 1) : '?' }} %
                        </td>
                        <td>{{ $recharge['puissance_max_kw'] > 0 ? round($recharge['puissance_max_kw'], 1).' kW' : '—' }}</td>
                        <td>{{ $recharge['energie_kwh'] !== null ? round($recharge['energie_kwh'], 1).' kWh' : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (! empty($mesures))
        <p class="has-text-grey is-size-7 mb-4">
            {{ $releves->count() }} relevé(s) sur les 30 derniers jours.
        </p>
        <div class="columns is-multiline">
            @foreach ($mesures as $mesure)
                <div class="column is-6">
                    <p class="heading">
                        {{ $mesure['label'] }}
                        @if ($mesure['unit'])
                            <span class="has-text-grey">({{ $mesure['unit'] }})</span>
                        @endif
                        <span class="has-text-grey is-size-7">— {{ $mesure['count'] }} point(s)</span>
                    </p>
                    <canvas class="obd-chart" height="150"
                        data-labels='@json($mesure['labels'])'
                        data-values='@json($mesure['values'])'
                        data-unit="{{ $mesure['unit'] }}"></canvas>
                </div>
            @endforeach
        </div>
    @elseif ($dernierJeton)
        <div class="notification is-info is-light">
            Aucun relevé pour l'instant. Le premier arrivera à la prochaine exécution planifiée de
            <code>ford:sync</code>.
        </div>
    @endif

    @if ($dernierJeton)
        <p class="has-text-grey is-size-7 mt-5">
            Autorisation valable jusqu'au {{ $dernierJeton->expires_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
            — renouvelée automatiquement à chaque synchronisation, tant que le jeton de rafraîchissement
            reste valide (90 jours glissants).
        </p>
    @endif

    {{-- Meme module que Statistiques OBD / Donnees Xpeng : generique (lit
         data-labels/-values/-unit sur canvas.obd-chart), pas d'utilite a le
         dupliquer ici. --}}
    @vite('resources/js/obd-stats.js')
    @if ($dernier?->latitude !== null)
        @vite('resources/js/ford-map.js')
    @endif
@endsection
