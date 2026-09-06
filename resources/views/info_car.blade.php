<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $vehicle?->name ?? 'Véhicule' }}</title>
    {{-- Page entierement autonome : aucune feuille de style ni script externe.
         Les assets du site sont derriere la passerelle passkey — les ouvrir
         pour cette page aurait expose tout le front. Et sur le reseau mobile
         d'une voiture, une seule requete vaut mieux que quatre. --}}
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 1.5rem;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #fff; color: #1a1a1a;
        }
        header { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; margin-bottom: 1.5rem; }
        h1 { font-size: 1.9rem; margin: 0; }
        .etat { font-size: 1.1rem; font-weight: 600; padding: .35rem .9rem; border-radius: 999px; white-space: nowrap; }
        .etat.charge   { background: #d6f5e3; color: #14663f; }
        .etat.route    { background: #d9ecfb; color: #14568a; }
        .etat.arret    { background: #ececec; color: #4a4a4a; }
        .etat.silence  { background: #fdf0d5; color: #7a5200; }
        .grille { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.6rem 2rem; }
        .titre { font-size: .8rem; text-transform: uppercase; letter-spacing: .07em; color: #6b6b6b; margin: 0 0 .2rem; }
        .valeur { font-size: 3.2rem; font-weight: 700; line-height: 1; margin: 0; }
        .valeur .unite { font-size: 1.4rem; font-weight: 400; color: #6b6b6b; }
        .moyenne { font-size: 2rem; font-weight: 600; margin: 0; }
        .note { font-size: .9rem; color: #6b6b6b; margin: .25rem 0 0; }
        .jauge { width: 100%; height: .7rem; border-radius: 999px; background: #e6e6e6; overflow: hidden; margin-top: .6rem; }
        .jauge span { display: block; height: 100%; background: #2ea36b; }
        footer { margin-top: 2rem; font-size: .9rem; color: #6b6b6b; }
        @media (prefers-color-scheme: dark) {
            body { background: #16181c; color: #f0f0f0; }
            .titre, .note, footer, .valeur .unite { color: #a0a4ab; }
            .jauge { background: #2c3037; }
        }
    </style>
</head>
<body>
@if (! $vehicle || ! $telemetry)
    <h1>Aucun relevé disponible</h1>
    <p class="note">Le boîtier n'a encore rien publié.</p>
@else
    @php
        $classes = ['charging' => 'charge', 'driving' => 'route', 'parked' => 'arret', 'offline' => 'silence'];
    @endphp
    <header>
        <h1>{{ $vehicle->name }}</h1>
        <span class="etat {{ $classes[$state['state']] ?? 'arret' }}">{{ $state['label'] }}</span>
    </header>

    <div class="grille">
        <div>
            <p class="titre">Batterie</p>
            <p class="valeur">
                {{ $soc !== null ? rtrim(rtrim(number_format($soc, 1, ',', ' '), '0'), ',') : '—' }}<span class="unite"> %</span>
            </p>
            <div class="jauge"><span style="width: {{ max(0, min(100, (int) round($soc ?? 0))) }}%"></span></div>
        </div>

        <div>
            <p class="titre">Autonomie estimée</p>
            <p class="valeur">{{ $rangeKm !== null ? $rangeKm : '—' }}<span class="unite"> km</span></p>
            @if ($availableKwh !== null)
                <p class="note">{{ str_replace('.', ',', (string) $availableKwh) }} kWh disponibles</p>
            @endif
        </div>

        @if ($telemetry->odometer !== null)
            <div>
                <p class="titre">Compteur</p>
                <p class="moyenne">{{ number_format($telemetry->odometer, 0, ',', ' ') }} km</p>
            </div>
        @endif

        @if ($telemetry->soh !== null)
            <div>
                <p class="titre">Santé batterie</p>
                <p class="moyenne">{{ str_replace('.', ',', rtrim(rtrim((string) $telemetry->soh, '0'), '.')) }} %</p>
            </div>
        @endif

        @if ($telemetry->power_kw !== null)
            <div>
                <p class="titre">Puissance</p>
                <p class="moyenne">{{ str_replace('.', ',', (string) round(abs((float) $telemetry->power_kw), 1)) }} kW</p>
                <p class="note">{{ (float) $telemetry->power_kw < 0 ? 'entrante' : 'consommée' }}</p>
            </div>
        @endif

        @if ($telemetry->batt_temp !== null)
            <div>
                <p class="titre">Température batterie</p>
                <p class="moyenne">{{ str_replace('.', ',', rtrim(rtrim((string) $telemetry->batt_temp, '0'), '.')) }} °C</p>
            </div>
        @endif
    </div>

    <footer>
        Dernier relevé {{ $telemetry->recorded_at->diffForHumans() }}
        ({{ $telemetry->recorded_at->timezone(config('app.timezone'))->format('d/m H:i:s') }})
        &middot; page réactualisée toutes les {{ $refreshSeconds }} s
    </footer>
@endif

<script>
    setTimeout(function () { window.location.reload(); }, {{ $refreshSeconds }} * 1000);
</script>
</body>
</html>
