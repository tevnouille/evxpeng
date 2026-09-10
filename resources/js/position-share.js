import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

function popupFor(point) {
    const lines = [`<strong>${point.time}</strong>`];

    if (point.soc !== null) {
        lines.push(`Batterie : ${point.soc} %`);
    }

    if (point.range_km !== null) {
        lines.push(`Autonomie estimée : ${point.range_km} km`);
    }

    lines.push(point.address ?? 'Adresse inconnue');

    return lines.join('<br>');
}

function renderMap() {
    const container = document.getElementById('share-map');

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

    let lastMarker = null;

    points.forEach((point, index) => {
        const isLast = index === points.length - 1;

        const marker = L.circleMarker([point.lat, point.lon], {
            radius: isLast ? 9 : 5,
            color: '#fff',
            weight: isLast ? 3 : 2,
            fillColor: isLast ? '#f14668' : '#3e8ed0',
            fillOpacity: 1,
        })
            .bindPopup(popupFor(point))
            .addTo(map);

        if (isLast) {
            lastMarker = marker;
        }
    });

    map.fitBounds(L.latLngBounds(coordinates), { padding: [30, 30], maxZoom: 16 });

    // Le point le plus recent est celui que le destinataire du lien est venu
    // chercher : sa bulle s'ouvre d'emblee, sans qu'il ait a cliquer.
    lastMarker?.openPopup();
}

// Tant que le partage n'est pas expire, la page se recharge seule pour que le
// destinataire voie la voiture avancer sans intervenir.
function setUpAutoRefresh() {
    const status = document.getElementById('share-refresh-status');

    if (!status) {
        return;
    }

    const period = 20;
    let remaining = period;

    window.setInterval(() => {
        if (document.hidden) {
            remaining = period;
            status.textContent = 'Actualisation en pause (onglet en arrière-plan)';

            return;
        }

        remaining -= 1;

        if (remaining <= 0) {
            status.textContent = 'Actualisation…';
            window.location.reload();

            return;
        }

        status.textContent = `Actualisation dans ${remaining} s`;
    }, 1000);
}

renderMap();
setUpAutoRefresh();
