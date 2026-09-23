import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Un seul point (la derniere position connue), contrairement a trips.js qui
// trace un parcours -- pas de polyline ni de groupement par trajet ici.
function renderFordMap() {
    const container = document.getElementById('ford-map');

    if (!container) {
        return;
    }

    const lat = parseFloat(container.dataset.lat);
    const lon = parseFloat(container.dataset.lon);

    const map = L.map(container, { scrollWheelZoom: false }).setView([lat, lon], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 19,
    }).addTo(map);

    L.circleMarker([lat, lon], {
        radius: 9,
        color: '#fff',
        weight: 3,
        fillColor: '#f14668',
        fillOpacity: 1,
    }).addTo(map);
}

document.addEventListener('DOMContentLoaded', renderFordMap);
