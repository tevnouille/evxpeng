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
        .etat {
            font-size: clamp(.7rem, 2.4vh, 1rem); font-weight: 600;
            padding: .25em .8em; border-radius: 999px; white-space: nowrap;
        }
        .etat.charge   { background: #d6f5e3; color: #14663f; }
        .etat.route    { background: #d9ecfb; color: #14568a; }
        .etat.arret    { background: #ececec; color: #4a4a4a; }
        .etat.silence  { background: #fdf0d5; color: #7a5200; }
        /* Cibles tactiles : on les vise d'un doigt, en conduisant si besoin.
           A la ligne sous le titre : quatre onglets ne tenaient plus a cote du
           badge d'etat sans se comprimer illisiblement. */
        .onglets { display: flex; flex-wrap: wrap; gap: 1vw; margin: 0 0 1.2vh; flex: 0 0 auto; }
        .onglets button {
            font: inherit; font-size: clamp(.75rem, 2.6vh, 1.05rem); font-weight: 600;
            padding: .5em 1.4em; border: 1px solid #d0d0d0; border-radius: 999px;
            background: #f4f4f4; color: #4a4a4a; cursor: pointer;
        }
        .onglets button[aria-current="page"] { background: #2ea36b; border-color: #2ea36b; color: #fff; }
        .fraicheur {
            flex: 0 0 auto; margin: 0 0 .7vh;
            font-size: clamp(.55rem, 1.8vh, .85rem); color: #6b6b6b;
        }
        /* Compte a rebours du rechargement. Sur un ecran ou rien ne bouge entre
           deux relevas, une barre qui avance dit deux choses d'un coup d'oeil :
           quand la page sera renouvelee, et que le navigateur n'a pas suspendu
           ses minuteries. Figee, elle designe elle-meme la panne. */
        .attente {
            flex: 0 0 auto; margin: 0 0 2vh;
            height: clamp(.15rem, .7vh, .35rem); border-radius: 999px;
            background: #e6e6e6; overflow: hidden;
        }
        .attente span {
            display: block; height: 100%; width: 0;
            background: #b4b4b4;
            /* La largeur vient de l'horloge, pas d'une animation CSS : une
               animation repartirait de zero apres une mise en veille et
               annoncerait un delai qui n'existe plus. La transition ne fait
               qu'adoucir le pas entre deux battements. */
            transition: width .2s linear;
        }
        .attente.suspendue { opacity: .45; }
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
        /* Un nom de commune se coupe plutot que de deborder : « Corbeil-
           Essonnes » ne tient pas sur un tiers d'ecran etroit, et rien ici ne
           defile pour aller le rechercher. */
        .moyenne.texte {
            font-size: clamp(.85rem, 3.8vh, 1.6rem);
            overflow-wrap: break-word; hyphens: auto;
        }
        .note { font-size: clamp(.55rem, 1.6vh, .8rem); color: #6b6b6b; margin: .2em 0 0; }
        /* Un fondu court a la releve : sans lui, la valeur change d'un coup et
           se lit comme une mesure qui vient de bouger. */
        .alterne > div:not([hidden]) { animation: alterne-apparait .4s ease-out; }
        @keyframes alterne-apparait {
            from { opacity: 0; }
            to   { opacity: 1; }
        }
        .jauge {
            width: 100%; height: clamp(.25rem, 1vh, .5rem); border-radius: 999px;
            background: #e6e6e6; overflow: hidden; margin-top: .5vh;
        }
        /* Degrade qui traverse la barre en continu, meme a valeur fixe : elle
           reste vivante sans rien affirmer de faux. Un simple deplacement de
           background-position, ce que tout moteur sait faire depuis longtemps —
           le navigateur embarque de la voiture n'a pas a etre recent. */
        .jauge span {
            display: block; height: 100%;
            background-color: #2ea36b;
            background-image: linear-gradient(
                100deg,
                #2ea36b 0%, #2ea36b 38%,
                #7ee2b0 50%,
                #2ea36b 62%, #2ea36b 100%
            );
            background-size: 300% 100%;
            background-repeat: no-repeat;
            animation: jauge-flux 3.2s linear infinite;
        }
        @keyframes jauge-flux {
            from { background-position: 200% 0; }
            to   { background-position: -100% 0; }
        }
        /* En charge, les rayures remplacent le degrade : elles vont plus vite et
           portent une information de plus — l'energie entre. Elles ecrasent les
           trois proprietes du degrade, aucune superposition a gerer. */
        .jauge span.charge {
            background-image: linear-gradient(
                135deg,
                rgba(255, 255, 255, .35) 25%, transparent 25%,
                transparent 50%, rgba(255, 255, 255, .35) 50%,
                rgba(255, 255, 255, .35) 75%, transparent 75%, transparent
            );
            background-size: 1.1em 1.1em;
            animation: jauge-defile .9s linear infinite;
        }
        @keyframes jauge-defile {
            from { background-position: 0 0; }
            to   { background-position: 1.1em 0; }
        }
        @media (prefers-reduced-motion: reduce) {
            .jauge span, .jauge span.charge { animation: none; }
            .alterne > div:not([hidden]) { animation: none; }
        }
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
        #courbe-autonomie { width: 100%; flex: 1 1 auto; min-height: 0; }
        .atteint { color: #2ea36b; font-weight: 600; }
        .inconnu { color: #9a9a9a; font-weight: 400; }
        @media (prefers-color-scheme: dark) {
            body { background: #16181c; color: #f0f0f0; }
            .titre, .note, .fraicheur, .valeur .unite, thead th { color: #a0a4ab; }
            .jauge, .attente { background: #2c3037; }
            .attente span { background: #5a6069; }
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
        <span class="etat {{ $classes[$state['state']] ?? 'arret' }}">{{ $state['label'] }}</span>
    </header>

    <div class="onglets">
        <button type="button" data-vue="info" aria-current="page">Info</button>
        <button type="button" data-vue="recharge">Recharge</button>
        <button type="button" data-vue="courbe">Courbe</button>
        @if ($position)
            <button type="button" data-vue="position">Position</button>
        @endif
    </div>

    {{-- Sous le titre et non en pied de page : sur un ecran qu'on ne peut ni
         defiler ni dezoomer, la fraicheur du releve doit se lire d'emblee.
         C'est elle qui dit si les chiffres en dessous valent quelque chose. --}}
    <p class="fraicheur">
        Dernier relevé {{ $telemetry->recorded_at->diffForHumans() }}
        ({{ $telemetry->recorded_at->timezone(config('app.timezone'))->format('d/m H:i:s') }})
        &middot; <span id="cadence">page réactualisée toutes les {{ $refreshSeconds }} s</span>
    </p>
    <div class="attente" id="attente" aria-hidden="true"><span></span></div>

    <div class="vue" id="vue-info">
        <div class="grille">
            <div>
                <p class="titre">Batterie</p>
                <p class="valeur">
                    {{ $soc !== null ? rtrim(rtrim(number_format($soc, 1, ',', ' '), '0'), ',') : '—' }}<span class="unite"> %</span>
                </p>
                <div class="jauge"><span class="{{ ($state['state'] ?? null) === 'charging' ? 'charge' : '' }}" style="width: {{ max(0, min(100, (int) round($soc ?? 0))) }}%"></span></div>
            </div>

            {{-- Deux informations pour une seule case, alternees toutes les cinq
                 secondes : sur un ecran qui ne defile pas, la place est comptee.
                 Le rang de la face vient de l'horloge et non d'un compteur
                 remis a zero au chargement — la page se recharge toutes les
                 cinq secondes en charge, et la seconde face n'apparaitrait
                 jamais. --}}
            <div class="alterne" data-periode="5">
                <div>
                    <p class="titre">Autonomie estimée</p>
                    <p class="valeur">{{ $rangeKm !== null ? $rangeKm : '—' }}<span class="unite"> km</span></p>
                    @if ($availableKwh !== null)
                        <p class="note">{{ str_replace('.', ',', (string) $availableKwh) }} kWh disponibles</p>
                    @endif
                </div>

                @if ($telemetry->soh !== null)
                    <div hidden>
                        <p class="titre">Santé batterie</p>
                        <p class="valeur">{{ str_replace('.', ',', rtrim(rtrim((string) $telemetry->soh, '0'), '.')) }}<span class="unite"> %</span></p>
                        <p class="note">capacité annoncée par la batterie</p>
                    </div>
                @endif
            </div>

            @if ($ville !== null)
                <div>
                    <p class="titre">Commune</p>
                    <p class="moyenne texte">{{ $ville }}</p>
                    <p class="note">d'après la position relevée</p>
                </div>
            @endif

            @if ($ecartCellules !== null)
                <div class="alterne" data-periode="5">
                    <div>
                        <p class="titre">Écart entre cellules</p>
                        <p class="valeur">{{ $ecartCellules }}<span class="unite"> mV</span></p>
                        {{-- La mediane est dite, parce qu'elle change le sens du
                             chiffre : ce n'est pas l'ecart de l'instant. --}}
                        <p class="note">médiane des dernières 24 h</p>
                    </div>
                </div>
            @endif

            @if ($telemetry->batt_temp !== null || $telemetry->power_kw !== null)
                <div class="alterne" data-periode="5">
                    @if ($telemetry->batt_temp !== null)
                        <div>
                            <p class="titre">Température batterie</p>
                            <p class="valeur">{{ str_replace('.', ',', rtrim(rtrim((string) $telemetry->batt_temp, '0'), '.')) }}<span class="unite"> °C</span></p>
                        </div>
                    @endif

                    @if ($telemetry->power_kw !== null)
                        <div @if ($telemetry->batt_temp !== null) hidden @endif>
                            <p class="titre">Puissance</p>
                            <p class="valeur">{{ str_replace('.', ',', (string) round(abs((float) $telemetry->power_kw), 1)) }}<span class="unite"> kW</span></p>
                            <p class="note">{{ (float) $telemetry->power_kw < 0 ? 'entrante' : 'consommée' }}</p>
                        </div>
                    @endif
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

    <div class="vue" id="vue-courbe" hidden>
        @if ($rangeChart === null)
            <p class="note">
                Pas assez de données pour tracer l'autonomie
                (consommation non renseignée sur la fiche du véhicule, ou historique insuffisant).
            </p>
        @else
            <svg id="courbe-autonomie" viewBox="0 0 {{ $rangeChart['width'] }} {{ $rangeChart['height'] }}"
                 preserveAspectRatio="none" role="img"
                 aria-label="Autonomie estimée entre {{ $rangeChart['km_min'] }} et {{ $rangeChart['km_max'] }} km sur les dernières 24 heures">
                <polyline points="{{ $rangeChart['points'] }}" fill="none" stroke="#2ea36b"
                          stroke-width="3" stroke-linejoin="round" stroke-linecap="round" />
            </svg>
            <p class="note">
                Autonomie estimée de {{ $rangeChart['km_min'] }} à {{ $rangeChart['km_max'] }} km,
                de {{ $rangeChart['debut']->timezone(config('app.timezone'))->format('H:i') }}
                à {{ $rangeChart['fin']->timezone(config('app.timezone'))->format('H:i') }}.
                Une pente montante en fin de courbe signale une charge en cours.
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

        var ongletDepuisAncre = window.location.hash.replace('#', '');
        afficher(['recharge', 'courbe', 'position'].indexOf(ongletDepuisAncre) !== -1 ? ongletDepuisAncre : 'info');
    })();

    (function () {
        var cases = document.querySelectorAll('.alterne');

        /*
         * Le rang de la face se calcule sur l'heure absolue plutot que sur un
         * compteur local : la page se recharge toute seule, parfois toutes les
         * cinq secondes, et un compteur reparti de zero aurait toujours montre
         * la meme face. Ainsi la releve tombe toujours au meme moment, qu'un
         * rechargement soit intervenu ou non.
         *
         * Chaque case est decalee d'une fraction de la periode, de sorte que
         * les releves s'etalent au lieu de tomber toutes ensemble. Le decalage
         * vient du rang dans le document : il ne bouge pas d'un chargement a
         * l'autre, une case ne se met donc pas a battre de travers.
         */
        function afficher() {
            for (var i = 0; i < cases.length; i++) {
                var faces = cases[i].children;

                if (faces.length < 2) { continue; }

                var periode = (parseInt(cases[i].dataset.periode, 10) || 10) * 1000;
                var decalage = periode / cases.length * i;
                var rang = Math.floor((Date.now() + decalage) / periode) % faces.length;

                for (var f = 0; f < faces.length; f++) {
                    faces[f].hidden = f !== rang;
                }
            }
        }

        setInterval(afficher, 500);
        afficher();
    })();

    var rechargement = (function () {
        var duree = {{ $refreshSeconds }} * 1000;
        var echeance = Date.now() + duree;
        var barre = document.getElementById('attente');
        var jauge = barre ? barre.firstElementChild : null;
        var cadence = document.getElementById('cadence');
        var texte = cadence ? cadence.textContent : '';
        var suspendu = false;
        var lance = false;

        /*
         * Le rechargement se decide sur l'horloge et non sur un delai pose une
         * fois : le navigateur de la voiture endort ses minuteries des que
         * l'ecran s'eteint ou que l'onglet passe derriere, et un setTimeout
         * d'une minute pouvait alors ne jamais aboutir — la page restait
         * affichee avec des chiffres vieux d'une heure. Un battement court qui
         * compare deux dates repart juste apres une suspension, et recharge des
         * la reprise si l'echeance est deja passee.
         */
        function battre() {
            if (suspendu) { return; }

            var reste = echeance - Date.now();

            if (jauge) {
                var part = (1 - reste / duree) * 100;
                jauge.style.width = (part < 0 ? 0 : (part > 100 ? 100 : part)).toFixed(1) + '%';
            }

            /*
             * Une seule fois, et le battement s'arrete. Le navigateur met du
             * temps a aller chercher la page sur un lien mobile, et pendant ce
             * temps la page en cours continue de tourner : sans ce garde-fou,
             * reload() repartait cinq fois par seconde, chaque requete annulant
             * la precedente jusqu'a ce que le serveur oppose sa limite de debit.
             * Mesure sur la voiture : 30 a 58 requetes par minute au lieu de six,
             * et des reponses 429 en rafale.
             */
            if (reste <= 0 && ! lance) {
                lance = true;
                clearInterval(battement);
                window.location.reload();
            }
        }

        var battement = setInterval(battre, 200);
        battre();

        // Revenir sur la page par le bouton « precedent » la restaure telle
        // quelle, minuteries comprises : mieux vaut la recharger aussitot.
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) { window.location.reload(); }
        });

        return {
            suspendre: function () {
                suspendu = true;
                if (barre) { barre.classList.add('suspendue'); }
                if (jauge) { jauge.style.width = '0%'; }
                if (cadence) { cadence.textContent = 'réactualisation suspendue tant que la carte est affichée'; }
            },
            reprendre: function () {
                if (!suspendu) { return; }
                suspendu = false;
                echeance = Date.now() + duree;
                if (barre) { barre.classList.remove('suspendue'); }
                if (cadence) { cadence.textContent = texte; }
                battre();
            },
        };
    })();

    (function () {
        var vue = document.getElementById('vue-position');

        if (!vue) { return; }

        var boutons = document.querySelectorAll('.onglets button');

        for (var i = 0; i < boutons.length; i++) {
            boutons[i].addEventListener('click', function (e) {
                if (e.currentTarget.dataset.vue !== 'position') {
                    // Quitter la carte remet le compte a rebours en marche : le
                    // suspendre sans jamais le reprendre laissait la page figee
                    // pour de bon des qu'on avait regarde la position une fois.
                    rechargement.reprendre();
                    return;
                }

                // Le rechargement est suspendu tant que la carte est affichee :
                // elle serait reconstruite toutes les minutes, et rappellerait
                // OpenStreetMap a chaque fois.
                rechargement.suspendre();

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
            rechargement.suspendre();
            var declencheur = document.querySelector('.onglets button[data-vue=\"position\"]');
            if (declencheur) { declencheur.click(); }
        }
    })();
</script>
</body>
</html>
