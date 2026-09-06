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
        /*
         * Le navigateur de la voiture ne permet ni de zoomer ni de defiler :
         * tout doit tenir dans un ecran, dont on ignore la hauteur. Les tailles
         * sont donc exprimees en fraction de la hauteur de fenetre, bornees par
         * clamp() pour rester lisibles aux extremes. Une taille fixe, meme
         * divisee par trois, deborderait encore sur un ecran plus court.
         */
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            margin: 0; padding: 2.5vh 3vw;
            display: flex; flex-direction: column;
            /* Rien ne depasse : ce qui ne tient pas serait invisible. */
            overflow: hidden;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #fff; color: #1a1a1a;
        }
        header {
            display: flex; align-items: baseline; justify-content: space-between;
            gap: 1rem; margin-bottom: .8vh; flex: 0 0 auto;
        }
        h1 { font-size: clamp(.9rem, 4vh, 1.8rem); margin: 0; }
        .etat {
            font-size: clamp(.7rem, 2.4vh, 1rem); font-weight: 600;
            padding: .25em .8em; border-radius: 999px; white-space: nowrap;
        }
        .etat.charge   { background: #d6f5e3; color: #14663f; }
        .etat.route    { background: #d9ecfb; color: #14568a; }
        .etat.arret    { background: #ececec; color: #4a4a4a; }
        .etat.silence  { background: #fdf0d5; color: #7a5200; }
        /* Le tableau occupe la hauteur restante et y repartit ses lignes. */
        /*
         * Trois colonnes plutot qu'un `auto-fit` : sur un ecran large, celui-ci
         * alignait six tuiles sur une seule rangee et en laissait une seule sur
         * la suivante, avec de grands vides. Deux rangees de trois remplissent
         * la hauteur, ce qui autorise des caracteres plus grands.
         */
        .grille {
            flex: 1 1 auto; display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5vh 3vw; align-content: space-evenly; min-height: 0;
        }
        @media (max-width: 700px) { .grille { grid-template-columns: repeat(2, 1fr); } }
        .titre {
            font-size: clamp(.55rem, 1.6vh, .75rem); text-transform: uppercase;
            letter-spacing: .06em; color: #6b6b6b; margin: 0 0 .15em;
        }
        .valeur { font-size: clamp(1.1rem, 7.5vh, 2.8rem); font-weight: 700; line-height: 1.05; margin: 0; }
        .valeur .unite { font-size: .45em; font-weight: 400; color: #6b6b6b; }
        .moyenne { font-size: clamp(.95rem, 5.2vh, 2.1rem); font-weight: 600; line-height: 1.1; margin: 0; }
        .note { font-size: clamp(.55rem, 1.6vh, .8rem); color: #6b6b6b; margin: .2em 0 0; }
        .jauge {
            width: 100%; height: clamp(.25rem, 1vh, .5rem); border-radius: 999px;
            background: #e6e6e6; overflow: hidden; margin-top: .5vh;
        }
        .jauge span { display: block; height: 100%; background: #2ea36b; }
        .fraicheur {
            flex: 0 0 auto; margin: 0 0 2vh;
            font-size: clamp(.55rem, 1.8vh, .85rem); color: #6b6b6b;
        }
        @media (prefers-color-scheme: dark) {
            body { background: #16181c; color: #f0f0f0; }
            .titre, .note, .fraicheur, .valeur .unite { color: #a0a4ab; }
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

    {{-- Sous le titre et non en pied de page : sur un ecran qu'on ne peut ni
         defiler ni dezoomer, la fraicheur du releve doit se lire d'emblee.
         C'est elle qui dit si les chiffres en dessous valent quelque chose. --}}
    <p class="fraicheur">
        Dernier relevé {{ $telemetry->recorded_at->diffForHumans() }}
        ({{ $telemetry->recorded_at->timezone(config('app.timezone'))->format('d/m H:i:s') }})
        &middot; page réactualisée toutes les {{ $refreshSeconds }} s
    </p>

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
@endif

<script>
    setTimeout(function () { window.location.reload(); }, {{ $refreshSeconds }} * 1000);
</script>
</body>
</html>
