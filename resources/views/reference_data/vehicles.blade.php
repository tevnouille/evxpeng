@extends('layouts.app')

@section('title', 'Administration — Véhicules')

@section('content')
    <a href="{{ route('reference-data.index') }}" class="is-size-7">&larr; Administration</a>
    <h1 class="title mt-2">Véhicules</h1>

    {{-- Pleine largeur, et colonnes qui totalisent douze par ligne : la fiche
         compte sept champs, un gabarit etroit les repliait n'importe comment. --}}
    <div class="box">
        <h2 class="title is-6">Ajouter un véhicule</h2>
        <form method="POST" action="{{ route('reference-data.vehicles.store') }}">
            @csrf
            <div class="columns is-multiline">
                <div class="column is-4">
                    <div class="field">
                        <label class="label is-small">Nom</label>
                        <div class="control">
                            <input class="input" type="text" name="name" placeholder="Nom du véhicule" required>
                        </div>
                    </div>
                </div>
                <div class="column is-5">
                    <div class="field">
                        <label class="label is-small">Courbe de recharge</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="charging_curve">
                                    <option value="">— aucune —</option>
                                    @foreach ($curves as $c)
                                        <option value="{{ $c['slug'] }}">{{ $c['name'] }} ({{ $c['model_year'] }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="column is-2">
                    <div class="field">
                        <label class="label is-small">Token ABRP</label>
                        <div class="control">
                            <input class="input" type="text" name="abrp_token" placeholder="token télémétrie">
                        </div>
                    </div>
                </div>

                <div class="column is-1">
                    <div class="field">
                        <label class="label is-small">Ident. MQTT</label>
                        <div class="control">
                            <input class="input" type="text" name="mqtt_client_id" placeholder="ex. xpengG6">
                        </div>
                    </div>
                </div>

                <div class="column is-3">
                    <div class="field">
                        <label class="label is-small">Consommation (kWh/100 km)</label>
                        <div class="control">
                            <input class="input" type="number" step="0.1" min="0" name="kwh_per_100km" placeholder="ex. 15">
                        </div>
                    </div>
                </div>
                <div class="column is-3">
                    <div class="field">
                        <label class="label is-small">Équivalent essence (L/100 km)</label>
                        <div class="control">
                            <input class="input" type="number" step="0.1" min="0" name="essence_l_per_100km" placeholder="ex. 6">
                        </div>
                    </div>
                </div>
                <div class="column is-3">
                    <div class="field">
                        <label class="label is-small">Équivalent diesel (L/100 km)</label>
                        <div class="control">
                            <input class="input" type="number" step="0.1" min="0" name="diesel_l_per_100km" placeholder="ex. 6">
                        </div>
                    </div>
                </div>
                <div class="column is-3">
                    <div class="field">
                        <label class="label is-small">Véhicule par défaut</label>
                        <div class="control">
                            <label class="checkbox">
                                <input type="checkbox" name="is_default" value="1">
                                Proposé en premier
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="button is-primary is-fullwidth">Ajouter</button>
                </div>
            </div>
        </form>
    </div>

    <p class="is-size-7 has-text-grey mb-4">
        Ces valeurs alimentent l'équivalent essence/diesel du dashboard et de l'historique. Aucune valeur par défaut n'est appliquée :
        un véhicule laissé sans consommation renseignée n'aura simplement pas d'équivalent calculé pour ses recharges.
        La courbe de recharge associée alimente la page
        <a href="{{ route('charging-curves.index') }}">Courbe de recharge</a> ; le véhicule par défaut y est affiché en premier.
        Le <strong>token ABRP</strong> est facultatif : renseigné, il permet de récupérer automatiquement le niveau de charge
        du véhicule (Réglages &rarr; Car model &rarr; le véhicule &rarr; Live data &rarr; Generic dans A Better Routeplanner).
        Il faut aussi que la clé <code>ABRP_API_KEY</code> soit présente dans le <code>.env</code>.
    </p>

    {{-- Une boite par vehicule plutot qu'un tableau : chaque ligne portait deja
         un formulaire complet dans une seule cellule, ce que la mise en page
         d'un tableau ne sait pas presenter. --}}
    @forelse ($vehicles as $vehicle)
        <div class="box">
            <div class="level is-mobile mb-3">
                <div class="level-left">
                    <h2 class="title is-6 mb-0">
                        {{ $vehicle->name }}
                        @if ($vehicle->is_default)
                            <span class="tag is-info is-light ml-2">par défaut</span>
                        @endif
                        @if ($vehicle->abrp_token)
                            <span class="tag is-success is-light ml-1">télémétrie active</span>
                        @endif
                    </h2>
                </div>
                <div class="level-right">
                    <form method="POST" action="{{ route('reference-data.vehicles.destroy', $vehicle) }}"
                          onsubmit="return confirm('Supprimer {{ $vehicle->name }} ? Ses recharges et relevés le seront aussi.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button is-small is-danger is-light">Supprimer</button>
                    </form>
                </div>
            </div>

            <form method="POST" action="{{ route('reference-data.vehicles.update', $vehicle) }}">
                @csrf
                @method('PUT')
                <div class="columns is-multiline">
                    <div class="column is-4">
                        <div class="field">
                            <label class="label is-small">Nom</label>
                            <div class="control">
                                <input class="input" type="text" name="name" value="{{ $vehicle->name }}" required>
                            </div>
                        </div>
                    </div>
                    <div class="column is-5">
                        <div class="field">
                            <label class="label is-small">Courbe de recharge</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="charging_curve">
                                        <option value="">— aucune courbe —</option>
                                        @foreach ($curves as $c)
                                            <option value="{{ $c['slug'] }}" @selected($vehicle->charging_curve === $c['slug'])>{{ $c['name'] }} ({{ $c['model_year'] }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="column is-2">
                        <div class="field">
                            <label class="label is-small">Token ABRP</label>
                            <div class="control">
                                <input class="input" type="text" name="abrp_token" value="{{ $vehicle->abrp_token }}" placeholder="token télémétrie">
                            </div>
                        </div>
                    </div>

                    {{-- Identifiant que le boitier publie dans ses topics MQTT
                         (vehicles/{ident}/data) : sans lui ses messages ne se
                         rattachent a aucune voiture. --}}
                    <div class="column is-1">
                        <div class="field">
                            <label class="label is-small">Ident. MQTT</label>
                            <div class="control">
                                <input class="input" type="text" name="mqtt_client_id" value="{{ $vehicle->mqtt_client_id }}" placeholder="ex. xpengG6">
                            </div>
                        </div>
                    </div>

                    <div class="column is-3">
                        <div class="field">
                            <label class="label is-small">Consommation (kWh/100 km)</label>
                            <div class="control">
                                <input class="input" type="number" step="0.1" min="0" name="kwh_per_100km" value="{{ $vehicle->kwh_per_100km }}" placeholder="ex. 15">
                            </div>
                        </div>
                    </div>
                    <div class="column is-3">
                        <div class="field">
                            <label class="label is-small">Équivalent essence (L/100 km)</label>
                            <div class="control">
                                <input class="input" type="number" step="0.1" min="0" name="essence_l_per_100km" value="{{ $vehicle->essence_l_per_100km }}" placeholder="ex. 6">
                            </div>
                        </div>
                    </div>
                    <div class="column is-3">
                        <div class="field">
                            <label class="label is-small">Équivalent diesel (L/100 km)</label>
                            <div class="control">
                                <input class="input" type="number" step="0.1" min="0" name="diesel_l_per_100km" value="{{ $vehicle->diesel_l_per_100km }}" placeholder="ex. 6">
                            </div>
                        </div>
                    </div>
                    <div class="column is-3">
                        <div class="field">
                            <label class="label is-small">Véhicule par défaut</label>
                            <div class="control">
                                <label class="checkbox">
                                    <input type="checkbox" name="is_default" value="1" @checked($vehicle->is_default)>
                                    Proposé en premier
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="button is-info is-light is-fullwidth">Enregistrer</button>
                    </div>
                </div>
            </form>
        </div>
    @empty
        <div class="notification is-info is-light">Aucun véhicule pour l'instant.</div>
    @endforelse
@endsection
