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
                <div class="column is-3">
                    <div class="field">
                        <label class="label is-small">Identifiant MQTT</label>
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
                </div>
            </div>

            <hr>
            <p class="label is-small mb-2">Amortissement face à une thermique équivalente</p>
            <div class="columns is-multiline">
                <div class="column is-3">
                    <div class="field">
                        <label class="label is-small">Prix d'achat (€)</label>
                        <div class="control">
                            <input class="input" type="number" step="1" min="0" name="purchase_price" placeholder="ex. 50000">
                        </div>
                    </div>
                </div>
                <div class="column is-3">
                    <div class="field">
                        <label class="label is-small">Date d'achat</label>
                        <div class="control">
                            <input class="input" type="date" name="purchase_date">
                        </div>
                    </div>
                </div>
                <div class="column is-3">
                    <div class="field">
                        <label class="label is-small">Kilométrage à l'achat</label>
                        <div class="control">
                            <input class="input" type="number" step="1" min="0" name="purchase_odometer_km" placeholder="0 si neuve">
                        </div>
                    </div>
                </div>
                <div class="column is-3">
                    <div class="field">
                        <label class="label is-small">Thermique de comparaison</label>
                        <div class="control">
                            <input class="input" type="text" name="thermal_equivalent_label" placeholder="ex. Škoda Kodiaq 2.0 TDI">
                        </div>
                    </div>
                </div>
                <div class="column is-3">
                    <div class="field">
                        <label class="label is-small">Prix thermique (€)</label>
                        <div class="control">
                            <input class="input" type="number" step="1" min="0" name="thermal_equivalent_price" placeholder="ex. 50000">
                        </div>
                    </div>
                </div>
                <div class="column is-2">
                    <div class="field">
                        <label class="label is-small">Entretien EV (€)</label>
                        <div class="control">
                            <input class="input" type="number" step="1" min="0" name="ev_maintenance_cost" placeholder="180">
                        </div>
                    </div>
                </div>
                <div class="column is-2">
                    <div class="field">
                        <label class="label is-small">…tous les (km)</label>
                        <div class="control">
                            <input class="input" type="number" step="1" min="1" name="ev_maintenance_interval_km" placeholder="20000">
                        </div>
                    </div>
                </div>
                <div class="column is-2">
                    <div class="field">
                        <label class="label is-small">Entretien thermique (€)</label>
                        <div class="control">
                            <input class="input" type="number" step="1" min="0" name="thermal_maintenance_cost" placeholder="350">
                        </div>
                    </div>
                </div>
                <div class="column is-3">
                    <div class="field">
                        <label class="label is-small">…tous les (km)</label>
                        <div class="control">
                            <input class="input" type="number" step="1" min="1" name="thermal_maintenance_interval_km" placeholder="25000">
                        </div>
                    </div>
                </div>
                <div class="column is-2 is-flex is-align-items-flex-end">
                    <div class="field">
                        <div class="control">
                            <button type="submit" class="button is-primary is-fullwidth">Ajouter</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <p class="is-size-7 has-text-grey mb-4">
        Ces valeurs alimentent l'équivalent essence/diesel du dashboard et de l'historique. Aucune valeur par défaut n'est appliquée :
        un véhicule laissé sans consommation renseignée n'aura simplement pas d'équivalent calculé pour ses recharges.
        La courbe de recharge associée alimente la page
        <a href="{{ route('charging-curves.index') }}">Courbe de recharge</a> ; le véhicule par défaut y est affiché en premier.
        L'<strong>identifiant MQTT</strong> est celui que le boîtier OBD utilise pour publier ses relevés
        (topics <code>vehicles/{identifiant}/…</code>, réglable dans XPCarData). Sans lui, ses messages ne peuvent
        être rattachés à aucune voiture, et les pages « Ma voiture » et « Déplacements » restent inaccessibles.
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
                        @if ($vehicle->mqtt_client_id)
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
                    {{-- Identifiant que le boitier publie dans ses topics MQTT
                         (vehicles/{ident}/data) : sans lui ses messages ne se
                         rattachent a aucune voiture. --}}
                    <div class="column is-3">
                        <div class="field">
                            <label class="label is-small">Identifiant MQTT</label>
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
                    </div>
                </div>

                <hr>
                <p class="label is-small mb-2">Amortissement face à une thermique équivalente</p>
                <div class="columns is-multiline">
                    <div class="column is-3">
                        <div class="field">
                            <label class="label is-small">Prix d'achat (€)</label>
                            <div class="control">
                                <input class="input" type="number" step="1" min="0" name="purchase_price" value="{{ $vehicle->purchase_price }}" placeholder="ex. 50000">
                            </div>
                        </div>
                    </div>
                    <div class="column is-3">
                        <div class="field">
                            <label class="label is-small">Date d'achat</label>
                            <div class="control">
                                <input class="input" type="date" name="purchase_date" value="{{ $vehicle->purchase_date?->format('Y-m-d') }}">
                            </div>
                        </div>
                    </div>
                    <div class="column is-3">
                        <div class="field">
                            <label class="label is-small">Kilométrage à l'achat</label>
                            <div class="control">
                                <input class="input" type="number" step="1" min="0" name="purchase_odometer_km" value="{{ $vehicle->purchase_odometer_km }}" placeholder="0 si neuve">
                            </div>
                        </div>
                    </div>
                    <div class="column is-3">
                        <div class="field">
                            <label class="label is-small">Thermique de comparaison</label>
                            <div class="control">
                                <input class="input" type="text" name="thermal_equivalent_label" value="{{ $vehicle->thermal_equivalent_label }}" placeholder="ex. Škoda Kodiaq 2.0 TDI">
                            </div>
                        </div>
                    </div>
                    <div class="column is-3">
                        <div class="field">
                            <label class="label is-small">Prix thermique (€)</label>
                            <div class="control">
                                <input class="input" type="number" step="1" min="0" name="thermal_equivalent_price" value="{{ $vehicle->thermal_equivalent_price }}" placeholder="ex. 50000">
                            </div>
                        </div>
                    </div>
                    <div class="column is-2">
                        <div class="field">
                            <label class="label is-small">Entretien EV (€)</label>
                            <div class="control">
                                <input class="input" type="number" step="1" min="0" name="ev_maintenance_cost" value="{{ $vehicle->ev_maintenance_cost }}" placeholder="180">
                            </div>
                        </div>
                    </div>
                    <div class="column is-2">
                        <div class="field">
                            <label class="label is-small">…tous les (km)</label>
                            <div class="control">
                                <input class="input" type="number" step="1" min="1" name="ev_maintenance_interval_km" value="{{ $vehicle->ev_maintenance_interval_km }}" placeholder="20000">
                            </div>
                        </div>
                    </div>
                    <div class="column is-2">
                        <div class="field">
                            <label class="label is-small">Entretien thermique (€)</label>
                            <div class="control">
                                <input class="input" type="number" step="1" min="0" name="thermal_maintenance_cost" value="{{ $vehicle->thermal_maintenance_cost }}" placeholder="350">
                            </div>
                        </div>
                    </div>
                    <div class="column is-3">
                        <div class="field">
                            <label class="label is-small">…tous les (km)</label>
                            <div class="control">
                                <input class="input" type="number" step="1" min="1" name="thermal_maintenance_interval_km" value="{{ $vehicle->thermal_maintenance_interval_km }}" placeholder="25000">
                            </div>
                        </div>
                    </div>
                    <div class="column is-2 is-flex is-align-items-flex-end">
                        <div class="field">
                            <div class="control">
                                <button type="submit" class="button is-info is-light is-fullwidth">Enregistrer</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    @empty
        <div class="notification is-info is-light">Aucun véhicule pour l'instant.</div>
    @endforelse
@endsection
