@extends('layouts.app')

@section('title', 'Comparaison des recharges')

@section('content')
    <h1 class="title">Recharges face à la courbe</h1>

    <p class="is-size-7 has-text-grey mb-5">
        Chaque recharge d'au moins {{ (int) $minKwh }} kWh, superposée à la courbe de recharge du véhicule.
        C'est ce que la courbe seule ne montre pas&nbsp;: une charge qui n'atteint pas ce que la batterie
        devrait accepter — borne bridée, batterie froide, cellule faible — se voit d'un coup d'œil.
    </p>

    @if (! $vehicle || ! $curve)
        <div class="notification is-info is-light">
            Aucun véhicule ne dispose à la fois d'une courbe de recharge et d'un boîtier OBD.
        </div>
    @else
        @if ($vehicles->count() > 1)
            <div class="field mb-5">
                <div class="control">
                    <div class="select">
                        <select onchange="window.location.href = '{{ route('charging-curves.compare') }}?vehicule=' + this.value">
                            @foreach ($vehicles as $v)
                                <option value="{{ $v->id }}" @selected($v->id === $vehicle->id)>{{ $v->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        @endif

        @if (empty($sessions))
            <div class="notification is-info is-light">
                Aucune recharge d'au moins {{ (int) $minKwh }} kWh relevée par le boîtier pour l'instant.
                Seules les recharges qu'il a vues de bout en bout peuvent être confrontées à la courbe.
            </div>
        @else
            @foreach ($sessions as $comparaison)
                @php($s = $comparaison['session'])
                <div class="box">
                    <div class="level is-mobile mb-2">
                        <div class="level-left">
                            <h2 class="title is-5 mb-0">
                                {{ $s->started_at->translatedFormat('l j F Y') }}
                                <span class="has-text-grey is-size-6">
                                    {{ $s->started_at->format('H:i') }}
                                </span>
                            </h2>
                        </div>
                        <div class="level-right">
                            <span class="tag {{ $comparaison['alternating'] ? 'is-light' : 'is-primary is-light' }} is-medium">
                                {{ strtoupper($s->charging_type ?? '?') }}
                            </span>
                        </div>
                    </div>

                    <p class="has-text-grey is-size-7 mb-3">
                        {{ str_replace('.', ',', (string) $s->energy_kwh) }} kWh
                        &middot; {{ (int) $s->soc_start }} % &rarr; {{ (int) $s->soc_end }} %
                        &middot; {{ intdiv((int) $s->duration_seconds, 60) }} min
                        &middot; pointe mesurée
                        <strong>{{ str_replace('.', ',', (string) $comparaison['peak']) }} kW</strong>
                    </p>

                    @if ($comparaison['alternating'])
                        <div class="notification is-warning is-light is-size-7 py-2 px-3 mb-3">
                            Charge en <strong>courant alternatif</strong>&nbsp;: la puissance est imposée par la borne
                            et le chargeur embarqué, jamais par la batterie. La référence est donc ramenée à
                            {{ str_replace('.', ',', (string) $comparaison['capped_at']) }} kW — la question n'est pas
                            « la batterie encaisse-t-elle&nbsp;? » mais « la puissance a-t-elle tenu&nbsp;? ».
                        </div>
                    @elseif ($comparaison['worst'] && $comparaison['worst']['gap'] > 0)
                        <div class="notification is-danger is-light is-size-7 py-2 px-3 mb-3">
                            Écart le plus marqué à <strong>{{ str_replace('.', ',', (string) $comparaison['worst']['soc']) }} %</strong>&nbsp;:
                            {{ str_replace('.', ',', (string) $comparaison['worst']['measured']) }} kW reçus
                            contre {{ str_replace('.', ',', (string) $comparaison['worst']['expected']) }} kW attendus,
                            soit {{ str_replace('.', ',', (string) $comparaison['worst']['gap']) }} kW de moins.
                            Une borne bridée, une batterie froide ou un départ de charge expliquent la plupart des écarts.
                        </div>
                    @endif

                    <canvas class="curve-compare" height="220"
                        data-measured='@json($comparaison['measured'])'
                        data-reference='@json($comparaison['reference'])'></canvas>
                </div>
            @endforeach
        @endif
    @endif

    @vite('resources/js/curve-compare.js')
@endsection
