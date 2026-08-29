@extends('layouts.app')

@section('title', 'Recharges')

@section('content')
    <h1 class="title">{{ $editing ? 'Modifier une recharge' : 'Nouvelle recharge' }}</h1>
    @if ($duplicateFrom)
        <p class="notification is-info is-light">Localisation, fournisseur, puissance et coût unitaire repris de la recharge du {{ $duplicateFrom->session_date->format('d/m/Y') }}.</p>
    @endif

    <div class="box">
        <form method="POST" action="{{ $editing ? route('charging-sessions.update', $editing) : route('charging-sessions.store') }}">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="columns is-multiline">
                <div class="column is-3">
                    <div class="field">
                        <label class="label">Véhicule</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="vehicle_id" id="vehicle_id" required data-searchable>
                                    <option value="" disabled {{ old('vehicle_id', $editing?->vehicle_id ?? $vehicles->firstWhere('is_default', true)?->id) ? '' : 'selected' }}>-- choisir --</option>
                                    @foreach ($vehicles as $vehicle)
                                        <option value="{{ $vehicle->id }}" @selected(old('vehicle_id', $editing?->vehicle_id ?? $vehicles->firstWhere('is_default', true)?->id) == $vehicle->id)>{{ $vehicle->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <p class="help"><a href="{{ route('reference-data.index') }}">Ajouter / éditer / supprimer un véhicule</a></p>
                    </div>
                </div>

                <div class="column is-3">
                    <div class="field">
                        <label class="label">Date</label>
                        <div class="control">
                            <input class="input" type="date" name="session_date" required
                                value="{{ old('session_date', $editing?->session_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
                        </div>
                    </div>
                </div>

                <div class="column is-3">
                    <div class="field">
                        <label class="label">Localisation</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="location_choice" id="location_choice" required data-searchable>
                                    <option value="" disabled {{ old('location_choice', $editing?->location_id ?? $duplicateFrom?->location_id) ? '' : 'selected' }}>-- choisir --</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->id }}" @selected(old('location_choice', $editing?->location_id ?? $duplicateFrom?->location_id) == $location->id)>{{ $location->name }}</option>
                                    @endforeach
                                    <option value="other" @selected(old('location_choice') === 'other')>Autre…</option>
                                </select>
                            </div>
                        </div>
                        <div class="control mt-2">
                            <button type="button" id="geolocate_button" class="button is-small is-light">&#128205; Utiliser ma position</button>
                        </div>
                        <div class="control mt-2" id="location_other_wrapper" style="display: {{ old('location_choice') === 'other' ? 'block' : 'none' }};">
                            <input class="input" type="text" name="location_other" id="location_other" placeholder="Nouvelle localisation" value="{{ old('location_other') }}">
                        </div>
                        <p class="help"><a href="{{ route('reference-data.index') }}">Ajouter / éditer / supprimer une localisation</a></p>
                    </div>
                </div>

                <div class="column is-4">
                    <div class="field">
                        <label class="label">Fournisseur borne</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="provider_choice" id="provider_choice" required data-searchable>
                                    <option value="" disabled {{ old('provider_choice', $editing?->provider_id ?? $duplicateFrom?->provider_id) ? '' : 'selected' }}>-- choisir --</option>
                                    @foreach ($providers as $provider)
                                        <option value="{{ $provider->id }}" @selected(old('provider_choice', $editing?->provider_id ?? $duplicateFrom?->provider_id) == $provider->id)>{{ $provider->name }}</option>
                                    @endforeach
                                    <option value="other" @selected(old('provider_choice') === 'other')>Autre…</option>
                                </select>
                            </div>
                        </div>
                        <div class="control mt-2" id="provider_other_wrapper" style="display: {{ old('provider_choice') === 'other' ? 'block' : 'none' }};">
                            <input class="input" type="text" name="provider_other" placeholder="Nouveau fournisseur" value="{{ old('provider_other') }}">
                        </div>
                        <p class="help"><a href="{{ route('reference-data.index') }}">Ajouter / éditer / supprimer un fournisseur</a></p>
                    </div>
                </div>

                <div class="column is-3">
                    <div class="field">
                        <label class="label">Puissance borne</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="power_rating_id" id="power_rating_id" required data-searchable>
                                    <option value="" disabled {{ old('power_rating_id', $editing?->power_rating_id ?? $duplicateFrom?->power_rating_id) ? '' : 'selected' }}>-- choisir --</option>
                                    @foreach ($powerRatings as $powerRating)
                                        <option value="{{ $powerRating->id }}" @selected(old('power_rating_id', $editing?->power_rating_id ?? $duplicateFrom?->power_rating_id) == $powerRating->id)>{{ rtrim(rtrim($powerRating->kw, '0'), '.') }} kW</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <p class="help"><a href="{{ route('reference-data.index') }}">Ajouter / éditer / supprimer une puissance</a></p>
                    </div>
                </div>

                <div class="column is-2">
                    <div class="field">
                        <label class="label">Quantité (kWh)</label>
                        <div class="control">
                            <input class="input" type="number" step="0.01" min="0" name="quantity_kwh" id="quantity_kwh" required
                                value="{{ old('quantity_kwh', $editing?->quantity_kwh) }}">
                        </div>
                    </div>
                </div>

                <div class="column is-3">
                    <div class="field">
                        <label class="label">Durée de recharge</label>
                        <div class="control">
                            <input class="input" type="time" name="charge_duration"
                                value="{{ old('charge_duration', $editing ? $editing->charge_duration?->format('H:i') : '00:00') }}">
                        </div>
                    </div>
                </div>

                <div class="column is-3">
                    <div class="field">
                        <label class="label">Coût unitaire (€/kWh)</label>
                        <div class="control">
                            <input class="input" type="number" step="0.0001" min="0" name="unit_cost" id="unit_cost"
                                value="{{ old('unit_cost', $editing?->unit_cost ?? $duplicateFrom?->unit_cost) }}">
                        </div>
                    </div>
                </div>

                <div class="column is-3">
                    <div class="field">
                        <label class="label">Coût total facturé (€)</label>
                        <div class="control">
                            <input class="input" type="number" step="0.01" min="0" name="total_cost" id="total_cost"
                                value="{{ old('total_cost', $editing ? $editing->total_cost : 0) }}">
                        </div>
                        <p class="help">Calculé automatiquement (quantité × coût unitaire), modifiable.</p>
                    </div>
                </div>

                <div class="column is-12">
                    <div class="field">
                        <label class="label">Commentaire</label>
                        <div class="control">
                            <textarea class="textarea" name="comment" rows="2">{{ old('comment', $editing?->comment) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="field is-grouped">
                <div class="control">
                    <button type="submit" name="action" value="save" class="button is-primary">{{ $editing ? 'Enregistrer' : 'Ajouter' }}</button>
                </div>
                @if (! $editing)
                    <div class="control">
                        <button type="submit" name="action" value="save_and_duplicate" class="button is-link is-light">Ajouter et dupliquer</button>
                    </div>
                @endif
                @if ($editing)
                    <div class="control">
                        <a href="{{ route('charging-sessions.index') }}" class="button is-light">Annuler</a>
                    </div>
                @endif
            </div>
        </form>
    </div>

    <div class="level">
        <div class="level-left">
            <h2 class="title is-4">10 dernières recharges</h2>
        </div>
        <div class="level-right">
            <div class="buttons">
                <a href="{{ route('history.show', ['year' => now()->year, 'month' => now()->month]) }}" class="button is-link is-light">Historique du mois en cours</a>
                <a href="{{ route('history.index') }}" class="button is-link is-light">Voir tout l'historique</a>
            </div>
        </div>
    </div>

    @include('charging_sessions._sessions_table', ['emptyMessage' => "Aucune recharge enregistrée pour l'instant."])
@endsection
