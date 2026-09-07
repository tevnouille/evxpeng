@php
    // Modifier ou supprimer depuis la page d'un mois doit y ramener : sans cela
    // on repartait sur /recharges, en perdant le mois consulte. On transporte
    // l'annee et le mois, jamais une URL, pour ne pas ouvrir une redirection.
    $returnQuery = isset($returnTo) ? ['return_year' => $returnTo['year'], 'return_month' => $returnTo['month']] : [];

    // Identifiant propre a l'inclusion : le partiel pourrait se retrouver deux
    // fois sur une meme page, et le champ de filtre doit viser son tableau.
    $tableId = 'sessions-'.uniqid();
@endphp

<div class="level is-mobile mb-2">
    <div class="level-left">
        <div class="field mb-0">
            <div class="control has-icons-left">
                <input class="input" type="search" placeholder="Filtrer…" autocomplete="off"
                       data-filters="{{ $tableId }}" aria-label="Filtrer les recharges">
                <span class="icon is-small is-left">&#128269;</span>
            </div>
        </div>
    </div>
    <div class="level-right">
        <span class="has-text-grey is-size-7" data-filter-count="{{ $tableId }}"></span>
    </div>
</div>

<div class="table-container">
    <table class="table is-fullwidth is-striped is-hoverable" id="{{ $tableId }}" data-sessions-table>
        <thead>
            <tr>
                {{-- data-sort marque les colonnes triables : la derniere, qui ne
                     porte que des boutons, ne l'est pas. --}}
                <th data-sort>Date</th>
                <th data-sort>Véhicule</th>
                <th data-sort>Localisation</th>
                <th data-sort>Fournisseur</th>
                <th data-sort>Puissance</th>
                <th data-sort>kWh</th>
                <th data-sort>Durée recharge</th>
                <th data-sort>€/kWh</th>
                <th data-sort>Réel €</th>
                <th data-sort>Total €</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sessions as $session)
                @php
                    $duree = $session->charge_duration;
                @endphp
                <tr>
                    {{-- data-value porte la valeur qui sert au tri quand elle
                         differe de l'affichage : une date en 31/01 se trierait
                         apres le 01/02, une duree en H:i apres 9:00, et « — »
                         doit tomber en fin de liste plutot qu'au debut. --}}
                    <td data-value="{{ $session->session_date->format('Y-m-d') }}">{{ $session->session_date->format('d/m/Y') }}</td>
                    <td>{{ $session->vehicle->name }}</td>
                    <td>{{ $session->location->name }}</td>
                    <td>{{ $session->provider->name }}</td>
                    <td data-value="{{ (float) $session->powerRating->kw }}">{{ rtrim(rtrim($session->powerRating->kw, '0'), '.') }} kW</td>
                    <td data-value="{{ (float) $session->quantity_kwh }}">{{ $session->quantity_kwh }}</td>
                    <td data-value="{{ $duree ? $duree->hour * 60 + $duree->minute : -1 }}">{{ $duree?->format('H:i') ?? '—' }}</td>
                    <td data-value="{{ $session->unit_cost !== null ? (float) $session->unit_cost : -1 }}">{{ $session->unit_cost ?? '—' }}</td>
                    <td data-value="{{ $session->real_cost !== null ? (float) $session->real_cost : -1 }}">{{ $session->real_cost ?? '—' }}</td>
                    <td data-value="{{ $session->total_cost !== null ? (float) $session->total_cost : -1 }}">{{ $session->total_cost ?? '—' }}</td>
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

@vite('resources/js/sessions-table.js')
