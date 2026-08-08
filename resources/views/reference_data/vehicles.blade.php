@extends('layouts.app')

@section('title', 'Administration — Véhicules')

@section('content')
    <a href="{{ route('reference-data.index') }}" class="is-size-7">&larr; Administration</a>
    <h1 class="title mt-2">Véhicules</h1>

    <div class="columns">
        <div class="column is-6">
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
    </div>
@endsection
