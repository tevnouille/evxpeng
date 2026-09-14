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
        /* Le titre ouvre le plein ecran : pas garanti sur le navigateur
           embarque de la voiture ("on verra bien"), mais gratuit a tenter et
           sans consequence en cas d'echec silencieux. */
        header h1 { cursor: pointer; display: flex; align-items: center; gap: .3em; }
        header h1 svg { width: .55em; height: .55em; flex: 0 0 auto; opacity: .55; }
        /* Tuiles ouvrant un ecran par-dessus (batterie du jour, communes
           traversees) : memes cibles tactiles genereuses que le reste de la
           page, doigt sur ecran en roulant compris. */
        .tuile-cliquable { cursor: pointer; }
        .recouvrement {
            position: fixed; inset: 0; z-index: 20;
            display: none; align-items: center; justify-content: center;
            padding: 4vw; background: rgba(0, 0, 0, .55);
        }
        .recouvrement:not([hidden]) { display: flex; }
        .recouvrement .carte {
            background: #fff; border-radius: 12px; padding: 3.5vw 4vw;
            width: 100%; max-width: 640px; max-height: 90vh; overflow: auto;
            display: flex; flex-direction: column; gap: 1vh;
        }
        .recouvrement .entete {
            display: flex; align-items: center; justify-content: space-between;
            gap: 1em;
        }
        .recouvrement h2 { font-size: clamp(.9rem, 3.4vh, 1.3rem); margin: 0; }
        .recouvrement button.fermer {
            font: inherit; font-size: clamp(.9rem, 3vh, 1.2rem); line-height: 1;
            border: 1px solid #d0d0d0; border-radius: 999px; background: #f4f4f4;
            color: #4a4a4a; padding: .35em .7em; cursor: pointer;
        }
        #graphe-batterie { width: 100%; height: 36vh; }
        .liste-villes { list-style: none; margin: 0; padding: 0; }
        .liste-villes li {
            display: flex; justify-content: space-between; gap: 1em;
            padding: .5em 0; font-size: clamp(.85rem, 2.6vh, 1.1rem);
        }
        .liste-villes li + li { border-top: 1px solid #efefef; }
        .liste-villes .heure { color: #6b6b6b; font-weight: 400; white-space: nowrap; }
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
            --jauge-base: #2ea36b;
            --jauge-clair: #7ee2b0;
            background-color: var(--jauge-base);
            background-image: linear-gradient(
                100deg,
                var(--jauge-base) 0%, var(--jauge-base) 38%,
                var(--jauge-clair) 50%,
                var(--jauge-base) 62%, var(--jauge-base) 100%
            );
            background-size: 300% 100%;
            background-repeat: no-repeat;
            animation: jauge-flux 3.2s linear infinite;
        }
        @keyframes jauge-flux {
            from { background-position: 200% 0; }
            to   { background-position: -100% 0; }
        }
        /* Couleur de la jauge Batterie selon le niveau -- verte au-dessus de
           80 %, bleue entre 50 et 80, orange entre 20 et 50, rouge en dessous.
           Le bleu reprend celui deja utilise par le badge d'etat "route" plus
           haut, plutot qu'une nouvelle teinte propre a la jauge. Seules les
           deux couleurs du degrade changent : la classe .charge continue de
           les ecraser par-dessus pour les rayures, qu'elle qu'en soit la
           couleur. */
        .jauge span.jauge-mediane { --jauge-base: #14568a; --jauge-clair: #7cc0ea; }
        .jauge span.jauge-moyenne { --jauge-base: #cf8a12; --jauge-clair: #f4c869; }
        .jauge span.jauge-basse   { --jauge-base: #c0392b; --jauge-clair: #ef8a7d; }
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
            .recouvrement .carte { background: #20232a; }
            .recouvrement button.fermer { background: #24272d; border-color: #3a3f47; color: #d5d8dd; }
            .liste-villes li + li { border-top-color: #2c3037; }
            .liste-villes .heure { color: #a0a4ab; }
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
        $niveauBatterie = $soc === null
            ? ''
            : ($soc >= 80 ? 'jauge-haute' : ($soc >= 50 ? 'jauge-mediane' : ($soc >= 20 ? 'jauge-moyenne' : 'jauge-basse')));
    @endphp
    <header>
        {{-- data-plein-ecran plutot qu'un id : coherent avec data-ouvre plus
             bas, un seul mecanisme generique en JS pour tout ce qui reagit au
             toucher sur cette page. --}}
        <h1 data-plein-ecran>
            {{ $vehicle->name }}
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M8 21H5a2 2 0 0 1-2-2v-3M16 21h3a2 2 0 0 0 2-2v-3"/>
            </svg>
        </h1>
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
            {{-- Ouvre le graphique horodate du jour par-dessus. La tuile entiere
                 est la cible tactile, pas seulement le chiffre. --}}
            <div class="tuile-cliquable" data-ouvre="batterie" role="button" tabindex="0">
                <p class="titre">Batterie</p>
                <p class="valeur">
                    {{ $soc !== null ? rtrim(rtrim(number_format($soc, 1, ',', ' '), '0'), ',') : '—' }}<span class="unite"> %</span>
                </p>
                <div class="jauge"><span class="{{ $niveauBatterie }} {{ ($state['state'] ?? null) === 'charging' ? 'charge' : '' }}" style="width: {{ max(0, min(100, (int) round($soc ?? 0))) }}%"></span></div>
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
                <div @if (! empty($dernieresVilles)) class="tuile-cliquable" data-ouvre="villes" role="button" tabindex="0" @endif>
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
            {{-- Etiquettes dans le dessin lui-meme, pas dans un sous-titre a
                 part : un texte hors du SVG s'est revele repousse hors ecran
                 par le graphique, qui s'etire pour prendre toute la hauteur
                 disponible (flex: 1 1 auto) sur l'ecran sans defilement de la
                 voiture. fill="currentColor" suit la couleur du texte de la
                 page, y compris en mode sombre. --}}
            <svg id="courbe-autonomie" viewBox="0 0 {{ $rangeChart['width'] }} {{ $rangeChart['height'] }}"
                 role="img"
                 aria-label="Autonomie estimée entre {{ $rangeChart['km_min'] }} et {{ $rangeChart['km_max'] }} km, de {{ $rangeChart['debut']->timezone(config('app.timezone'))->format('H:i') }} à {{ $rangeChart['fin']->timezone(config('app.timezone'))->format('H:i') }}">
                <polyline points="{{ $rangeChart['points'] }}" fill="none" stroke="#2ea36b"
                          stroke-width="3" stroke-linejoin="round" stroke-linecap="round" />
                <text x="{{ $rangeChart['label_x'] }}" y="{{ $rangeChart['km_max_y'] }}"
                      font-size="15" fill="currentColor" dominant-baseline="hanging">{{ $rangeChart['km_max'] }} km</text>
                <text x="{{ $rangeChart['label_x'] }}" y="{{ $rangeChart['km_min_y'] }}"
                      font-size="15" fill="currentColor">{{ $rangeChart['km_min'] }} km</text>
                <text x="{{ $rangeChart['label_x'] }}" y="{{ $rangeChart['temps_y'] }}"
                      font-size="15" fill="currentColor" opacity=".7">{{ $rangeChart['debut']->timezone(config('app.timezone'))->format('H:i') }}</text>
                <text x="{{ $rangeChart['temps_fin_x'] }}" y="{{ $rangeChart['temps_y'] }}"
                      font-size="15" fill="currentColor" opacity=".7" text-anchor="end">{{ $rangeChart['fin']->timezone(config('app.timezone'))->format('H:i') }}</text>
            </svg>
            <p class="note">
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

    {{-- Ecran de batterie du jour : contenu vide au chargement, rempli en
         JS des l'ouverture puis toutes les minutes tant qu'il reste affiche.
         Pas de rendu cote serveur ici -- contrairement a l'onglet Courbe --
         le premier affichage a l'ouverture appelle de toute facon le meme
         point d'entree, autant n'avoir qu'un seul chemin qui dessine. --}}
    <div class="recouvrement" id="recouvrement-batterie" hidden>
        <div class="carte" role="dialog" aria-label="Batterie aujourd'hui">
            <div class="entete">
                <h2>Batterie aujourd'hui</h2>
                <button type="button" class="fermer" data-ferme="batterie" aria-label="Fermer">✕</button>
            </div>
            <svg id="graphe-batterie" viewBox="0 0 600 220" role="img" aria-label="Chargement…"></svg>
            <p class="note" id="graphe-batterie-etat">Chargement…</p>
        </div>
    </div>

    @if (! empty($dernieresVilles))
        <div class="recouvrement" id="recouvrement-villes" hidden>
            <div class="carte" role="dialog" aria-label="Dernières communes traversées">
                <div class="entete">
                    <h2>Dernières communes</h2>
                    <button type="button" class="fermer" data-ferme="villes" aria-label="Fermer">✕</button>
                </div>
                <ul class="liste-villes">
                    @foreach ($dernieresVilles as $entree)
                        <li>
                            <span>{{ $entree['ville'] }}</span>
                            <span class="heure">{{ $entree['vu_a']->timezone(config('app.timezone'))->format('d/m H:i') }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
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
         *
         * Les cases sont recherchees a chaque appel plutot qu'une seule fois :
         * en plein ecran, `rechargement` remplace le contenu de l'onglet Info
         * sans jamais naviguer (voir plus bas), ce qui remplacerait aussi ces
         * elements. Une liste mise en cache continuerait de faire battre des
         * cases devenues invisibles, sans plus rien animer a l'ecran.
         */
        function afficher() {
            var cases = document.querySelectorAll('.alterne');

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

        // Zones dont le contenu doit rester a jour quand le rechargement se
        // fait sur place (voir rafraichirSurPlace) plutot que par navigation.
        // L'onglet Position n'y figure pas : il suspend deja lui-meme le
        // rechargement des qu'on l'affiche (carte OpenStreetMap), le plein
        // ecran ne peut donc jamais s'y heurter au probleme resolu ici.
        var ZONES_A_RAFRAICHIR = ['vue-info', 'vue-recharge', 'vue-courbe'];

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

                // Une navigation sort systematiquement du plein ecran, sans
                // qu'aucune API ne permette de l'en empecher -- le redemander
                // apres coup echoue silencieusement, faute du geste utilisateur
                // recent que les navigateurs exigent (constate sur la voiture).
                // Tant que l'ecran y est, on se met donc a jour sur place : on
                // va chercher la meme page et on replace seulement ce qui peut
                // avoir change, sans jamais naviguer.
                if (document.fullscreenElement) {
                    rafraichirSurPlace();
                } else {
                    window.location.reload();
                }
            }
        }

        function rafraichirSurPlace() {
            fetch(window.location.href, { cache: 'no-store' })
                .then(function (reponse) {
                    if (! reponse.ok) { throw new Error('reponse ' + reponse.status); }
                    return reponse.text();
                })
                .then(function (html) {
                    var frais = new DOMParser().parseFromString(html, 'text/html');

                    // Si le document frais n'a plus les zones attendues --
                    // code d'acces expire entre-temps, ou toute autre raison
                    // -- mieux vaut une vraie navigation, qui affichera la
                    // realite (l'ecran de code au besoin), qu'une page figee
                    // indefiniment sur des donnees perimees.
                    if (! frais.getElementById('vue-info')) {
                        throw new Error('page inattendue');
                    }

                    var etatFrais = frais.querySelector('.etat');
                    var etatActuel = document.querySelector('.etat');
                    if (etatFrais && etatActuel) {
                        etatActuel.className = etatFrais.className;
                        etatActuel.textContent = etatFrais.textContent;
                    }

                    for (var i = 0; i < ZONES_A_RAFRAICHIR.length; i++) {
                        var actuelle = document.getElementById(ZONES_A_RAFRAICHIR[i]);
                        var neuve = frais.getElementById(ZONES_A_RAFRAICHIR[i]);
                        if (actuelle && neuve) { actuelle.innerHTML = neuve.innerHTML; }
                    }

                    lance = false;
                    echeance = Date.now() + duree;
                    battement = setInterval(battre, 200);
                })
                .catch(function () {
                    window.location.reload();
                });
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
        var titre = document.querySelector('[data-plein-ecran]');

        if (!titre || !document.documentElement.requestFullscreen) { return; }

        var CLE_PLEIN_ECRAN = 'infocar-plein-ecran';

        titre.addEventListener('click', function () {
            if (document.fullscreenElement) {
                document.exitFullscreen();
                return;
            }

            // Promesse ignoree volontairement : rien ne garantit que l'API
            // existe vraiment sur le navigateur embarque de la voiture, ni
            // qu'elle l'accepte. Un refus doit rester silencieux, pas
            // remonter en erreur sur l'ecran du conducteur.
            var p = document.documentElement.requestFullscreen();
            if (p && p.catch) { p.catch(function () {}); }
        });

        document.addEventListener('fullscreenchange', function () {
            try {
                if (document.fullscreenElement) {
                    sessionStorage.setItem(CLE_PLEIN_ECRAN, '1');
                } else {
                    sessionStorage.removeItem(CLE_PLEIN_ECRAN);
                }
            } catch (e) {}
        });

        /*
         * Le rechargement periodique (voir `rechargement` plus haut) charge
         * un document tout neuf a chaque fois, et sortir du plein ecran a
         * cette occasion est un comportement du navigateur qu'aucune API ne
         * permet de court-circuiter. On retente donc l'entree au chargement
         * si l'ecran l'etait juste avant -- en silence, comme au-dessus : la
         * plupart des navigateurs exigent un geste utilisateur recent pour
         * l'accorder, et rien ne garantit que celui de la voiture le fasse
         * ici hors d'un clic.
         */
        try {
            if (sessionStorage.getItem(CLE_PLEIN_ECRAN) === '1') {
                var p2 = document.documentElement.requestFullscreen();
                if (p2 && p2.catch) { p2.catch(function () {}); }
            }
        } catch (e) {}
    })();

    /*
     * Ecrans qui s'ouvrent par-dessus tout le reste (batterie du jour,
     * communes traversees). Un seul mecanisme generique par attribut
     * data-ouvre/data-ferme plutot qu'un gestionnaire par ecran : la
     * suspension du rechargement automatique doit valoir pour n'importe
     * lequel des deux, et un troisieme s'ajouterait sans rien dupliquer ici.
     */
    var recouvrements = (function () {
        var rafraichisseurBatterie = null;

        function ferme(nom) {
            var ecran = document.getElementById('recouvrement-' + nom);
            if (!ecran) { return; }
            ecran.hidden = true;
            rechargement.reprendre();

            if (nom === 'batterie' && rafraichisseurBatterie) {
                clearInterval(rafraichisseurBatterie);
                rafraichisseurBatterie = null;
            }
        }

        function ouvre(nom) {
            var ecran = document.getElementById('recouvrement-' + nom);
            if (!ecran) { return; }

            // Le rechargement complet de la page couperait l'ecran ouvert
            // toutes les 5 a 20 s : il est suspendu tant qu'on regarde, comme
            // deja fait pour la carte de l'onglet Position.
            rechargement.suspendre();
            ecran.hidden = false;

            if (nom === 'batterie') {
                rafraichirBatterie();
                rafraichisseurBatterie = setInterval(rafraichirBatterie, 60000);
            }
        }

        // Delegue sur document plutot qu'un ecouteur par declencheur : la
        // tuile Batterie vit dans l'onglet Info, que rafraichirSurPlace
        // remplace en entier pendant le plein ecran (voir `rechargement` plus
        // haut). Un ecouteur pose sur l'ancien element ne suivrait pas.
        document.addEventListener('click', function (e) {
            var declencheur = e.target.closest('[data-ouvre]');
            if (declencheur) { ouvre(declencheur.dataset.ouvre); }
        });
        // Cible tactile activable au clavier aussi (role="button").
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') { return; }
            var declencheur = e.target.closest('[data-ouvre]');
            if (declencheur) {
                e.preventDefault();
                ouvre(declencheur.dataset.ouvre);
            }
        });

        var boutonsFermeture = document.querySelectorAll('[data-ferme]');
        for (var j = 0; j < boutonsFermeture.length; j++) {
            (function (bouton) {
                bouton.addEventListener('click', function () { ferme(bouton.dataset.ferme); });
            })(boutonsFermeture[j]);
        }

        // Toucher le fond assombri ferme aussi, sans avoir a viser le bouton.
        var ecrans = document.querySelectorAll('.recouvrement');
        for (var k = 0; k < ecrans.length; k++) {
            (function (ecran) {
                ecran.addEventListener('click', function (e) {
                    if (e.target === ecran) { ferme(ecran.id.replace('recouvrement-', '')); }
                });
            })(ecrans[k]);
        }

        return { ferme: ferme };
    })();

    function rafraichirBatterie() {
        var svg = document.getElementById('graphe-batterie');
        var etat = document.getElementById('graphe-batterie-etat');

        if (!svg || typeof fetch !== 'function') {
            if (etat) { etat.textContent = "Actualisation automatique indisponible sur ce navigateur."; }
            return;
        }

        fetch('{{ route('info-car') }}?flux=batterie', { headers: { 'Accept': 'application/json' } })
            .then(function (reponse) {
                if (!reponse.ok) { throw new Error('reponse ' + reponse.status); }
                return reponse.json();
            })
            .then(function (donnees) { dessineBatterie(donnees.points || []); })
            .catch(function () {
                if (etat) { etat.textContent = 'Impossible de récupérer les relevés.'; }
            });
    }

    function dessineBatterie(points) {
        var svg = document.getElementById('graphe-batterie');
        var etat = document.getElementById('graphe-batterie-etat');

        if (!svg) { return; }

        while (svg.firstChild) { svg.removeChild(svg.firstChild); }

        if (points.length < 2) {
            if (etat) { etat.textContent = "Pas encore assez de relevés aujourd'hui."; }
            return;
        }

        var width = 600, height = 220;
        var margeHaut = 16, margeBas = 30, margeGauche = 34, margeDroite = 10;
        var largeur = width - margeGauche - margeDroite;
        var hauteur = height - margeHaut - margeBas;

        var debut = points[0].t;
        var fin = points[points.length - 1].t;
        var etendue = Math.max(1, fin - debut);

        var ns = 'http://www.w3.org/2000/svg';

        function ligne(y, pointilles) {
            var l = document.createElementNS(ns, 'line');
            l.setAttribute('x1', margeGauche); l.setAttribute('x2', width - margeDroite);
            l.setAttribute('y1', y); l.setAttribute('y2', y);
            l.setAttribute('stroke', 'currentColor');
            l.setAttribute('stroke-opacity', '.15');
            if (pointilles) { l.setAttribute('stroke-dasharray', '4 4'); }
            svg.appendChild(l);
        }

        function texte(x, y, contenu, ancrage, base) {
            var tEl = document.createElementNS(ns, 'text');
            tEl.setAttribute('x', x); tEl.setAttribute('y', y);
            tEl.setAttribute('font-size', '13'); tEl.setAttribute('fill', 'currentColor');
            tEl.setAttribute('opacity', '.7');
            if (ancrage) { tEl.setAttribute('text-anchor', ancrage); }
            if (base) { tEl.setAttribute('dominant-baseline', base); }
            tEl.textContent = contenu;
            svg.appendChild(tEl);
        }

        // Reperes a 0, 50 et 100 % : sans eux le trace seul ne dit pas contre
        // quelle echelle le lire. Fixe et non ajustee aux valeurs du jour --
        // une batterie se lit contre sa capacite totale, un axe qui
        // s'etirerait sur les seules valeurs vues exagererait une variation
        // de quelques points.
        var reperes = [0, 50, 100];
        for (var r = 0; r < reperes.length; r++) {
            var yRepere = margeHaut + hauteur - (reperes[r] / 100) * hauteur;
            ligne(yRepere, reperes[r] !== 0 && reperes[r] !== 100);
            texte(margeGauche - 6, yRepere, reperes[r] + ' %', 'end',
                reperes[r] === 100 ? 'hanging' : (reperes[r] === 0 ? 'auto' : 'middle'));
        }

        var coords = [];
        for (var i = 0; i < points.length; i++) {
            var x = margeGauche + (points[i].t - debut) / etendue * largeur;
            var y = margeHaut + hauteur - (points[i].soc / 100) * hauteur;
            coords.push(x.toFixed(1) + ',' + y.toFixed(1));
        }

        var poly = document.createElementNS(ns, 'polyline');
        poly.setAttribute('points', coords.join(' '));
        poly.setAttribute('fill', 'none');
        poly.setAttribute('stroke', '#2ea36b');
        poly.setAttribute('stroke-width', '3');
        poly.setAttribute('stroke-linejoin', 'round');
        poly.setAttribute('stroke-linecap', 'round');
        svg.appendChild(poly);

        function formateHeure(ms) {
            var d = new Date(ms);
            var h = d.getHours(), m = d.getMinutes();
            return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m;
        }

        texte(margeGauche, height - 8, formateHeure(debut));
        texte(width - margeDroite, height - 8, formateHeure(fin), 'end');

        if (etat) {
            etat.textContent = 'Dernier relevé ' + formateHeure(fin) + ' \u00b7 mise à jour chaque minute';
        }
    }

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
