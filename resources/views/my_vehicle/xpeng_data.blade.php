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

    @if (! $dernier)
        <div class="notification is-info is-light">
            Aucune synchronisation pour l'instant. La première aura lieu à la prochaine exécution
            planifiée (<code>xpeng:sync</code>, une fois par jour).
        </div>
    @elseif ($dernier->statut === 'ok')
        <div class="notification is-success is-light">
            Dernière synchronisation réussie le {{ $dernier->requested_at->translatedFormat('d F Y à H:i') }}.
            @if ($dernier->chemin_fichier)
                <a href="{{ route('my-vehicle.xpeng.telecharger', $dernier) }}">Télécharger le fichier brut</a>
                — le format n'est pas encore documenté ici : cette page sera complétée avec de vrais
                graphiques une fois son contenu inspecté.
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
@endsection
