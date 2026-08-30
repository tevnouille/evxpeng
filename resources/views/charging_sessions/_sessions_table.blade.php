@php
    // Modifier ou supprimer depuis la page d'un mois doit y ramener : sans cela
    // on repartait sur /recharges, en perdant le mois consulte. On transporte
    // l'annee et le mois, jamais une URL, pour ne pas ouvrir une redirection.
    $returnQuery = isset($returnTo) ? ['return_year' => $returnTo['year'], 'return_month' => $returnTo['month']] : [];
@endphp
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
                <th>Réel €</th>
                <th>Total €</th>
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
                    <td>{{ $session->real_cost ?? '—' }}</td>
                    <td>{{ $session->total_cost ?? '—' }}</td>
                    <td class="is-flex is-flex-wrap-nowrap">
                        <a href="{{ route('charging-sessions.edit', array_merge([$session], $returnQuery)) }}" class="button is-small is-info is-light mr-1">Éditer</a>
                        <a href="{{ route('charging-sessions.index', ['duplicate' => $session->id]) }}" class="button is-small is-light mr-1">Dupliquer</a>
                        <form method="POST" action="{{ route('charging-sessions.destroy', array_merge([$session], $returnQuery)) }}" onsubmit="return confirm('Supprimer cette recharge ?');">
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
