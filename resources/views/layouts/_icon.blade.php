{{--
    Icones de navigation, dessinees en SVG inline.

    Pas de police d'icones ni de CDN : le trait suit la couleur du texte
    (`currentColor`), donc l'etat actif et le survol de Bulma s'appliquent sans
    regle supplementaire, et rien n'est charge en plus.
--}}
@php
    $paths = [
        'recharges' => '<path d="M5 18H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h3.2M15 6h2a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-3.2"/><line x1="23" y1="13" x2="23" y2="11"/><polyline points="11 6 7 12 13 12 9 18"/>',
        'historique' => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15.5 14"/>',
        'dashboard' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
        'voiture' => '<path d="M5 17h14v-4l-1.6-4.4A2 2 0 0 0 15.5 7h-7a2 2 0 0 0-1.9 1.6L5 13z"/><circle cx="7.5" cy="17" r="1.5"/><circle cx="16.5" cy="17" r="1.5"/>',
        'courbe' => '<polyline points="22 7 14 15 10 11 2 19"/><polyline points="16 7 22 7 22 13"/>',
        'carte' => '<polygon points="2 6 2 21 8.5 17.5 15.5 21 22 17.5 22 3 15.5 6.5 8.5 3 2 6"/><line x1="8.5" y1="3" x2="8.5" y2="17.5"/><line x1="15.5" y1="6.5" x2="15.5" y2="21"/>',
        'planificateur' => '<polygon points="3 11 22 2 13 21 11 13 3 11"/>',
        'favoris' => '<polygon points="12 3 14.7 8.5 20.8 9.4 16.4 13.7 17.4 19.7 12 16.9 6.6 19.7 7.6 13.7 3.2 9.4 9.3 8.5 12 3"/>',
        'administration' => '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
    ];
@endphp
@if (isset($paths[$name]))
    <span class="icon is-small mr-1" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $paths[$name] !!}</svg>
    </span>
@endif
