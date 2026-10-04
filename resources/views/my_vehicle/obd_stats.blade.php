@extends('layouts.app')

@section('title', 'Statistiques OBD')

@section('content')
    <h1 class="title">Statistiques OBD</h1>

    @if (! $vehicle || ! $month)
        <div class="notification is-info is-light">
            Aucun relevé pour l'instant. Le boîtier publie en continu dès que XPCarData est connecté
            au dongle et au broker.
        </div>
    @else
        <div class="field is-grouped is-grouped-multiline mb-5">
            @if ($vehicles->count() > 1)
                <div class="control">
                    <div class="select">
                        <select onchange="window.location.href = '{{ route('my-vehicle.obd') }}?vehicule=' + this.value">
                            @foreach ($vehicles as $v)
                                <option value="{{ $v->id }}" @selected($v->id === $vehicle->id)>{{ $v->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endif
            <div class="control">
                <div class="select">
                    <select onchange="window.location.href = '{{ route('my-vehicle.obd', ['vehicule' => $vehicle->id]) }}&mois=' + this.value">
                        @foreach ($months as $m)
                            <option value="{{ $m }}" @selected($month->format('Y-m') === $m)>
                                {{ \Carbon\CarbonImmutable::createFromFormat('Y-m-d', $m . '-01')->translatedFormat('F Y') }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Grille fixe commencant un lundi : les cases d'avant le 1er restent
             vides plutot que d'etre remplies par le mois precedent, pour qu'une
             date tombe toujours dans la meme colonne d'un mois a l'autre. --}}
        <div class="box">
            <h2 class="title is-5">{{ $month->translatedFormat('F Y') }}</h2>
            <p class="has-text-grey is-size-7 mb-4">
                Une case colorée porte des relevés ; plus elle est soutenue, plus ils sont nombreux.
                Une case vide n'est pas une panne : le boîtier n'émet que téléphone présent dans la voiture.
            </p>

            <div class="obd-calendar">
                @foreach (['lun', 'mar', 'mer', 'jeu', 'ven', 'sam', 'dim'] as $entete)
                    <div class="obd-calendar-head">{{ $entete }}</div>
                @endforeach

                @for ($i = 0; $i < $leading; $i++)
                    <div></div>
                @endfor

                @for ($numero = 1; $numero <= $month->daysInMonth; $numero++)
                    @php
                        $date = $month->startOfMonth()->addDays($numero - 1);
                        $cle = $date->format('Y-m-d');
                        $releves = $days[$cle] ?? 0;
                        $maximum = max($days ?: [1]);
                        // Quatre paliers : au-dela, l'oeil ne distingue plus les nuances.
                        $palier = $releves === 0 ? 0 : (int) ceil($releves / $maximum * 4);
                    @endphp
                    @if ($releves === 0)
                        <div class="obd-day is-empty" title="Aucun relevé">{{ $numero }}</div>
                    @else
                        <a class="obd-day level-{{ $palier }} {{ $day && $day->format('Y-m-d') === $cle ? 'is-current' : '' }}"
                           href="{{ route('my-vehicle.obd', ['vehicule' => $vehicle->id, 'mois' => $month->format('Y-m'), 'jour' => $cle]) }}"
                           title="{{ $releves }} relevé(s)">
                            {{ $numero }}
                            <span class="obd-day-count">{{ $releves }}</span>
                        </a>
                    @endif
                @endfor
            </div>
        </div>

        @if (! $readings || $readings['count'] === 0)
            <div class="notification is-info is-light">Choisissez une journée colorée dans le calendrier.</div>
        @else
            <div class="box">
                <h2 class="title is-5">
                    {{ $day->translatedFormat('l j F Y') }}
                    <span class="tag is-light is-medium ml-2">{{ $readings['count'] }} relevés</span>
                </h2>
                <p class="has-text-grey is-size-7">
                    De {{ $readings['first_at']->format('H:i:s') }} à {{ $readings['last_at']->format('H:i:s') }}.
                    Le boîtier interroge les capteurs à tour de rôle : chaque indicateur a donc son propre
                    nombre de points, toujours inférieur ou égal au nombre de relevés.
                </p>
            </div>

            @if (! empty($readings['textual']))
                <div class="box">
                    <h2 class="title is-5">États</h2>
                    <p class="has-text-grey is-size-7 mb-4">
                        Seuls les <strong>changements</strong> sont listés : un état répété à l'identique
                        n'apprend rien de plus que sa première occurrence, et sa durée se lit sur l'écart
                        des horodatages.
                    </p>
                    <div class="columns is-multiline">
                        @foreach ($readings['textual'] as $etat)
                            <div class="column is-4">
                                <p class="heading">{{ $etat['label'] }}</p>
                                <table class="table is-fullwidth is-narrow is-striped">
                                    <tbody>
                                        @foreach ($etat['changes'] as $changement)
                                            <tr>
                                                <td class="has-text-grey">{{ $changement['at']->format('H:i:s') }}</td>
                                                <td class="has-text-right">{{ $changement['value'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="box">
                <h2 class="title is-5">Mesures</h2>
                <div class="columns is-multiline">
                    @foreach ($readings['numeric'] as $mesure)
                        {{-- Trois par ligne : a cinquante indicateurs, deux colonnes
                             donnaient une page de vingt mille pixels. --}}
                        <div class="column is-4">
                            <p class="heading">
                                {{ $mesure['label'] }}
                                @if ($mesure['unit'])
                                    <span class="has-text-grey">({{ $mesure['unit'] }})</span>
                                @endif
                            </p>
                            <p class="has-text-grey is-size-7 mb-2">
                                min {{ str_replace('.', ',', (string) $mesure['min']) }} ·
                                moy {{ str_replace('.', ',', (string) $mesure['avg']) }} ·
                                max {{ str_replace('.', ',', (string) $mesure['max']) }}
                                — {{ $mesure['count'] }} point(s)
                                @if ($mesure['sampled'])
                                    <span title="La courbe est échantillonnée à pas constant : sa forme est conservée, pas chaque point.">échantillonnés</span>
                                @endif
                            </p>
                            <canvas class="obd-chart" height="150"
                                data-labels='@json($mesure['labels'])'
                                data-values='@json($mesure['values'])'
                                data-unit="{{ $mesure['unit'] }}"></canvas>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    @vite('resources/js/obd-stats.js')
@endsection
