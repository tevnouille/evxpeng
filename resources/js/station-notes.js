import { attachSuggestions, fetchJson } from './suggest';

/**
 * Recherche de borne pour lui attacher une note, sur le meme mecanisme que
 * la saisie d'adresse et la recherche de bornes du formulaire de recharge.
 */
function wireStationSearch() {
    const input = document.querySelector('[data-station-search]');

    if (!input) {
        return;
    }

    const form = input.closest('form');
    const idField = form?.querySelector('[data-station-id]');
    const help = form?.querySelector('[data-station-help]');

    const setStation = (station) => {
        if (idField) {
            idField.value = station ? station.id : '';
        }

        if (help) {
            help.textContent = station
                ? 'Borne retenue — la note s\'appliquera à celle-ci.'
                : 'Choisissez une borne dans la liste proposée.';
            help.classList.toggle('has-text-success', Boolean(station));
        }
    };

    attachSuggestions(input, {
        search: (query) => fetchJson(`/recharges/bornes?q=${encodeURIComponent(query)}`),
        render: (node, station) => {
            node.appendChild(document.createTextNode(station.city || station.name));

            const detail = document.createElement('span');
            detail.className = 'has-text-grey ml-2 is-size-7';
            detail.textContent = [station.name, station.operator, `${station.power_kw} kW`]
                .filter(Boolean)
                .join(' — ');
            node.appendChild(detail);
        },
        pick: (station) => {
            input.value = [station.name, station.city].filter(Boolean).join(' — ');
            setStation(station);
        },
        // Le texte ne correspond plus a la borne retenue : mieux vaut bloquer
        // l'envoi (station_id vide, le champ est requis cote serveur) que de
        // noter la mauvaise borne.
        type: () => setStation(null),
    });
}

wireStationSearch();
