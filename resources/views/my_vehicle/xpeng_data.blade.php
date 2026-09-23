@extends('layouts.app')

@section('title', 'Données Xpeng')

@section('content')
    <h1 class="title">Données Xpeng</h1>
    <p class="subtitle is-6">
        API officielle du constructeur (<code>/oauth2/queryData</code>), distincte du boîtier OBD.
        Elle ne renvoie pas de télémétrie en direct : un fichier d'export est récupéré une fois par
        jour par une tâche planifiée, jamais depuis cette page — le quota Xpeng (5 demandes/24h) est
        trop serré pour le risquer sur une simple visite.
    </p>

    @if ($derniereDonnee)
        <p class="has-text-grey is-size-7 mb-4">
            Dernière donnée disponible : {{ $derniereDonnee->translatedFormat('d/m/Y à H:i') }}
            ({{ $derniereDonnee->diffForHumans() }}).
        </p>
    @endif

    @if (! empty($mesures))
        <p class="has-text-grey is-size-7 mb-4">
            {{ $releves->count() }} relevé(s), agrégés à la minute, sur les 30 derniers jours.
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
    @endif

    @if (! empty($recharges))
        <h2 class="title is-5 mt-5">Recharges détectées</h2>
        <p class="has-text-grey is-size-7 mb-2">
            Détectées à partir des relevés (pas d'indicateur de charge direct dans l'export
            constructeur) : à l'arrêt (0 km/h), avec puissance de charge positive et/ou SoC en
            hausse par rapport à la minute précédente.
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
                        <td>{{ $recharge['debut']->translatedFormat('d/m/Y H:i') }}</td>
                        <td>{{ $recharge['fin']->translatedFormat('d/m/Y H:i') }}</td>
                        <td>{{ $recharge['duree_minutes'] }} min</td>
                        <td>
                            {{ $recharge['soc_debut'] !== null ? round($recharge['soc_debut'], 1) : '?' }} %
                            → {{ $recharge['soc_fin'] !== null ? round($recharge['soc_fin'], 1) : '?' }} %
                        </td>
                        <td>{{ round($recharge['puissance_max_kw'], 1) }} kW</td>
                        <td>{{ round($recharge['energie_kwh'], 1) }} kWh</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (! $dernier)
        <div class="notification is-info is-light">
            Aucune synchronisation automatique pour l'instant. La première aura lieu à la prochaine
            exécution planifiée (<code>xpeng:sync</code>, une fois par jour).
        </div>
    @elseif ($dernier->statut === 'ok')
        <div class="notification is-success is-light">
            Dernière synchronisation réussie le {{ $dernier->requested_at->translatedFormat('d F Y à H:i') }}.
            @if ($dernier->chemin_fichier)
                <a href="{{ route('my-vehicle.xpeng.telecharger', $dernier) }}">Télécharger le fichier brut</a>
            @endif
        </div>
    @elseif ($dernier->statut === 'en_cours')
        <div class="notification is-warning is-light">
            Synchronisation en cours depuis {{ $dernier->requested_at->diffForHumans() }}.
        </div>
    @else
        <div class="notification is-danger is-light">
            Dernière synchronisation en échec le {{ $dernier->requested_at->translatedFormat('d F Y à H:i') }}
            @if ($dernier->erreur)
                : {{ $dernier->erreur }}
            @endif
        </div>
    @endif

    @if ($canSync)
        <form method="POST" action="{{ route('my-vehicle.xpeng.synchroniser') }}" class="mb-2">
            @csrf
            <button type="submit" class="button is-link is-light"
                @disabled($soumissions24h >= $quota24h || $dernier?->statut === 'en_cours')>
                Lancer la récupération des données
            </button>
        </form>
        <p class="has-text-grey is-size-7">
            {{ $soumissions24h }}/{{ $quota24h }} demande(s) Xpeng sur les dernières 24 h — le quota est commun
            au passage planifié (6h15, puis 8h15 en cas d'échec) et à ce bouton. Dure en général moins de deux
            minutes, en arrière-plan : rechargez la page pour voir le résultat.
        </p>
    @endif

    <h2 class="title is-5 mt-5">Déposer un export manuellement</h2>
    <p class="has-text-grey is-size-7 mb-2">
        En attendant que la synchronisation automatique fonctionne (<code>appId</code>/<code>appSecret</code>),
        un export téléchargé à la main sur le portail Xpeng — un .zip.
    </p>
    <form method="POST" action="{{ route('my-vehicle.xpeng.importer') }}" enctype="multipart/form-data" class="field has-addons">
        @csrf
        <div class="control">
            <input class="input" type="file" name="fichier" accept=".zip" required>
        </div>
        <div class="control">
            <button type="submit" class="button is-primary">Importer</button>
        </div>
    </form>
    @error('fichier')
        <p class="help is-danger">{{ $message }}</p>
    @enderror

    @if ($exports->isNotEmpty())
        <h2 class="title is-5 mt-5">Historique</h2>
        <table class="table is-fullwidth is-striped">
            <thead>
                <tr>
                    <th>Demandée le</th>
                    <th>Statut</th>
                    <th>Détail</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($exports as $export)
                    <tr>
                        <td>{{ $export->requested_at->translatedFormat('d/m/Y H:i') }}</td>
                        <td>
                            @if ($export->statut === 'ok')
                                <span class="tag is-success is-light">Réussie</span>
                            @elseif ($export->statut === 'en_cours')
                                <span class="tag is-warning is-light">En cours</span>
                            @else
                                <span class="tag is-danger is-light">Échec</span>
                            @endif
                        </td>
                        <td>
                            @if ($export->statut === 'ok' && $export->chemin_fichier)
                                <a href="{{ route('my-vehicle.xpeng.telecharger', $export) }}">fichier</a>
                            @else
                                {{ $export->erreur }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Meme module que Statistiques OBD : generique (lit data-labels/-values/
         -unit sur canvas.obd-chart), pas d'utilite a le dupliquer ici. --}}
    @vite('resources/js/obd-stats.js')
@endsection
