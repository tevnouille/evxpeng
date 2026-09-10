@extends('layouts.app')

@section('title', 'Trajets habituels')

@section('content')
    <h1 class="title">Trajets habituels</h1>

    @if (! $vehicle)
        <div class="notification is-warning is-light">
            Aucun véhicule n'est relié au boîtier OBD.
            Renseignez un identifiant MQTT depuis
            <a href="{{ route('reference-data.vehicles.index') }}">Administration &rarr; Véhicules</a>.
        </div>
    @else
        <div class="columns">
            @if ($vehicles->count() > 1)
                <div class="column is-narrow">
                    <div class="field">
                        <label class="label">Véhicule</label>
                        <div class="control">
                            <div class="select">
                                <select onchange="window.location.search = new URLSearchParams({vehicule: this.value, jours: '{{ $days }}'}).toString()">
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
                    <label class="label">Période</label>
                    <div class="control">
                        <div class="select">
                            <select onchange="window.location.search = new URLSearchParams({vehicule: '{{ $vehicle->id }}', jours: this.value}).toString()">
                                @foreach ([14, 30, 60, 90, 180] as $option)
                                    <option value="{{ $option }}" @selected($days === $option)>{{ $option }} jours</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($tripCount === 0)
            <div class="notification is-info is-light">
                Aucun trajet sur les {{ $days }} derniers jours.
            </div>
        @else
            <div class="box">
                <p class="has-text-grey is-size-7 mb-3">
                    {{ $tripCount }} trajet(s) distinct(s), {{ number_format($pointCount, 0, ',', ' ') }} relevé(s)
                    sur {{ $days }} jours. Chaque trace est semi-transparente&nbsp;: un trajet fait une fois se voit
                    à peine, un trajet quotidien (domicile-travail, par exemple) s'assombrit à force de passages
                    superposés. Aucune donnée de trafic ni de carte de chaleur — l'effet vient uniquement du cumul
                    des traces.
                </p>
                <div id="recurring-map" style="height: 65vh;" data-polylines='@json($polylines)'></div>
                <p class="has-text-grey is-size-7 mt-3">
                    Les fonds de carte proviennent d'OpenStreetMap : afficher cette page transmet la zone
                    consultée à leurs serveurs.
                </p>
            </div>
        @endif
    @endif
@endsection

@push('scripts')
    @vite('resources/js/trips.js')
@endpush
