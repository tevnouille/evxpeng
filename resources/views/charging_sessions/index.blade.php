@extends('layouts.app')

@section('title', 'Recharges')

@section('content')
    <h1 class="title">{{ $editing ? 'Modifier une recharge' : 'Nouvelle recharge' }}</h1>

    <div class="box">
        <form method="POST" action="{{ $editing ? route('charging-sessions.update', $editing) : route('charging-sessions.store') }}">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="columns is-multiline">
                <div class="column is-3">
                    <div class="field">
                        <label class="label">Date</label>
                        <div class="control">
                            <input class="input" type="date" name="session_date" required
                                value="{{ old('session_date', optional($editing?->session_date)->format('Y-m-d')) }}">
                        </div>
                    </div>
                </div>

                <div class="column is-4">
                    <div class="field">
                        <label class="label">Fournisseur borne</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="provider_id" required>
                                    <option value="" disabled {{ old('provider_id', $editing?->provider_id) ? '' : 'selected' }}>-- choisir --</option>
                                    @foreach ($providers as $provider)
                                        <option value="{{ $provider->id }}" @selected(old('provider_id', $editing?->provider_id) == $provider->id)>{{ $provider->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <p class="help"><a href="{{ route('reference-data.index') }}">Ajouter / éditer / supprimer un fournisseur</a></p>
                    </div>
                </div>

                <div class="column is-3">
                    <div class="field">
                        <label class="label">Puissance borne</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="power_rating_id" required>
                                    <option value="" disabled {{ old('power_rating_id', $editing?->power_rating_id) ? '' : 'selected' }}>-- choisir --</option>
                                    @foreach ($powerRatings as $powerRating)
                                        <option value="{{ $powerRating->id }}" @selected(old('power_rating_id', $editing?->power_rating_id) == $powerRating->id)>{{ rtrim(rtrim($powerRating->kw, '0'), '.') }} kW</option>
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
                                value="{{ old('charge_duration', optional($editing?->charge_duration)->format('H:i')) }}">
                        </div>
                    </div>
                </div>

                <div class="column is-3">
                    <div class="field">
                        <label class="label">Durée de stationnement</label>
                        <div class="control">
                            <input class="input" type="time" name="parking_duration"
                                value="{{ old('parking_duration', optional($editing?->parking_duration)->format('H:i')) }}">
                        </div>
                    </div>
                </div>

                <div class="column is-3">
                    <div class="field">
                        <label class="label">Coût unitaire (€/kWh)</label>
                        <div class="control">
                            <input class="input" type="number" step="0.0001" min="0" name="unit_cost" id="unit_cost"
                                value="{{ old('unit_cost', $editing?->unit_cost) }}">
                        </div>
                    </div>
                </div>

                <div class="column is-3">
                    <div class="field">
                        <label class="label">Coût total facturé (€)</label>
                        <div class="control">
                            <input class="input" type="number" step="0.01" min="0" name="total_cost" id="total_cost"
                                value="{{ old('total_cost', $editing?->total_cost) }}">
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
                    <button type="submit" class="button is-primary">{{ $editing ? 'Enregistrer' : 'Ajouter' }}</button>
                </div>
                @if ($editing)
                    <div class="control">
                        <a href="{{ route('charging-sessions.index') }}" class="button is-light">Annuler</a>
                    </div>
                @endif
            </div>
        </form>
    </div>

    <h2 class="title is-4">Historique</h2>

    <div class="table-container">
        <table class="table is-fullwidth is-striped is-hoverable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Fournisseur</th>
                    <th>Puissance</th>
                    <th>kWh</th>
                    <th>Durée recharge</th>
                    <th>Durée stationnement</th>
                    <th>€/kWh</th>
                    <th>Total €</th>
                    <th>Commentaire</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sessions as $session)
                    <tr>
                        <td>{{ $session->session_date->format('d/m/Y') }}</td>
                        <td>{{ $session->provider->name }}</td>
                        <td>{{ rtrim(rtrim($session->powerRating->kw, '0'), '.') }} kW</td>
                        <td>{{ $session->quantity_kwh }}</td>
                        <td>{{ $session->charge_duration ? \Illuminate\Support\Carbon::parse($session->charge_duration)->format('H:i') : '—' }}</td>
                        <td>{{ $session->parking_duration ? \Illuminate\Support\Carbon::parse($session->parking_duration)->format('H:i') : '—' }}</td>
                        <td>{{ $session->unit_cost ?? '—' }}</td>
                        <td>{{ $session->total_cost ?? '—' }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($session->comment, 30) }}</td>
                        <td class="is-flex is-flex-wrap-nowrap">
                            <a href="{{ route('charging-sessions.edit', $session) }}" class="button is-small is-info is-light mr-1">Éditer</a>
                            <form method="POST" action="{{ route('charging-sessions.destroy', $session) }}" onsubmit="return confirm('Supprimer cette recharge ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="button is-small is-danger is-light">Suppr.</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="has-text-centered has-text-grey">Aucune recharge enregistrée pour l'instant.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $sessions->links() }}
@endsection
