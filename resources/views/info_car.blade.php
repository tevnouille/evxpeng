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
            display: flex; align-items: center; justify-content: space-between;
            gap: 2vw; margin-bottom: .8vh; flex: 0 0 auto;
        }
        h1 { font-size: clamp(.9rem, 4vh, 1.8rem); margin: 0; }
        .droite { display: flex; align-items: center; gap: 1.5vw; }
        .etat {
            font-size: clamp(.7rem, 2.4vh, 1rem); font-weight: 600;
            padding: .25em .8em; border-radius: 999px; white-space: nowrap;
        }
        .etat.charge   { background: #d6f5e3; color: #14663f; }
        .etat.route    { background: #d9ecfb; color: #14568a; }
        .etat.arret    { background: #ececec; color: #4a4a4a; }
        .etat.silence  { background: #fdf0d5; color: #7a5200; }
        /* Cibles tactiles : on les vise d'un doigt, en conduisant si besoin. */
        .onglets { display: flex; gap: 1vw; }
        .onglets button {
            font: inherit; font-size: clamp(.75rem, 2.6vh, 1.05rem); font-weight: 600;
            padding: .5em 1.4em; border: 1px solid #d0d0d0; border-radius: 999px;
            background: #f4f4f4; color: #4a4a4a; cursor: pointer;
        }
        .onglets button[aria-current="page"] { background: #2ea36b; border-color: #2ea36b; color: #fff; }
        .fraicheur {
            flex: 0 0 auto; margin: 0 0 2vh;
            font-size: clamp(.55rem, 1.8vh, .85rem); color: #6b6b6b;
        }
        .vue { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; }
        .vue[hidden] { display: none; }
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
        /* Le tableau occupe la hauteur libre et y repartit ses lignes. */
        table { flex: 1 1 auto; width: 100%; border-collapse: collapse; }
        th, td { text-align: right; padding: .3em .5em; }
        th:first-child, td:first-child { text-align: left; }
        thead th {
            font-size: clamp(.55rem, 1.8vh, .85rem); text-transform: uppercase;
            letter-spacing: .06em; color: #6b6b6b; font-weight: 600;
            border-bottom: 1px solid #dcdcdc;
        }
        tbody td { font-size: clamp(.85rem, 3.6vh, 1.5rem); font-weight: 600; }
        tbody td:first-child { font-weight: 700; }
        tbody tr + tr td { border-top: 1px solid #efefef; }
        #carte { height: 100%; min-height: 55vh; }
        #carte iframe { width: 100%; height: 100%; min-height: 55vh; border: 1px solid #dcdcdc; border-radius: 8px; }
        .atteint { color: #2ea36b; font-weight: 600; }
        .inconnu { color: #9a9a9a; font-weight: 400; }
        @media (prefers-color-scheme: dark) {
            body { background: #16181c; color: #f0f0f0; }
            .titre, .note, .fraicheur, .valeur .unite, thead th { color: #a0a4ab; }
            .jauge { background: #2c3037; }
            .onglets button { background: #24272d; border-color: #3a3f47; color: #d5d8dd; }
            thead th { border-bottom-color: #3a3f47; }
            #carte iframe { border-color: #3a3f47; }
            tbody tr + tr td { border-top-color: #24272d; }
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
        $duree = function (?int $secondes): string {
            if ($secondes === null) { return ''; }
            $minutes = (int) round($secondes / 60);
            return $minutes < 60
                ? $minutes . ' min'
                : intdiv($minutes, 60) . ' h ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
        };
    @endphp
    <header>
        <h1>{{ $vehicle->name }}</h1>
        <div class="droite">
            <span class="etat {{ $classes[$state['state']] ?? 'arret' }}">{{ $state['label'] }}</span>
            <div class="onglets">
                <button type="button" data-vue="info" aria-current="page">Info</button>
                <button type="button" data-vue="recharge">Recharge</button>
                @if ($position)
                    <button type="button" data-vue="position">Position</button>
                @endif
            </div>
        </div>
    </header>

    {{-- Sous le titre et non en pied de page : sur un ecran qu'on ne peut ni
         defiler ni dezoomer, la fraicheur du releve doit se lire d'emblee.
         C'est elle qui dit si les chiffres en dessous valent quelque chose. --}}
    <p class="fraicheur">
        Dernier relevé {{ $telemetry->recorded_at->diffForHumans() }}
        ({{ $telemetry->recorded_at->timezone(config('app.timezone'))->format('d/m H:i:s') }})
        &middot; page réactualisée toutes les {{ $refreshSeconds }} s
    </p>

    <div class="vue" id="vue-info">
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
    </div>

    <div class="vue" id="vue-recharge" hidden>
        @if (empty($recharges))
            <p class="note">Temps de charge indisponible : aucune courbe de recharge associée au véhicule.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Borne</th>
                        @foreach ($cibles as $cible)
                            <th>→ {{ $cible }} %</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recharges as $ligne)
                        <tr>
                            <td>{{ str_replace('.', ',', rtrim(rtrim(number_format($ligne['puissance'], 1, '.', ''), '0'), '.')) }} kW</td>
                            @foreach ($cibles as $cible)
                                @php($secondes = $ligne['durees'][$cible] ?? null)
                                <td>
                                    @if ($soc !== null && $soc >= $cible)
                                        <span class="atteint">atteint</span>
                                    @elseif ($secondes === null)
                                        <span class="inconnu">—</span>
                                    @else
                                        {{ $duree($secondes) }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="note">
                Depuis {{ $soc !== null ? rtrim(rtrim(number_format($soc, 1, ',', ' '), '0'), ',') : '—' }} %,
                courbe du véhicule bridée à la puissance de la borne. Estimation : la puissance réelle dépend
                aussi de la température de la batterie.
            </p>
        @endif
    </div>

    @if ($position)
        {{-- La carte n'est inseree qu'a l'ouverture de l'onglet : sans ce
             garde-fou, chaque affichage — et la page se recharge seule —
             enverrait la position du vehicule a OpenStreetMap. --}}
        <div class="vue" id="vue-position" hidden
             data-lat="{{ $position['lat'] }}" data-lon="{{ $position['lon'] }}">
            <div id="carte"></div>
            <p class="note">
                {{ number_format($position['lat'], 5, ',', ' ') }},
                {{ number_format($position['lon'], 5, ',', ' ') }}
                &middot;
                <a target="_blank" rel="noopener"
                   href="https://www.openstreetmap.org/?mlat={{ $position['lat'] }}&mlon={{ $position['lon'] }}#map=15/{{ $position['lat'] }}/{{ $position['lon'] }}">ouvrir dans OpenStreetMap</a>
            </p>
        </div>
    @endif
@endif

<script>
    // Les deux vues sont rendues d'avance et permutees ici : sur un reseau
    // mobile, un aller-retour serveur pour changer d'onglet se sentirait.
    (function () {
        var boutons = document.querySelectorAll('.onglets button');

        function afficher(nom) {
            for (var i = 0; i < boutons.length; i++) {
                var actif = boutons[i].dataset.vue === nom;
                boutons[i].setAttribute('aria-current', actif ? 'page' : 'false');
                var vue = document.getElementById('vue-' + boutons[i].dataset.vue);
                if (vue) { vue.hidden = !actif; }
            }
            // L'ancre survit au rechargement automatique : rester sur l'onglet
            // Recharge pendant qu'on branche la voiture n'aurait aucun sens
            // sinon.
            if (window.location.hash !== '#' + nom) { window.location.hash = nom; }
        }

        for (var i = 0; i < boutons.length; i++) {
            boutons[i].addEventListener('click', function (e) { afficher(e.currentTarget.dataset.vue); });
        }

        afficher(window.location.hash === '#recharge' ? 'recharge' : 'info');
    })();

    (function () {
        var vue = document.getElementById('vue-position');
        var recharge = setTimeout(function () { window.location.reload(); }, {{ $refreshSeconds }} * 1000);

        if (!vue) { return; }

        var boutons = document.querySelectorAll('.onglets button');

        for (var i = 0; i < boutons.length; i++) {
            boutons[i].addEventListener('click', function (e) {
                if (e.currentTarget.dataset.vue !== 'position') { return; }

                // Le rechargement est suspendu tant que la carte est affichee :
                // elle serait reconstruite toutes les minutes, et rappellerait
                // OpenStreetMap a chaque fois.
                clearTimeout(recharge);

                var carte = document.getElementById('carte');
                if (carte.childElementCount > 0) { return; }

                var lat = parseFloat(vue.dataset.lat);
                var lon = parseFloat(vue.dataset.lon);
                var d = 0.006;
                var cadre = document.createElement('iframe');
                cadre.src = 'https://www.openstreetmap.org/export/embed.html?bbox='
                    + [lon - d, lat - d, lon + d, lat + d].join(',')
                    + '&layer=mapnik&marker=' + lat + ',' + lon;
                cadre.loading = 'lazy';
                cadre.referrerPolicy = 'no-referrer';
                carte.appendChild(cadre);
            });
        }

        // L'ancre survit au rechargement : arriver directement sur l'onglet
        // Position doit inserer la carte, sans attendre un clic qui n'aura pas lieu.
        if (window.location.hash === '#position') {
            clearTimeout(recharge);
            var declencheur = document.querySelector('.onglets button[data-vue=\"position\"]');
            if (declencheur) { declencheur.click(); }
        }
    })();
</script>
</body>
</html>
