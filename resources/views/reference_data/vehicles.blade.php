@extends('layouts.app')

@section('title', 'Administration — Véhicules')

@section('content')
    <a href="{{ route('reference-data.index') }}" class="is-size-7">&larr; Administration</a>
    <h1 class="title mt-2">Véhicules</h1>

    <div class="columns">
        <div class="column is-10">
            <div class="box">
                <form method="POST" action="{{ route('reference-data.vehicles.store') }}">
                    @csrf
                    <div class="columns is-multiline is-vcentered">
                        <div class="column is-3">
                            <label class="label is-small">Nom</label>
                            <input class="input" type="text" name="name" placeholder="Nom du véhicule" required>
                        </div>
                        <div class="column is-3">
                            <label class="label is-small">Courbe de recharge</label>
                            <div class="select is-fullwidth">
                                <select name="charging_curve">
                                    <option value="">— aucune —</option>
                                    @foreach ($curves as $c)
                                        <option value="{{ $c['slug'] }}">{{ $c['name'] }} ({{ $c['model_year'] }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="column is-3">
                            <label class="label is-small">Token ABRP</label>
                            <input class="input" type="text" name="abrp_token" placeholder="token télémétrie">
                        </div>
                        <div class="column is-2">
                            <label class="label is-small">Conso. (kWh/100km)</label>
                            <input class="input" type="number" step="0.1" min="0" name="kwh_per_100km" placeholder="ex. 15">
                        </div>
                        <div class="column is-2">
                            <label class="label is-small">Équiv. essence (L/100km)</label>
                            <input class="input" type="number" step="0.1" min="0" name="essence_l_per_100km" placeholder="ex. 6">
                        </div>
                        <div class="column is-2">
                            <label class="label is-small">Équiv. diesel (L/100km)</label>
                            <input class="input" type="number" step="0.1" min="0" name="diesel_l_per_100km" placeholder="ex. 6">
                        </div>
                        <div class="column is-2">
                            <label class="checkbox">
                                <input type="checkbox" name="is_default" value="1">
                                Par défaut
                            </label>
                            <button type="submit" class="button is-primary is-fullwidth mt-2">Ajouter</button>
                        </div>
                    </div>
                </form>
            </div>

            <p class="is-size-7 has-text-grey mb-3">
                Ces valeurs alimentent l'équivalent essence/diesel du dashboard et de l'historique. Aucune valeur par défaut n'est appliquée :
                un véhicule laissé sans consommation renseignée n'aura simplement pas d'équivalent calculé pour ses recharges.
                La courbe de recharge associée alimente la page
                <a href="{{ route('charging-curves.index') }}">Courbe de recharge</a> ; le véhicule par défaut y est affiché en premier.
                Le <strong>token ABRP</strong> est facultatif : renseigné, il permet de récupérer automatiquement le niveau de charge
                du véhicule (Réglages &rarr; Car model &rarr; le véhicule &rarr; Live data &rarr; Generic dans A Better Routeplanner).
                Il faut aussi que la clé <code>ABRP_API_KEY</code> soit présente dans le <code>.env</code>.
            </p>

            <div class="table-container">
                <table class="table is-fullwidth is-striped">
                    <tbody>
                        @forelse ($vehicles as $vehicle)
                            <tr>
                                <td>
                                    <form method="POST" action="{{ route('reference-data.vehicles.update', $vehicle) }}">
                                        @csrf
                                        @method('PUT')
                                        <div class="columns is-multiline is-vcentered mb-0">
                                            <div class="column is-3">
                                                <input class="input" type="text" name="name" value="{{ $vehicle->name }}" required>
                                            </div>
                                            <div class="column is-3">
                                                <div class="select is-fullwidth">
                                                    <select name="charging_curve">
                                                        <option value="">— aucune courbe —</option>
                                                        @foreach ($curves as $c)
                                                            <option value="{{ $c['slug'] }}" @selected($vehicle->charging_curve === $c['slug'])>{{ $c['name'] }} ({{ $c['model_year'] }})</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="column is-3">
                                                <input class="input" type="text" name="abrp_token" value="{{ $vehicle->abrp_token }}" placeholder="token ABRP">
                                            </div>
                                            <div class="column is-2">
                                                <input class="input" type="number" step="0.1" min="0" name="kwh_per_100km" value="{{ $vehicle->kwh_per_100km }}" placeholder="ex. 15">
                                            </div>
                                            <div class="column is-2">
                                                <input class="input" type="number" step="0.1" min="0" name="essence_l_per_100km" value="{{ $vehicle->essence_l_per_100km }}" placeholder="ex. 6">
                                            </div>
                                            <div class="column is-2">
                                                <input class="input" type="number" step="0.1" min="0" name="diesel_l_per_100km" value="{{ $vehicle->diesel_l_per_100km }}" placeholder="ex. 6">
                                            </div>
                                            <div class="column is-2">
                                                <label class="checkbox">
                                                    <input type="checkbox" name="is_default" value="1" @checked($vehicle->is_default)>
                                                    Par défaut {{ $vehicle->is_default ? '(actuel)' : '' }}
                                                </label>
                                                <button type="submit" class="button is-info is-light is-fullwidth mt-2">Enregistrer</button>
                                            </div>
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
    </div>
@endsection
