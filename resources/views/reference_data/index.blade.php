@extends('layouts.app')

@section('title', 'Administration')

@section('content')
    <h1 class="title">Administration</h1>

    <div class="columns">
        <div class="column is-3">
            <h2 class="title is-4">Véhicules</h2>

            <div class="box">
                <form method="POST" action="{{ route('reference-data.vehicles.store') }}">
                    @csrf
                    <div class="field has-addons">
                        <div class="control is-expanded">
                            <input class="input" type="text" name="name" placeholder="Nom du véhicule" required>
                        </div>
                        <div class="control">
                            <button type="submit" class="button is-primary">Ajouter</button>
                        </div>
                    </div>
                    <div class="field">
                        <label class="checkbox">
                            <input type="checkbox" name="is_default" value="1">
                            Définir par défaut
                        </label>
                    </div>
                </form>
            </div>

            <div class="table-container">
                <table class="table is-fullwidth is-striped">
                    <tbody>
                        @forelse ($vehicles as $vehicle)
                            <tr>
                                <td>
                                    <form method="POST" action="{{ route('reference-data.vehicles.update', $vehicle) }}">
                                        @csrf
                                        @method('PUT')
                                        <div class="field has-addons mb-1">
                                            <div class="control is-expanded">
                                                <input class="input" type="text" name="name" value="{{ $vehicle->name }}" required>
                                            </div>
                                            <div class="control">
                                                <button type="submit" class="button is-info is-light">Enregistrer</button>
                                            </div>
                                        </div>
                                        <div class="field">
                                            <label class="checkbox">
                                                <input type="checkbox" name="is_default" value="1" @checked($vehicle->is_default)>
                                                Par défaut {{ $vehicle->is_default ? '(actuel)' : '' }}
                                            </label>
                                        </div>
                                    </form>
                                </td>
                                <td class="is-vcentered">
                                    <form method="POST" action="{{ route('reference-data.vehicles.destroy', $vehicle) }}" onsubmit="return confirm('Supprimer ce véhicule ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="button is-small is-danger is-light">Suppr.</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="has-text-grey">Aucun véhicule pour l'instant.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="column is-3">
            <h2 class="title is-4">Localisations</h2>

            <div class="box">
                <form method="POST" action="{{ route('reference-data.locations.store') }}" class="field has-addons">
                    @csrf
                    <div class="control is-expanded">
                        <input class="input" type="text" name="name" placeholder="Nom de la localisation" required>
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

        <div class="column is-3">
            <h2 class="title is-4">Fournisseurs de bornes</h2>

            <div class="box">
                <form method="POST" action="{{ route('reference-data.providers.store') }}" class="field has-addons">
                    @csrf
                    <div class="control is-expanded">
                        <input class="input" type="text" name="name" placeholder="Nom du fournisseur" required>
                    </div>
                    <div class="control">
                        <button type="submit" class="button is-primary">Ajouter</button>
                    </div>
                </form>
            </div>

            <div class="table-container">
                <table class="table is-fullwidth is-striped">
                    <tbody>
                        @forelse ($providers as $provider)
                            <tr>
                                <td>
                                    <form method="POST" action="{{ route('reference-data.providers.update', $provider) }}" class="field has-addons mb-0">
                                        @csrf
                                        @method('PUT')
                                        <div class="control is-expanded">
                                            <input class="input" type="text" name="name" value="{{ $provider->name }}" required>
                                        </div>
                                        <div class="control">
                                            <button type="submit" class="button is-info is-light">Enregistrer</button>
                                        </div>
                                    </form>
                                </td>
                                <td class="is-vcentered">
                                    <form method="POST" action="{{ route('reference-data.providers.destroy', $provider) }}" onsubmit="return confirm('Supprimer ce fournisseur ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="button is-small is-danger is-light">Suppr.</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="has-text-grey">Aucun fournisseur pour l'instant.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="column is-3">
            <h2 class="title is-4">Puissances de bornes (kW)</h2>

            <div class="box">
                <form method="POST" action="{{ route('reference-data.power-ratings.store') }}" class="field has-addons">
                    @csrf
                    <div class="control is-expanded">
                        <input class="input" type="number" step="0.01" min="0" name="kw" placeholder="Puissance en kW" required>
                    </div>
                    <div class="control">
                        <button type="submit" class="button is-primary">Ajouter</button>
                    </div>
                </form>
            </div>

            <div class="table-container">
                <table class="table is-fullwidth is-striped">
                    <tbody>
                        @forelse ($powerRatings as $powerRating)
                            <tr>
                                <td>
                                    <form method="POST" action="{{ route('reference-data.power-ratings.update', $powerRating) }}" class="field has-addons mb-0">
                                        @csrf
                                        @method('PUT')
                                        <div class="control is-expanded">
                                            <input class="input" type="number" step="0.01" min="0" name="kw" value="{{ $powerRating->kw }}" required>
                                        </div>
                                        <div class="control">
                                            <button type="submit" class="button is-info is-light">Enregistrer</button>
                                        </div>
                                    </form>
                                </td>
                                <td class="is-vcentered">
                                    <form method="POST" action="{{ route('reference-data.power-ratings.destroy', $powerRating) }}" onsubmit="return confirm('Supprimer cette puissance ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="button is-small is-danger is-light">Suppr.</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="has-text-grey">Aucune puissance pour l'instant.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
