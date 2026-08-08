<div class="table-container">
    <table class="table is-fullwidth is-striped is-hoverable">
        <thead>
            <tr>
                <th>Date</th>
                <th>Véhicule</th>
                <th>Localisation</th>
                <th>Fournisseur</th>
                <th>Puissance</th>
                <th>kWh</th>
                <th>Durée recharge</th>
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
                    <td>{{ $session->vehicle->name }}</td>
                    <td>{{ $session->location->name }}</td>
                    <td>{{ $session->provider->name }}</td>
                    <td>{{ rtrim(rtrim($session->powerRating->kw, '0'), '.') }} kW</td>
                    <td>{{ $session->quantity_kwh }}</td>
                    <td>{{ $session->charge_duration?->format('H:i') ?? '—' }}</td>
                    <td>{{ $session->unit_cost ?? '—' }}</td>
                    <td>{{ $session->total_cost ?? '—' }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($session->comment, 30) }}</td>
                    <td class="is-flex is-flex-wrap-nowrap">
                        <a href="{{ route('charging-sessions.edit', $session) }}" class="button is-small is-info is-light mr-1">Éditer</a>
                        <a href="{{ route('charging-sessions.index', ['duplicate' => $session->id]) }}" class="button is-small is-light mr-1">Dupliquer</a>
                        <form method="POST" action="{{ route('charging-sessions.destroy', $session) }}" onsubmit="return confirm('Supprimer cette recharge ?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="button is-small is-danger is-light">Suppr.</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="has-text-centered has-text-grey">{{ $emptyMessage ?? "Aucune recharge enregistrée." }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
