@extends('layouts.app')

@section('title', 'Kilométrage')

@section('content')
    <h1 class="title">Kilométrage</h1>

    @if (! $vehicle)
        <div class="notification is-warning is-light">
            Aucun véhicule n'est relié au boîtier OBD.
            Renseignez un identifiant MQTT depuis
            <a href="{{ route('reference-data.vehicles.index') }}">Administration &rarr; Véhicules</a>.
        </div>
    @else
        @if ($vehicles->count() > 1)
            <div class="field">
                <label class="label">Véhicule</label>
                <div class="control">
                    <div class="select">
                        <select onchange="window.location.href = '{{ route('my-vehicle.mileage') }}?vehicule=' + this.value">
                            @foreach ($vehicles as $v)
                                <option value="{{ $v->id }}" @selected($v->id === $vehicle->id)>
                                    {{ $v->name }}{{ $v->is_default ? ' (par défaut)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        @endif

        @if (empty($byMonth))
            <p class="has-text-grey">Pas encore assez de relevés d'odomètre pour calculer un kilométrage.</p>
        @else
            <p class="has-text-grey is-size-7 mb-4">
                Distance déduite de l'odomètre du boîtier OBD, mois par mois — la même règle que le tableau
                quotidien de « Ma voiture » : un écart négatif ou supérieur à 2 000 km entre deux relevés
                (changement de source, remise à zéro) n'est pas compté. {{ number_format($totalKm, 0, ',', ' ') }} km
                au total sur la période connue.
            </p>

            <div class="box">
                <h2 class="title is-5 mb-2">Par année</h2>
                <div class="table-container">
                    <table class="table is-fullwidth is-narrow is-striped">
                        <thead>
                            <tr>
                                <th>Année</th>
                                <th class="has-text-right">Distance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($byYear as $year => $km)
                                <tr>
                                    <td>{{ $year }}</td>
                                    <td class="has-text-right">{{ number_format($km, 0, ',', ' ') }} km</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="box">
                <h2 class="title is-5 mb-2">Par mois</h2>
                <p class="has-text-grey is-size-7 mb-3">Cliquer sur un mois pour afficher le détail par jour.</p>
                <div class="table-container">
                    <table class="table is-fullwidth is-narrow is-hoverable" id="km-par-mois">
                        <thead>
                            <tr>
                                <th>Mois</th>
                                <th class="has-text-right">Distance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($byMonth as $row)
                                <tr class="is-clickable has-text-weight-semibold" data-mois="{{ $row['month'] }}" aria-expanded="false">
                                    <td>
                                        <span class="fleche" aria-hidden="true">▸</span>
                                        {{ ucfirst(\Carbon\CarbonImmutable::createFromFormat('Y-m-d', $row['month'] . '-01')->translatedFormat('F Y')) }}
                                    </td>
                                    <td class="has-text-right">{{ number_format($row['km'], 0, ',', ' ') }} km</td>
                                </tr>
                                @foreach ($row['days'] as $jour)
                                    <tr data-jour-de="{{ $row['month'] }}" class="has-background-white-bis" hidden>
                                        <td class="pl-5">{{ \Carbon\CarbonImmutable::createFromFormat('Y-m-d', $jour['date'])->translatedFormat('D d/m') }}</td>
                                        <td class="has-text-right">{{ number_format($jour['km'], 0, ',', ' ') }} km</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <script>
                document.getElementById('km-par-mois').addEventListener('click', function (e) {
                    const ligne = e.target.closest('tr[data-mois]');
                    if (!ligne) return;

                    const ouvert = ligne.getAttribute('aria-expanded') !== 'true';
                    ligne.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
                    ligne.querySelector('.fleche').textContent = ouvert ? '▾' : '▸';
                    document.querySelectorAll('tr[data-jour-de="' + ligne.dataset.mois + '"]')
                        .forEach((jour) => { jour.hidden = !ouvert; });
                });
            </script>
        @endif
    @endif
@endsection
