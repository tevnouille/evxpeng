import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Marqueurs vectoriels plutot que les icones par defaut de Leaflet : celles-ci
// sont chargees par URL relative a la feuille de style et se cassent des qu'on
// passe par un bundler.
function markerFor(point, index, total) {
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
        fillColor: point.charging ? '#ffdd57' : '#3e8ed0',
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

    const coordinates = points.map((point) => [point.lat, point.lon]);

    // Les positions sont espacees d'une minute au mieux : la ligne relie des
    // releves, elle ne retrace pas la route empruntee. D'ou le pointille.
    L.polyline(coordinates, {
        color: '#3e8ed0',
        weight: 3,
        opacity: 0.7,
        dashArray: '6, 6',
    }).addTo(map);

    points.forEach((point, index) => {
        L.circleMarker([point.lat, point.lon], markerFor(point, index, points.length))
            .bindPopup(popupFor(point, index, points.length))
            .addTo(map);
    });

    map.fitBounds(L.latLngBounds(coordinates), { padding: [30, 30], maxZoom: 16 });
}

renderMap();
