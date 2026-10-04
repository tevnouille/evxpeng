<?php /** @var \Illuminate\Support\Collection<int, \App\Models\ChargingStationNote> $notes */ ?>
@extends('layouts.app')

@section('title', 'Notes sur les bornes')

@section('content')
    <h1 class="title">Notes sur les bornes</h1>

    <div class="box">
        <p class="mb-4">
            Une appréciation personnelle par borne — fiable, hors service, accès compliqué — affichée
            à côté de la borne dans le planificateur et les trajets favoris. Une seconde note sur la
            même borne remplace la précédente ; il n'y a pas d'historique par visite.
        </p>

        <form method="POST" action="{{ route('station-notes.store') }}" class="columns is-multiline">
            @csrf
            <div class="column is-6">
                <div class="field">
                    <label class="label" for="station-search">Borne</label>
                    <div class="control" style="position: relative;">
                        <input class="input" type="text" id="station-search"
                               placeholder="Nom, commune ou opérateur…" autocomplete="off" data-station-search>
                        <input type="hidden" name="station_id" data-station-id>
                        <div class="dropdown-content" data-suggestions hidden
                             style="position: absolute; z-index: 30; width: 100%; max-height: 16rem; overflow-y: auto;"></div>
                    </div>
                    <p class="help" data-station-help>Choisissez une borne dans la liste proposée.</p>
                </div>
            </div>
            <div class="column is-6">
                <div class="field">
                    <label class="label" for="note">Note</label>
                    <div class="control">
                        <textarea class="textarea" id="note" name="note" rows="2" required
                                  maxlength="2000" placeholder="Fiable, accès compliqué, souvent occupée…"></textarea>
                    </div>
                </div>
                <div class="control">
                    <button class="button is-link" type="submit">Enregistrer</button>
                </div>
            </div>
        </form>
    </div>

    @if ($notes->isEmpty())
        <div class="notification is-info is-light">Aucune note pour l'instant.</div>
    @else
        <div class="box">
            <div class="table-container">
                <table class="table is-fullwidth is-striped">
                    <thead>
                        <tr>
                            <th>Borne</th>
                            <th>Note</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($notes as $stationNote)
                            <tr>
                                <td>
                                    <strong>{{ $stationNote->station->name }}</strong>
                                    <br>
                                    <span class="has-text-grey is-size-7">
                                        {{ $stationNote->station->city ?: $stationNote->station->address }}
                                    </span>
                                </td>
                                <td style="white-space: pre-line;">{{ $stationNote->note }}</td>
                                <td class="has-text-right" style="min-width: 12rem;">
                                    <details>
                                        <summary class="button is-light is-small">Modifier</summary>
                                        <form method="POST" action="{{ route('station-notes.store') }}" class="mt-2">
                                            @csrf
                                            <input type="hidden" name="station_id" value="{{ $stationNote->charging_station_id }}">
                                            <div class="field">
                                                <div class="control">
                                                    <textarea class="textarea" name="note" rows="2" required
                                                              maxlength="2000">{{ $stationNote->note }}</textarea>
                                                </div>
                                            </div>
                                            <div class="buttons are-small">
                                                <button class="button is-link" type="submit">Enregistrer</button>
                                            </div>
                                        </form>
                                    </details>
                                    <form method="POST" action="{{ route('station-notes.destroy', $stationNote) }}"
                                          class="mt-2" onsubmit="return confirm('Supprimer cette note ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="button is-danger is-light is-small" type="submit">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    @vite('resources/js/station-notes.js')
@endpush
