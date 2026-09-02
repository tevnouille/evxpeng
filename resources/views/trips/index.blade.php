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
    @endif
@endsection

@push('scripts')
    @vite('resources/js/trips.js')
@endpush
