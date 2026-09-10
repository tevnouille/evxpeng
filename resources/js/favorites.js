import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { wireAddressInputs } from './address-autocomplete';
import { wireNetworkFilter, wireNetworkPicker } from './network-picker';

// Nombre de bornes visibles au-dela duquel on cesse d'afficher leur nom en
// permanence. Un seuil de zoom fixe ne convenait pas : a zoom egal, un corridor
// filtre sur deux reseaux tient largement l'affichage la ou la base complete
// empile des centaines d'etiquettes. C'est donc la densite a l'ecran qui decide,
// et le survol reste disponible dans tous les cas.
const MAX_PINNED_LABELS = 25;

// Couleur de la pastille selon la puissance : sur un corridor de plusieurs
// centaines de bornes, c'est la seule information lisible d'un coup d'oeil.
function colorFor(power) {
    if (power >= 150) {
        return '#f14668';
    }

    if (power >= 100) {
        return '#ff9d00';
    }

    if (power >= 50) {
        return '#3e8ed0';
    }

    return '#b5b5b5';
}

function tooltipOptions(permanent) {
    return {
        permanent,
        direction: 'right',
        offset: [8, 0],
        opacity: 0.95,
        className: 'station-label',
    };
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function element(tag, className, text) {
    const node = document.createElement(tag);

    if (className) {
        node.className = className;
    }

    if (text !== undefined) {
        node.textContent = text;
    }

    return node;
}

function renderMap() {
    const container = document.getElementById('favorites-map');

    if (!container) {
        return;
    }

    const geometry = JSON.parse(container.dataset.geometry || '[]');
    const stations = JSON.parse(container.dataset.stations || '[]');
    const from = JSON.parse(container.dataset.from || 'null');
    const to = JSON.parse(container.dataset.to || 'null');
    const addUrl = container.dataset.endpoint;
    const removeBase = container.dataset.removeEndpoint;

    let favorites = JSON.parse(container.dataset.favorites || '[]');

    if (geometry.length === 0) {
        return;
    }

    const map = L.map(container);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 19,
    }).addTo(map);

    const line = L.polyline(geometry, { color: '#3e8ed0', weight: 5, opacity: 0.7 }).addTo(map);

    if (from) {
        L.circleMarker([from[0], from[1]], { radius: 9, color: '#fff', weight: 3, fillColor: '#48c774', fillOpacity: 1 })
            .addTo(map).bindPopup(`<strong>Départ</strong><br>${from[2]}`);
    }

    if (to) {
        L.circleMarker([to[0], to[1]], { radius: 9, color: '#fff', weight: 3, fillColor: '#f14668', fillOpacity: 1 })
            .addTo(map).bindPopup(`<strong>Arrivée</strong><br>${to[2]}`);
    }

    const list = document.getElementById('favorites-list');
    const routeLink = document.querySelector('[data-route-google-link]');
    const markers = new Map();
    const byId = new Map();
    // Une borne retenue puis exclue par un filtre plus strict doit rester
    // visible : elle fait partie du trajet, pas du corridor courant.
    const orphans = new Map();

    const isFavorite = (stationId) => favorites.some((favorite) => favorite.station_id === stationId);

    const styleFor = (station) => (isFavorite(station.id)
        ? { radius: 9, color: '#363636', weight: 3, fillColor: '#ffdd57', fillOpacity: 1 }
        : { radius: 6, color: '#fff', weight: 1.5, fillColor: colorFor(station.power_kw), fillOpacity: 0.9 });

    async function send(url, method, body) {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: body ? JSON.stringify(body) : undefined,
        });

        if (!response.ok) {
            window.alert("L'enregistrement a échoué. Rechargez la page et réessayez.");

            return null;
        }

        return response.json();
    }

    async function add(station) {
        const payload = await send(addUrl, 'POST', { station_id: station.id, km: station.km });

        if (payload) {
            apply(payload);
        }
    }

    async function remove(favoriteId) {
        const payload = await send(`${removeBase}/${favoriteId}`, 'DELETE');

        if (payload) {
            apply(payload);
        }
    }

    function apply(payload) {
        favorites = payload.stations;

        if (routeLink && payload.route_google_url) {
            routeLink.href = payload.route_google_url;
        }

        markers.forEach((marker, id) => {
            marker.setStyle(styleFor(byId.get(id)));
            label(marker, id, labelsPinned);
        });
        map.closePopup();
        syncOrphans();
        renderList();
    }

    function popupFor(station) {
        const box = element('div');
        box.appendChild(element('strong', null, station.name));

        [
            station.network || station.operator,
            station.address || station.city,
            `${station.power_kw} kW · ${station.points_count ?? '?'} point(s) de charge`,
            station.km !== null && station.km !== undefined
                ? `Km ${Math.round(station.km)} · détour ${station.detour_km ?? 0} km`
                : null,
        ].filter(Boolean).forEach((line) => {
            box.appendChild(document.createElement('br'));
            box.appendChild(document.createTextNode(line));
        });

        if (station.note) {
            box.appendChild(document.createElement('br'));
            box.appendChild(element('em', null, station.note));
        }

        const favorite = favorites.find((entry) => entry.station_id === station.id);
        const button = element('button', `button is-small mt-2 ${favorite ? 'is-danger is-light' : 'is-link'}`,
            favorite ? 'Retirer du trajet' : 'Ajouter au trajet');
        button.type = 'button';
        button.addEventListener('click', () => (favorite ? remove(favorite.id) : add(station)));
        box.appendChild(document.createElement('br'));
        box.appendChild(button);

        return box;
    }

    stations.forEach((station) => {
        const marker = L.circleMarker([station.lat, station.lon], styleFor(station)).addTo(map);

        marker.bindPopup(() => popupFor(station));
        marker.bindTooltip(station.name, tooltipOptions(false));
        markers.set(station.id, marker);
        byId.set(station.id, station);
    });

    // Le passage survol <-> permanent demande de relier l'etiquette : Leaflet ne
    // permet pas de changer `permanent` apres coup. On ne le fait donc qu'au
    // franchissement du seuil, pas a chaque zoom.
    let labelsPinned = null;

    function updateLabels() {
        const bounds = map.getBounds();
        let visible = 0;

        markers.forEach((marker) => {
            if (bounds.contains(marker.getLatLng())) {
                visible++;
            }
        });

        const pinned = visible <= MAX_PINNED_LABELS;

        if (pinned === labelsPinned) {
            return;
        }

        labelsPinned = pinned;

        markers.forEach((marker, id) => label(marker, id, pinned));
    }

    // Une borne retenue garde son nom sous les yeux quelle que soit la densite :
    // c'est celle qui compte.
    function label(marker, id, pinned) {
        const favorite = isFavorite(id);

        marker.unbindTooltip();
        marker.bindTooltip(byId.get(id).name, {
            ...tooltipOptions(favorite || pinned),
            className: favorite ? 'station-label is-favorite' : 'station-label',
        });
    }

    map.on('zoomend moveend', updateLabels);

    // Favoris hors corridor : meme rendu, mais construits depuis la copie
    // stockee avec le trajet.
    function syncOrphans() {
        const wanted = new Set(favorites
            .filter((favorite) => favorite.station_id === null || !markers.has(favorite.station_id))
            .map((favorite) => favorite.id));

        orphans.forEach((marker, id) => {
            if (!wanted.has(id)) {
                map.removeLayer(marker);
                orphans.delete(id);
            }
        });

        favorites.forEach((favorite) => {
            if (!wanted.has(favorite.id) || orphans.has(favorite.id)) {
                return;
            }

            const marker = L.circleMarker([favorite.lat, favorite.lon], {
                radius: 9, color: '#363636', weight: 3, fillColor: '#ffdd57', fillOpacity: 1,
            }).addTo(map);

            marker.bindTooltip(favorite.name, { ...tooltipOptions(true), className: 'station-label is-favorite' });

            marker.bindPopup(() => {
                const box = element('div');
                box.appendChild(element('strong', null, favorite.name));
                box.appendChild(document.createElement('br'));
                box.appendChild(document.createTextNode('Hors du filtre actuel'));
                const button = element('button', 'button is-small is-danger is-light mt-2', 'Retirer du trajet');
                button.type = 'button';
                button.addEventListener('click', () => remove(favorite.id));
                box.appendChild(document.createElement('br'));
                box.appendChild(button);

                return box;
            });

            orphans.set(favorite.id, marker);
        });
    }

    function renderList() {
        if (!list) {
            return;
        }

        list.innerHTML = '';

        if (favorites.length === 0) {
            list.appendChild(element('p', 'has-text-grey',
                "Aucune borne retenue pour l'instant. Cliquez sur une pastille de la carte pour en ajouter une."));

            return;
        }

        const table = element('table', 'table is-fullwidth is-striped is-narrow');
        const head = element('thead');
        const headRow = element('tr');

        ['#', 'Borne', 'Réseau', 'Adresse', 'Km', 'Puissance', 'Navigation', ''].forEach((label, index) => {
            const cell = element('th', index >= 4 && index <= 5 ? 'has-text-right' : null, label);
            headRow.appendChild(cell);
        });

        head.appendChild(headRow);
        table.appendChild(head);

        const body = element('tbody');

        favorites.forEach((favorite, index) => {
            const row = element('tr');
            row.appendChild(element('td', null, String(index + 1)));

            const nameCell = element('td');
            nameCell.appendChild(element('strong', null, favorite.name));
            row.appendChild(nameCell);

            row.appendChild(element('td', null, favorite.network || '—'));
            row.appendChild(element('td', 'is-size-7', favorite.address || favorite.city || '—'));
            row.appendChild(element('td', 'has-text-right',
                favorite.km === null || favorite.km === undefined ? '—' : String(Math.round(favorite.km))));
            row.appendChild(element('td', 'has-text-right', `${favorite.power_kw} kW`));

            const links = element('td');
            const buttons = element('div', 'buttons are-small');

            [['Google Maps', favorite.google_url], ['Waze', favorite.waze_url]].forEach(([label, href]) => {
                const link = element('a', 'button is-small is-link is-light', label);
                link.href = href;
                link.target = '_blank';
                link.rel = 'noopener';
                buttons.appendChild(link);
            });

            links.appendChild(buttons);
            row.appendChild(links);

            const actions = element('td', 'has-text-right');
            const button = element('button', 'button is-small is-danger is-light', 'Retirer');
            button.type = 'button';
            button.addEventListener('click', () => remove(favorite.id));
            actions.appendChild(button);
            row.appendChild(actions);

            body.appendChild(row);
        });

        table.appendChild(body);

        const wrapper = element('div', 'table-container');
        wrapper.appendChild(table);
        list.appendChild(wrapper);
    }

    syncOrphans();
    renderList();
    map.fitBounds(line.getBounds(), { padding: [30, 30] });
    updateLabels();
    // Les bornes deja retenues portent leur nom des l'ouverture.
    markers.forEach((marker, id) => {
        if (isFavorite(id)) {
            label(marker, id, false);
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    wireAddressInputs();
    wireNetworkFilter();
    wireNetworkPicker();
    renderMap();
});
