import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { wireAddressInputs } from './address-autocomplete';
import { wireNetworkFilter } from './network-picker';

function endpointMarker(color) {
    return {
        radius: 9,
        color: '#fff',
        weight: 3,
        fillColor: color,
        fillOpacity: 1,
    };
}

// Pastille numerotee pour les arrets : sur un trajet a quatre pauses, la
// correspondance avec le tableau doit etre immediate.
function stopIcon(index) {
    return L.divIcon({
        className: '',
        iconSize: [26, 26],
        iconAnchor: [13, 13],
        html: `<div style="width:26px;height:26px;border-radius:50%;background:#ffdd57;border:3px solid #fff;
                box-shadow:0 0 0 1px rgba(0,0,0,.3);color:#363636;font-weight:700;font-size:13px;
                display:flex;align-items:center;justify-content:center;">${index + 1}</div>`,
    });
}

function stopPopup(stop, index) {
    const lines = [
        `<strong>${index + 1}. ${stop.name}</strong>`,
        stop.network || stop.operator || '',
        stop.address || stop.city || '',
        `${stop.power_kw} kW · ${stop.points_count} point(s) de charge`,
        `Km ${Math.round(stop.km)} · détour ${stop.detour_km} km`,
        `${Math.round(stop.soc_in)} % &rarr; ${Math.round(stop.soc_out)} % en ${stop.minutes} min`,
    ];

    return lines.filter(Boolean).join('<br>');
}

function renderMap() {
    const container = document.getElementById('planner-map');

    if (!container) {
        return;
    }

    const geometry = JSON.parse(container.dataset.geometry || '[]');
    const stops = JSON.parse(container.dataset.stops || '[]');
    const from = JSON.parse(container.dataset.from || 'null');
    const to = JSON.parse(container.dataset.to || 'null');

    if (geometry.length === 0) {
        return;
    }

    const map = L.map(container);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 19,
    }).addTo(map);

    const line = L.polyline(geometry, { color: '#3e8ed0', weight: 5, opacity: 0.85 }).addTo(map);

    if (from) {
        L.circleMarker([from[0], from[1]], endpointMarker('#48c774')).addTo(map).bindPopup(`<strong>Départ</strong><br>${from[2]}`);
    }

    if (to) {
        L.circleMarker([to[0], to[1]], endpointMarker('#f14668')).addTo(map).bindPopup(`<strong>Arrivée</strong><br>${to[2]}`);
    }

    stops.forEach((stop, index) => {
        L.marker([stop.lat, stop.lon], { icon: stopIcon(index) }).addTo(map).bindPopup(stopPopup(stop, index));
    });

    map.fitBounds(line.getBounds(), { padding: [30, 30] });
}

document.addEventListener('DOMContentLoaded', () => {
    wireAddressInputs();
    wireNetworkFilter();
    renderMap();
});
