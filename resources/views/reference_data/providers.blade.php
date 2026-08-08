@extends('layouts.app')

@section('title', 'Administration — Fournisseurs')

@section('content')
    <a href="{{ route('reference-data.index') }}" class="is-size-7">&larr; Administration</a>
    <h1 class="title mt-2">Fournisseurs de bornes</h1>

    <div class="columns">
        <div class="column is-6">
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
    </div>
@endsection
