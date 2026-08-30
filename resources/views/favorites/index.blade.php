@extends('layouts.app')

@section('title', 'Favoris')

@section('content')
    <h1 class="title">Trajets favoris</h1>

    @if ($stationCount === 0)
        <div class="notification is-warning is-light">
            La base des bornes est vide. Lancez l'import de la base nationale IRVE :
            <code>php artisan irve:import</code>.
        </div>
    @else
        <form method="POST" action="{{ route('favorites.store') }}" class="box">
            @csrf
            <h2 class="subtitle">Nouveau trajet</h2>

            <div class="columns is-multiline">
                <div class="column is-4">
                    <div class="field">
                        <label class="label" for="nom">Nom du trajet</label>
                        <div class="control">
                            <input class="input" type="text" id="nom" name="nom" required maxlength="120"
                                   value="{{ old('nom') }}" placeholder="Maison → Les Alpes">
                        </div>
                    </div>
                </div>

                <div class="column is-4">
                    <div class="field">
                        <label class="label" for="depart">Départ</label>
                        <div class="control" style="position: relative;">
                            <input class="input" type="text" id="depart" name="depart" required
                                   value="{{ old('depart') }}" placeholder="Villabé, une adresse, ou 48.5836, 2.4436"
                                   data-address-input autocomplete="off">
                            <input type="hidden" name="depart_lat" value="{{ old('depart_lat') }}">
                            <input type="hidden" name="depart_lon" value="{{ old('depart_lon') }}">
                            <div class="dropdown-content" data-address-results hidden
                                 style="position: absolute; z-index: 30; width: 100%; max-height: 16rem; overflow-y: auto;"></div>
                        </div>
                    </div>
                </div>

                <div class="column is-4">
                    <div class="field">
                        <label class="label" for="arrivee">Arrivée</label>
                        <div class="control" style="position: relative;">
                            <input class="input" type="text" id="arrivee" name="arrivee" required
                                   value="{{ old('arrivee') }}" placeholder="Chambéry, une adresse…"
                                   data-address-input autocomplete="off">
                            <input type="hidden" name="arrivee_lat" value="{{ old('arrivee_lat') }}">
                            <input type="hidden" name="arrivee_lon" value="{{ old('arrivee_lon') }}">
                            <div class="dropdown-content" data-address-results hidden
                                 style="position: absolute; z-index: 30; width: 100%; max-height: 16rem; overflow-y: auto;"></div>
                        </div>
                    </div>
                </div>

                <div class="column is-3">
                    <div class="field">
                        <label class="label" for="puissance_min">Puissance mini de borne</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select id="puissance_min" name="puissance_min">
                                    @foreach ($minPowers as $power)
                                        <option value="{{ $power }}" @selected((int) old('puissance_min', 150) === $power)>
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
                                   value="{{ old('detour', 5) }}">
                            <span class="icon is-small is-right">km</span>
                        </div>
                    </div>
                </div>

                <div class="column is-3 is-flex is-align-items-flex-end">
                    <div class="field">
                        <div class="control">
                            <button class="button is-link" type="submit">Tracer le trajet</button>
                        </div>
                    </div>
                </div>
            </div>

            <p class="help">
                Ces deux réglages restent modifiables ensuite&nbsp;: ils ne servent qu'à filtrer
                les bornes affichées sur la carte, l'itinéraire ne change pas.
            </p>
        </form>

        @if ($routes->isEmpty())
            <div class="notification is-info is-light">
                Aucun trajet enregistré. Créez-en un ci-dessus&nbsp;: la carte affichera toutes les bornes
                du parcours, et vous choisirez celles à retenir.
            </div>
        @else
            <div class="box">
                <div class="table-container">
                    <table class="table is-fullwidth is-striped is-hoverable">
                        <thead>
                            <tr>
                                <th>Trajet</th>
                                <th>Départ</th>
                                <th>Arrivée</th>
                                <th class="has-text-right">Distance</th>
                                <th class="has-text-right">Bornes retenues</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($routes as $route)
                                <tr>
                                    <td>
                                        <a href="{{ route('favorites.show', $route) }}"><strong>{{ $route->name }}</strong></a>
                                        @if ($route->copied_from)
                                            <br><span class="tag is-info is-light">copié de {{ $route->copied_from }}</span>
                                        @endif
                                    </td>
                                    <td class="is-size-7">{{ $route->from_label }}</td>
                                    <td class="is-size-7">{{ $route->to_label }}</td>
                                    <td class="has-text-right">{{ number_format($route->distance_km, 0, ',', ' ') }} km</td>
                                    <td class="has-text-right">{{ $route->stations_count }}</td>
                                    <td class="has-text-right">
                                        <div class="buttons is-right are-small">
                                            <a class="button is-link is-light" href="{{ route('favorites.show', $route) }}">Ouvrir</a>
                                            <a class="button is-light" href="{{ route('favorites.show', $route) }}#copier">Copier</a>
                                            <form method="POST" action="{{ route('favorites.destroy', $route) }}"
                                                  onsubmit="return confirm('Supprimer le trajet « {{ $route->name }} » et ses bornes ?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="button is-danger is-light" type="submit">Supprimer</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
@endsection

@push('scripts')
    @vite('resources/js/favorites.js')
@endpush
