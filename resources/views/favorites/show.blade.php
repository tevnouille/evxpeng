@extends('layouts.app')

@section('title', $route->name)

@section('content')
    <nav class="breadcrumb is-small" aria-label="breadcrumbs">
        <ul>
            <li><a href="{{ route('favorites.index') }}">Trajets favoris</a></li>
            <li class="is-active"><a href="#" aria-current="page">{{ $route->name }}</a></li>
        </ul>
    </nav>

    <h1 class="title">
        {{ $route->name }}
        @if ($route->copied_from)
            <span class="tag is-info is-light ml-2">copié de {{ $route->copied_from }}</span>
        @endif
    </h1>

    <div class="box">
        <div class="columns is-multiline">
            <div class="column is-6">
                <p class="heading">Départ</p>
                <p>{{ $route->from_label }}</p>
            </div>
            <div class="column is-6">
                <p class="heading">Arrivée</p>
                <p>{{ $route->to_label }}</p>
            </div>
            <div class="column is-3">
                <p class="heading">Distance</p>
                <p class="title is-5">{{ number_format($route->distance_km, 0, ',', ' ') }} km</p>
            </div>
            <div class="column is-3">
                <p class="heading">Conduite</p>
                <p class="title is-5">{{ \App\Support\Duration::human($route->duration_minutes) }}</p>
            </div>
            <div class="column is-3">
                <p class="heading">Bornes affichées</p>
                <p class="title is-5">{{ number_format(count($stations), 0, ',', ' ') }}</p>
            </div>
            <div class="column is-3 is-flex is-align-items-flex-end">
                <a class="button is-link is-light" target="_blank" rel="noopener"
                   href="{{ $route->googleMapsUrl() }}" data-route-google-link>
                    Itinéraire complet dans Google Maps
                </a>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('favorites.update', $route) }}" class="box">
        @csrf
        @method('PUT')
        <div class="columns is-multiline">
            <div class="column is-12">
                <div class="field">
                    <label class="label" for="nom">Nom du trajet</label>
                    <div class="control">
                        <input class="input" type="text" id="nom" name="nom" required maxlength="120" value="{{ $route->name }}">
                    </div>
                </div>
            </div>
            <div class="column is-12">
                @php
                    $chosenNetworks = $route->networks ?? [];
                    $available = $networks->reject(fn ($network) => in_array($network->network, $chosenNetworks, true));
                    $chosen = $networks->filter(fn ($network) => in_array($network->network, $chosenNetworks, true));
                @endphp

                <label class="label">Réseaux</label>
                <div class="columns is-vcentered network-picker">
                    <div class="column is-5">
                        <div class="control mb-2">
                            <input class="input is-small" type="search" data-network-filter
                                   placeholder="Filtrer : electra, ionity, total…" autocomplete="off"
                                   aria-controls="reseaux-disponibles">
                        </div>
                        <div class="select is-multiple is-fullwidth">
                            <select id="reseaux-disponibles" multiple size="9">
                                @foreach ($available as $network)
                                    <option value="{{ $network->network }}">{{ $network->network }} ({{ number_format($network->stations, 0, ',', ' ') }})</option>
                                @endforeach
                            </select>
                        </div>
                        <p class="help">Disponibles &mdash; <span data-network-count="disponibles">{{ $available->count() }}</span></p>
                    </div>

                    <div class="column is-2 has-text-centered">
                        <button class="button is-fullwidth mb-2" type="button" data-network-add
                                title="Ajouter au trajet">Ajouter &rarr;</button>
                        <button class="button is-fullwidth" type="button" data-network-remove
                                title="Retirer du trajet">&larr; Retirer</button>
                    </div>

                    <div class="column is-5">
                        <div class="select is-multiple is-fullwidth mt-5">
                            <select id="reseaux" name="reseaux[]" multiple size="9">
                                @foreach ($chosen as $network)
                                    <option value="{{ $network->network }}">{{ $network->network }} ({{ number_format($network->stations, 0, ',', ' ') }})</option>
                                @endforeach
                            </select>
                        </div>
                        <p class="help">
                            Retenus &mdash; <span data-network-count="retenus">{{ $chosen->count() }}</span>.
                            <span data-network-empty @if (! $chosen->isEmpty()) hidden @endif>
                                Aucun&nbsp;: toutes les bornes sont affichées.
                            </span>
                        </p>
                    </div>
                </div>
                <p class="help">
                    Sélectionnez à gauche puis « Ajouter », ou double-cliquez sur une ligne.
                </p>
            </div>

            <div class="column is-3">
                <div class="field">
                    <label class="label" for="puissance_min">Puissance mini de borne</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select id="puissance_min" name="puissance_min">
                                @foreach ($minPowers as $power)
                                    <option value="{{ $power }}" @selected((float) $power === $route->min_power_kw)>
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
                    <label class="label" for="detour">Détour max</label>
                    <div class="control has-icons-right">
                        <input class="input" type="number" id="detour" name="detour" min="1" max="30" step="1"
                               value="{{ (int) round($route->max_detour_km) }}">
                        <span class="icon is-small is-right">km</span>
                    </div>
                </div>
            </div>
            <div class="column is-2 is-flex is-align-items-flex-end">
                <div class="field">
                    <div class="control">
                        <button class="button is-link" type="submit">Appliquer</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="box">
        <div class="level is-mobile mb-2">
            <div class="level-left">
                <p class="is-size-7 has-text-grey">
                    Cliquez sur une borne pour l'ajouter au trajet. Les bornes retenues sont en jaune.
                </p>
            </div>
            <div class="level-right is-size-7">
                <span class="mr-3"><span class="tag" style="background:#f14668;color:#fff;">150 kW et +</span></span>
                <span class="mr-3"><span class="tag" style="background:#ff9d00;color:#fff;">100 – 149</span></span>
                <span class="mr-3"><span class="tag" style="background:#3e8ed0;color:#fff;">50 – 99</span></span>
                <span><span class="tag" style="background:#b5b5b5;color:#fff;">moins de 50</span></span>
            </div>
        </div>

        <div id="favorites-map" style="height: 520px;"
             data-endpoint="{{ route('favorites.stations.store', $route) }}"
             data-remove-endpoint="{{ url('favoris/'.$route->id.'/bornes') }}"
             data-geometry="{{ json_encode($route->geometry ?? []) }}"
             data-stations="{{ json_encode($stations) }}"
             data-from="{{ json_encode([$route->from_lat, $route->from_lon, $route->from_label]) }}"
             data-to="{{ json_encode([$route->to_lat, $route->to_lon, $route->to_label]) }}"
             data-favorites="{{ json_encode($route->stations->map(fn ($station) => [
                'id' => $station->id,
                'station_id' => $station->charging_station_id,
                'name' => $station->name,
                'network' => $station->network,
                'address' => $station->address,
                'city' => $station->city,
                'lat' => $station->lat,
                'lon' => $station->lon,
                'power_kw' => $station->power_kw,
                'km' => $station->km,
                'google_url' => $station->googleMapsUrl(),
                'waze_url' => $station->wazeUrl(),
             ])) }}"></div>
    </div>

    <div class="box">
        <h2 class="subtitle">Bornes retenues</h2>
        <div id="favorites-list"></div>
    </div>

    <div class="box" id="copier">
        <h2 class="subtitle">Copier ce trajet vers un autre compte</h2>

        @if ($others->isEmpty())
            <p class="has-text-grey">
                Aucun autre compte pour l'instant. La copie sera proposée ici dès qu'une autre
                personne se sera connectée.
            </p>
        @else
            <form method="POST" action="{{ route('favorites.copy', $route) }}">
                @csrf
                <p class="mb-3">
                    Chaque destinataire reçoit <strong>sa propre copie</strong>, bornes retenues comprises&nbsp;:
                    il peut la renommer, la compléter ou la supprimer sans que la vôtre bouge.
                </p>

                <div class="field">
                    @foreach ($others as $other)
                        <label class="checkbox mr-4">
                            <input type="checkbox" name="utilisateurs[]" value="{{ $other->id }}">
                            {{ $other->email }}
                        </label>
                    @endforeach
                </div>

                <div class="field mt-4">
                    <div class="control">
                        <button class="button is-link" type="submit">Copier</button>
                    </div>
                </div>
            </form>
        @endif
    </div>

    <p class="has-text-grey is-size-7">
        Bornes issues de la base nationale IRVE, filtrées à
        {{ (int) round($route->max_detour_km) }} km de l'itinéraire
        @if ($route->networks)
            et aux réseaux {{ implode(', ', $route->networks) }}
        @endif
        · Les libellés et adresses viennent telles quelles de la base&nbsp;:
        c'est la position sur la carte qui fait foi.
        Une borne retenue est recopiée dans le trajet&nbsp;: elle reste affichée même si elle disparaît
        d'un import ultérieur. Waze n'accepte qu'une destination à la fois, d'où un lien par borne.
    </p>
@endsection

@push('scripts')
    @vite('resources/js/favorites.js')
@endpush
