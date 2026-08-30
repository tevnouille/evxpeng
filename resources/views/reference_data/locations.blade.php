@extends('layouts.app')

@section('title', 'Administration — Localisations')

@section('content')
    <a href="{{ route('reference-data.index') }}" class="is-size-7">&larr; Administration</a>
    <h1 class="title mt-2">Localisation des bornes</h1>

    <p class="is-size-7 has-text-grey mb-4">
        Les coordonnées sont facultatives, mais c'est elles qui permettent de reconnaître une borne à partir
        de la position remontée par la télémétrie : une recharge détectée à moins de 400 m d'une localisation
        connue pré-remplit automatiquement le lieu, ainsi que le fournisseur et la puissance les plus souvent
        saisis à cet endroit.
    </p>

    <div class="columns">
        <div class="column is-9">
            <div class="box">
                <form method="POST" action="{{ route('reference-data.locations.store') }}" class="field has-addons">
                    @csrf
                    <div class="control is-expanded">
                        <input class="input" type="text" name="name" placeholder="Nom de la localisation" required>
                        <div class="field is-grouped mt-2">
                            <p class="control is-expanded">
                                <input class="input is-small" type="number" step="0.0000001" name="latitude" placeholder="Latitude (ex. 48.5851299)">
                            </p>
                            <p class="control is-expanded">
                                <input class="input is-small" type="number" step="0.0000001" name="longitude" placeholder="Longitude (ex. 2.4496454)">
                            </p>
                        </div>
                    </div>
                    <div class="control">
                        <button type="submit" class="button is-primary">Ajouter</button>
                    </div>
                </form>
            </div>

            <div class="table-container">
                <table class="table is-fullwidth is-striped">
                    <tbody>
                        @forelse ($locations as $location)
                            <tr>
                                <td>
                                    <form method="POST" action="{{ route('reference-data.locations.update', $location) }}" class="field has-addons mb-0">
                                        @csrf
                                        @method('PUT')
                                        <div class="control is-expanded">
                                            <input class="input" type="text" name="name" value="{{ $location->name }}" required>
                                            <div class="field is-grouped mt-2">
                                                <p class="control is-expanded">
                                                    <input class="input is-small" type="number" step="0.0000001" name="latitude" value="{{ $location->latitude }}" placeholder="Latitude">
                                                </p>
                                                <p class="control is-expanded">
                                                    <input class="input is-small" type="number" step="0.0000001" name="longitude" value="{{ $location->longitude }}" placeholder="Longitude">
                                                </p>
                                            </div>
                                        </div>
                                        <div class="control">
                                            <button type="submit" class="button is-info is-light">Enregistrer</button>
                                        </div>
                                    </form>
                                </td>
                                <td class="is-vcentered">
                                    <form method="POST" action="{{ route('reference-data.locations.destroy', $location) }}" onsubmit="return confirm('Supprimer cette localisation ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="button is-small is-danger is-light">Suppr.</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="has-text-grey">Aucune localisation pour l'instant.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
