import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Au-dela de cet ecart entre deux releves, on considere que la voiture a ete
// coupee (le boitier OBD ne remonte plus rien moteur eteint) : c'est la limite
// entre deux deplacements distincts de la meme journee. En dessous, un arret
// bref (feu, embouteillage, charge) fait toujours partie du meme trajet.
const TRIP_GAP_SECONDS = 15 * 60;

// Palette distincte des couleurs deja prises par les marqueurs (vert premier
// releve, rouge dernier, jaune charge) pour ne pas se meler a leur sens.
const TRIP_COLORS = ['#3e8ed0', '#ff8c42', '#9b59b6', '#00b0a3', '#c0392b', '#6c7a89'];

function timeToSeconds(time) {
    const [h, m, s] = time.split(':').map(Number);

    return h * 3600 + m * 60 + s;
}

// Decoupe les releves de la journee en deplacements successifs, sur les
// ecarts de temps entre releves consecutifs.
function splitTrips(points) {
    const trips = [];
    let current = [points[0]];

    for (let i = 1; i < points.length; i++) {
        const gap = timeToSeconds(points[i].time) - timeToSeconds(points[i - 1].time);

        if (gap > TRIP_GAP_SECONDS) {
            trips.push(current);
            current = [];
        }

        current.push(points[i]);
    }

    trips.push(current);

    return trips;
}

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

    const trips = splitTrips(points);

    // Associe chaque point a la couleur de son deplacement, pour que la ligne
    // et ses marqueurs se repondent visuellement.
    const tripColorByPoint = new Map();

    trips.forEach((trip, tripIndex) => {
        const color = TRIP_COLORS[tripIndex % TRIP_COLORS.length];

        trip.forEach((point) => tripColorByPoint.set(point, color));

        // Les positions sont espacees d'une minute au mieux : la ligne relie
        // des releves, elle ne retrace pas la route empruntee. D'ou le pointille.
        L.polyline(trip.map((point) => [point.lat, point.lon]), {
            color,
            weight: 3,
            opacity: 0.7,
            dashArray: '6, 6',
        }).addTo(map);
    });

    points.forEach((point, index) => {
        L.circleMarker(
            [point.lat, point.lon],
            markerFor(point, index, points.length, tripColorByPoint.get(point))
        )
            .bindPopup(popupFor(point, index, points.length))
            .addTo(map);
    });

    const coordinates = points.map((point) => [point.lat, point.lon]);

    map.fitBounds(L.latLngBounds(coordinates), { padding: [30, 30], maxZoom: 16 });
}

renderMap();
