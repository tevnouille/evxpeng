import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Palette distincte des couleurs deja prises par les marqueurs (vert premier
// releve, rouge dernier, jaune charge) pour ne pas se meler a leur sens.
const TRIP_COLORS = ['#3e8ed0', '#ff8c42', '#9b59b6', '#00b0a3', '#c0392b', '#6c7a89'];

// Marqueurs vectoriels plutot que les icones par defaut de Leaflet : celles-ci
// sont chargees par URL relative a la feuille de style et se cassent des qu'on
// passe par un bundler.
function markerFor(point, index, total, tripColor) {
    const isFirst = index === 0;
    const isLast = index === total - 1;

    if (isFirst || isLast) {
        return {
            radius: 9,
            color: '#fff',
            weight: 3,
            fillColor: isFirst ? '#48c774' : '#f14668',
            fillOpacity: 1,
        };
    }

    return {
        radius: 5,
        color: '#fff',
        weight: 2,
        fillColor: point.charging ? '#ffdd57' : tripColor,
        fillOpacity: 0.9,
    };
}

function popupFor(point, index, total) {
    const lines = [`<strong>${point.time}</strong>`];

    if (index === 0) {
        lines.push('Premier relevé du jour');
    }

    if (index === total - 1) {
        lines.push('Dernier relevé du jour');
    }

    if (point.soc !== null) {
        lines.push(`Batterie : ${point.soc} %`);
    }

    if (point.speed !== null) {
        lines.push(`Vitesse : ${point.speed} km/h`);
    }

    if (point.odometer !== null) {
        lines.push(`Compteur : ${point.odometer.toLocaleString('fr-FR')} km`);
    }

    if (point.charging) {
        lines.push('En charge');
    }

    return lines.join('<br>');
}

// Cable les boutons du selecteur de deplacement : montrer/cacher les calques
// de la carte, recadrer dessus, et repercuter le choix sur le filtre du
// tableau des releves (evenement ecoute par sessions-table.js).
function setUpTripFilter(map, tripLayers, allCoordinates) {
    const container = document.getElementById('trip-filter');

    if (!container) {
        return;
    }

    const table = document.getElementById(container.dataset.table);
    const buttons = [...container.querySelectorAll('[data-trip]')];

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const selected = button.dataset.trip;

            buttons.forEach((b) => {
                b.classList.toggle('is-link', b === button);
                b.classList.toggle('is-selected', b === button);
            });

            let bounds = allCoordinates;

            tripLayers.forEach(({ layer, coordinates }, tripIndex) => {
                const show = selected === 'all' || String(tripIndex) === selected;

                if (show) {
                    layer.addTo(map);
                } else {
                    layer.remove();
                }
            });

            if (selected !== 'all') {
                bounds = tripLayers.get(Number(selected))?.coordinates ?? allCoordinates;
            }

            map.fitBounds(L.latLngBounds(bounds), { padding: [30, 30], maxZoom: 16 });

            if (table) {
                table.dataset.tripFilter = selected;
                table.dispatchEvent(new Event('trip-filter-change'));
            }
        });
    });
}

function renderMap() {
    const container = document.getElementById('trip-map');

    if (!container) {
        return;
    }

    const points = JSON.parse(container.dataset.points);

    if (points.length === 0) {
        return;
    }

    const map = L.map(container);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 19,
    }).addTo(map);

    // Le decoupage en deplacements est calcule cote serveur (TripMapController) :
    // c'est lui qui alimente aussi le selecteur et le filtre du tableau, une
    // seule source de verite pour les trois.
    const byTrip = new Map();

    points.forEach((point, index) => {
        if (!byTrip.has(point.trip)) {
            byTrip.set(point.trip, []);
        }

        byTrip.get(point.trip).push({ point, index });
    });

    const tripLayers = new Map();
    const allCoordinates = points.map((point) => [point.lat, point.lon]);

    byTrip.forEach((entries, tripIndex) => {
        const color = TRIP_COLORS[tripIndex % TRIP_COLORS.length];
        const layer = L.layerGroup();
        const coordinates = entries.map(({ point }) => [point.lat, point.lon]);

        // Les positions sont espacees d'une minute au mieux : la ligne relie
        // des releves, elle ne retrace pas la route empruntee. D'ou le pointille.
        L.polyline(coordinates, {
            color,
            weight: 3,
            opacity: 0.7,
            dashArray: '6, 6',
        }).addTo(layer);

        entries.forEach(({ point, index }) => {
            L.circleMarker([point.lat, point.lon], markerFor(point, index, points.length, color))
                .bindPopup(popupFor(point, index, points.length))
                .addTo(layer);
        });

        layer.addTo(map);
        tripLayers.set(tripIndex, { layer, coordinates });
    });

    map.fitBounds(L.latLngBounds(allCoordinates), { padding: [30, 30], maxZoom: 16 });

    setUpTripFilter(map, tripLayers, allCoordinates);
}

// Carte des trajets habituels (/deplacements/recurrents) : une trace par
// trajet, sans marqueur ni popup — l'effet recherche est purement visuel,
// le cumul de traces translucides qui s'assombrit la ou l'on repasse souvent.
function renderRecurringMap() {
    const container = document.getElementById('recurring-map');

    if (!container) {
        return;
    }

    const polylines = JSON.parse(container.dataset.polylines);

    if (polylines.length === 0) {
        return;
    }

    const map = L.map(container);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 19,
    }).addTo(map);

    const allCoordinates = [];

    polylines.forEach((coordinates) => {
        L.polyline(coordinates, {
            color: '#3e8ed0',
            weight: 4,
            opacity: 0.12,
            lineCap: 'round',
        }).addTo(map);

        allCoordinates.push(...coordinates);
    });

    map.fitBounds(L.latLngBounds(allCoordinates), { padding: [30, 30], maxZoom: 15 });
}

renderMap();
renderRecurringMap();
