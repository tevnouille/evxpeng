@extends('layouts.app')

@section('title', 'Recharges')

@section('content')
    <h1 class="title">{{ $editing ? 'Modifier une recharge' : 'Nouvelle recharge' }}</h1>
    @if ($duplicateFrom)
        <p class="notification is-info is-light">Localisation, fournisseur, puissance et coût unitaire repris de la recharge du {{ $duplicateFrom->session_date->format('d/m/Y') }}.</p>
    @endif

    @if (! $editing && count($pendingCharges) > 0)
        <div class="box">
            <h2 class="title is-5">
                Recharges détectées non enregistrées
                <span class="tag is-warning is-medium ml-2">{{ count($pendingCharges) }}</span>
            </h2>
            <p class="has-text-grey is-size-7 mb-4">
                Repérées par la télémétrie du véhicule. L'énergie indiquée est celle <strong>entrée dans la
                batterie</strong> : elle est inférieure à celle facturée à la borne, qui inclut les pertes de charge.
                « Ajouter » pré-remplit le formulaire ci-dessous avec la date, la durée et cette estimation —
                à vous de corriger la quantité facturée et de compléter le fournisseur et le coût.
            </p>

            <div class="table-container">
                <table class="table is-fullwidth is-striped is-hoverable">
                    <thead>
                        <tr>
                            <th>Début</th>
                            <th>Véhicule</th>
                            <th>Lieu reconnu</th>
                            <th>Durée</th>
                            <th class="has-text-right">Niveau</th>
                            <th class="has-text-right">Énergie estimée</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendingCharges as $charge)
                            <tr>
                                <td>{{ $charge['started_at']->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                                <td>{{ $charge['vehicle']->name }}</td>
                                <td>
                                    @if (! empty($charge['context']['location_name']))
                                        {{ $charge['context']['location_name'] }}
                                        <span class="has-text-grey is-size-7">à {{ $charge['context']['distance_m'] }} m</span>
                                    @else
                                        <span class="has-text-grey">—</span>
                                    @endif
                                </td>
                                <td>
                                    {{ intdiv($charge['duration_minutes'], 60) }} h {{ str_pad((string) ($charge['duration_minutes'] % 60), 2, '0', STR_PAD_LEFT) }}
                                    @if ($charge['samples'] < 2)
                                        <span class="tag is-warning is-light ml-1" title="Un seul relevé pendant la charge : les bornes sont approximatives">1 relevé</span>
                                    @endif
                                </td>
                                <td class="has-text-right">
                                    {{ $charge['soc_start'] !== null ? (int) $charge['soc_start'] . ' %' : '?' }}
                                    &rarr;
                                    {{ $charge['soc_end'] !== null ? (int) $charge['soc_end'] . ' %' : '?' }}
                                </td>
                                <td class="has-text-right">
                                    {{ $charge['kwh'] !== null ? str_replace('.', ',', (string) $charge['kwh']) . ' kWh' : '—' }}
                                </td>
                                <td class="has-text-right">
                                    <a class="button is-small is-primary"
                                       href="{{ route('charging-sessions.index', [
                                           'prefill_vehicle' => $charge['vehicle']->id,
                                           'prefill_date' => $charge['started_at']->timezone(config('app.timezone'))->format('Y-m-d'),
                                           'prefill_kwh' => $charge['kwh'],
                                           'prefill_duration' => sprintf('%02d:%02d', intdiv($charge['duration_minutes'], 60), $charge['duration_minutes'] % 60),
                                           'prefill_telemetry_start' => $charge['started_at']->format('Y-m-d H:i:s'),
                                           'prefill_location' => $charge['context']['location_id'] ?? null,
                                           'prefill_provider' => $charge['context']['provider_id'] ?? null,
                                           'prefill_power' => $charge['context']['power_rating_id'] ?? null,
                                       ]) }}#formulaire">
                                        Ajouter
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="box" id="formulaire">
        <form method="POST" action="{{ $editing ? route('charging-sessions.update', $editing) : route('charging-sessions.store') }}">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            @if (! empty($prefill['telemetry_started_at']))
                <input type="hidden" name="telemetry_started_at" value="{{ $prefill['telemetry_started_at'] }}">
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
                                value="{{ old('session_date', $editing?->session_date?->format('Y-m-d') ?? ($prefill['session_date'] ?? now()->format('Y-m-d'))) }}">
                        </div>
                    </div>
                </div>

                <div class="column is-3">
                    <div class="field">
                        <label class="label">Localisation</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="location_choice" id="location_choice" required data-searchable>
                                    <option value="" disabled {{ old('location_choice', $editing?->location_id ?? $duplicateFrom?->location_id ?? ($prefill['location_id'] ?? null)) ? '' : 'selected' }}>-- choisir --</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->id }}" @selected(old('location_choice', $editing?->location_id ?? $duplicateFrom?->location_id ?? ($prefill['location_id'] ?? null)) == $location->id)>{{ $location->name }}</option>
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
                                    <option value="" disabled {{ old('provider_choice', $editing?->provider_id ?? $duplicateFrom?->provider_id ?? ($prefill['provider_id'] ?? null)) ? '' : 'selected' }}>-- choisir --</option>
                                    @foreach ($providers as $provider)
                                        <option value="{{ $provider->id }}" @selected(old('provider_choice', $editing?->provider_id ?? $duplicateFrom?->provider_id ?? ($prefill['provider_id'] ?? null)) == $provider->id)>{{ $provider->name }}</option>
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
                                    <option value="" disabled {{ old('power_rating_id', $editing?->power_rating_id ?? $duplicateFrom?->power_rating_id ?? ($prefill['power_rating_id'] ?? null)) ? '' : 'selected' }}>-- choisir --</option>
                                    @foreach ($powerRatings as $powerRating)
                                        <option value="{{ $powerRating->id }}" @selected(old('power_rating_id', $editing?->power_rating_id ?? $duplicateFrom?->power_rating_id ?? ($prefill['power_rating_id'] ?? null)) == $powerRating->id)>{{ rtrim(rtrim($powerRating->kw, '0'), '.') }} kW</option>
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
                                value="{{ old('quantity_kwh', $editing?->quantity_kwh ?? ($prefill['quantity_kwh'] ?? null)) }}">
                        </div>
                    </div>
                </div>

                <div class="column is-3">
                    <div class="field">
                        <label class="label">Durée de recharge</label>
                        <div class="control">
                            <input class="input" type="time" name="charge_duration"
                                value="{{ old('charge_duration', $editing ? $editing->charge_duration?->format('H:i') : ($prefill['charge_duration'] ?? '00:00')) }}">
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
                        <div class="field has-addons">
                            <div class="control is-expanded">
                                <input class="input" type="number" step="0.01" min="0" name="total_cost" id="total_cost"
                                    value="{{ old('total_cost', $editing ? $editing->total_cost : 0) }}">
                            </div>
                            <div class="control">
                                {{-- Le calcul automatique s'arrete des qu'un total est saisi, ce qui est
                                     toujours le cas en modification : ce bouton le force. --}}
                                <button type="button" class="button is-light" id="recompute_total"
                                    title="Recalculer : quantité × coût unitaire">
                                    Recalculer
                                </button>
                            </div>
                        </div>
                        <p class="help">
                            Calculé automatiquement (quantité × coût unitaire) tant qu'il n'a pas été saisi à la main.
                            En modification, utilisez <strong>Recalculer</strong>.
                        </p>
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
