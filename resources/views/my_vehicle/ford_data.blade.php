@extends('layouts.app')

@section('title', 'Données Ford')

@section('content')
    <h1 class="title">Données Ford</h1>
    <p class="subtitle is-6">
        API officielle du constructeur (FordConnect Query), distincte du boîtier OBD. Contrairement à
        Xpeng, elle répond l'état courant à chaque appel : une tâche planifiée (<code>ford:sync</code>)
        enregistre un relevé toutes les 30 minutes. La voiture ne remonte rien tant qu'elle est à
        l'arrêt : Ford renvoie alors le dernier relevé connu.
    </p>

    @if (! $dernierJeton)
        <div class="notification is-warning is-light">
            Aucune autorisation Ford enregistrée. <a href="{{ route('my-vehicle.ford.authorize') }}">Autoriser l'accès</a>
            (nécessite de se connecter avec le compte Ford/FordPass propriétaire du véhicule).
        </div>
    @elseif ($isAdmin)
        <form method="POST" action="{{ route('my-vehicle.ford.synchroniser') }}" class="mb-4">
            @csrf
            <button type="submit" class="button is-link is-light">Lancer la récupération des données</button>
        </form>
    @endif

    @if ($imageUrl)
        <figure class="image mb-4" style="max-width: 480px;">
            <img src="{{ $imageUrl }}" alt="Photo du véhicule (cache constructeur Ford)">
        </figure>
    @endif

    @if ($dernier)
        <div class="box">
            <div class="columns is-mobile is-multiline mb-1">
                <div class="column">
                    <p class="heading">Batterie</p>
                    <p class="title is-4">{{ $dernier->soc !== null ? rtrim(rtrim(number_format($dernier->soc, 1, ',', ' '), '0'), ',') . ' %' : '—' }}</p>
                </div>
                <div class="column">
                    <p class="heading">Kilométrage</p>
                    <p class="title is-4">{{ $dernier->odometre_km !== null ? number_format($dernier->odometre_km, 0, ',', ' ') . ' km' : '—' }}</p>
                </div>
                <div class="column">
                    <p class="heading">Contact</p>
                    <p class="title is-4">{{ $dernier->ignition_status ?? '—' }}</p>
                </div>
                <div class="column">
                    <p class="heading">Dernier relevé</p>
                    <p class="title is-5">{{ $dernier->recorded_at->diffForHumans() }}</p>
                    <p class="has-text-grey is-size-7">
                        {{ $dernier->recorded_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                    </p>
                </div>
            </div>
        </div>
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
@endsection
