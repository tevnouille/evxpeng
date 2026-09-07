@extends('layouts.app')

@section('title', 'Déplacements')

@section('content')
    <h1 class="title">Déplacements</h1>

    @if (! $vehicle)
        <div class="notification is-warning is-light">
            Aucun véhicule n'est relié au boîtier OBD.
            Renseignez un identifiant MQTT depuis
            <a href="{{ route('reference-data.vehicles.index') }}">Administration &rarr; Véhicules</a>.
        </div>
    @elseif ($days->isEmpty())
        <div class="notification is-info is-light">
            Aucune position enregistrée pour l'instant. Les positions arrivent avec la télémétrie ;
            elles ne sont relevées que lorsque la voiture communique.
        </div>
    @else
        <div class="columns">
            @if ($vehicles->count() > 1)
                <div class="column is-narrow">
                    <div class="field">
                        <label class="label">Véhicule</label>
                        <div class="control">
                            <div class="select">
                                <select onchange="window.location.search = new URLSearchParams({vehicule: this.value}).toString()">
                                    @foreach ($vehicles as $v)
                                        <option value="{{ $v->id }}" @selected($v->id === $vehicle->id)>
                                            {{ $v->name }}{{ $v->is_default ? ' (par défaut)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="column is-narrow">
                <div class="field">
                    <label class="label">Journée</label>
                    <div class="control">
                        <div class="select">
                            <select onchange="window.location.search = new URLSearchParams({vehicule: '{{ $vehicle->id }}', date: this.value}).toString()">
                                @foreach ($days as $day)
                                    <option value="{{ $day->day }}" @selected($day->day === $date)>
                                        {{ \Carbon\Carbon::parse($day->day)->format('d/m/Y') }} — {{ $day->points }} relevé{{ $day->points > 1 ? 's' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="box">
            <div class="columns is-multiline">
                <div class="column is-3">
                    <p class="heading">Distance parcourue</p>
                    <p class="title is-3">{{ $distanceOdometer !== null ? number_format($distanceOdometer, 0, ',', ' ') . ' km' : '—' }}</p>
                    <p class="has-text-grey is-size-7">
                        {{ $distanceOdometer !== null ? 'lue au compteur' : 'compteur non disponible ce jour-là' }}
                    </p>
                </div>
                <div class="column is-3">
                    <p class="heading">Relevés</p>
                    <p class="title is-3">{{ $points->count() }}</p>
                    <p class="has-text-grey is-size-7">
                        de {{ $points->first()->recorded_at->format('H:i') }}
                        à {{ $points->last()->recorded_at->format('H:i') }}
                    </p>
                </div>
                <div class="column is-3">
                    <p class="heading">Batterie</p>
                    <p class="title is-3">
                        {{ $points->first()->soc !== null ? (int) $points->first()->soc . ' %' : '?' }}
                        &rarr;
                        {{ $points->last()->soc !== null ? (int) $points->last()->soc . ' %' : '?' }}
                    </p>
                </div>
                <div class="column is-3">
                    <p class="heading">Somme à vol d'oiseau</p>
                    <p class="title is-3">{{ $distanceGps !== null ? str_replace('.', ',', (string) $distanceGps) . ' km' : '—' }}</p>
                    <p class="has-text-grey is-size-7">entre relevés successifs</p>
                </div>
            </div>
        </div>

        <div class="box">
            <div id="trip-map" style="height: 520px;" data-points='@json($mapPoints)'></div>
            <p class="has-text-grey is-size-7 mt-3">
                Point vert : premier relevé de la journée. Point rouge : dernier. Point jaune : véhicule en charge.
                La ligne est <strong>pointillée</strong> à dessein — elle relie des relevés espacés d'une minute au mieux,
                elle ne retrace pas la route empruntée. La somme à vol d'oiseau n'est donc qu'indicative :
                elle sous-estime dès que la voiture roule, mais peut dépasser la distance réelle à l'arrêt,
                le bruit GPS faisant bouger des relevés pourtant immobiles. <strong>Seul le compteur fait foi.</strong>
            </p>
            <p class="has-text-grey is-size-7">
                Les fonds de carte proviennent d'OpenStreetMap : afficher cette page transmet la zone consultée à leurs serveurs.
            </p>
        </div>

        @php($tableId = 'releves-'.$date)
        <div class="box">
            <h2 class="title is-5">Relevés du {{ \Carbon\CarbonImmutable::parse($date)->translatedFormat('j F Y') }}</h2>

            <div class="level is-mobile mb-2">
                <div class="level-left">
                    <div class="field mb-0">
                        <div class="control has-icons-left">
                            <input class="input" type="search" placeholder="Filtrer…" autocomplete="off"
                                   data-filters="{{ $tableId }}" aria-label="Filtrer les relevés">
                            <span class="icon is-small is-left">&#128269;</span>
                        </div>
                    </div>
                </div>
                <div class="level-right">
                    <span class="has-text-grey is-size-7" data-filter-count="{{ $tableId }}"></span>
                </div>
            </div>

            <div class="table-container">
                <table class="table is-fullwidth is-striped is-hoverable is-narrow"
                       id="{{ $tableId }}" data-sessions-table data-unit="relevé(s)">
                    <thead>
                        <tr>
                            <th data-sort>Horodatage</th>
                            <th data-sort>Latitude</th>
                            <th data-sort>Longitude</th>
                            <th data-sort>Adresse</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($readings as $releve)
                            <tr>
                                <td data-value="{{ $releve['at']->format('Y-m-d H:i:s') }}">
                                    {{ $releve['at']->timezone(config('app.timezone'))->format('H:i:s') }}
                                    @if ($releve['charging'])
                                        <span class="tag is-success is-light ml-1">en charge</span>
                                    @endif
                                </td>
                                <td data-value="{{ $releve['lat'] }}">{{ number_format($releve['lat'], 6, ',', ' ') }}</td>
                                <td data-value="{{ $releve['lon'] }}">{{ number_format($releve['lon'], 6, ',', ' ') }}</td>
                                <td>
                                    @if ($releve['label'])
                                        {{ $releve['label'] }}
                                        {{-- La distance dit si le libelle designe l'endroit ou la voiture
                                             etait, ou la maison la plus proche a trois cents metres. --}}
                                        @if ($releve['distance_m'] !== null && $releve['distance_m'] > 60)
                                            <span class="has-text-grey is-size-7">à {{ $releve['distance_m'] }} m</span>
                                        @endif
                                    @else
                                        <span class="has-text-grey">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="has-text-centered has-text-grey">Aucun relevé ce jour-là.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <p class="has-text-grey is-size-7">
                Adresses fournies par la <strong>Base Adresse Nationale</strong>, interrogée une seule fois par
                position — arrondie à une dizaine de mètres — puis conservée. Un tiret signale une position
                sans adresse connue&nbsp;: pleine campagne, aire d'autoroute, ou point trop imprécis.
            </p>
        </div>
    @endif
@endsection

@push('scripts')
    @vite(['resources/js/trips.js', 'resources/js/sessions-table.js'])
@endpush
