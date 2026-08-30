import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const SUGGEST_DELAY = 300;
const SUGGEST_MIN_CHARS = 3;

// Comparaison "comme on tape" : sans accents ni casse. "eveque" doit ramener
// "l'Évêque", "electra" doit trouver "ELECTRA".
function fold(value) {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
}

// Saisie assistee des adresses.
//
// Liste maison et non <datalist> : le navigateur refiltre lui-meme les options
// d'un datalist sur le texte saisi, en tenant compte des accents. Taper
// "ville l'eveque" masquait donc "Rue de la Ville l'Eveque" que l'API venait
// pourtant de renvoyer. Ici tout ce que le serveur propose reste visible.
function wireAddressInput(input) {
    const results = input.parentElement.querySelector('[data-address-results]');

    if (!results) {
        return;
    }

    let timer = null;
    let lastQuery = null;
    let items = [];
    let active = -1;

    const close = () => {
        results.hidden = true;
        items = [];
        active = -1;
    };

    const highlight = (index) => {
        active = index;
        items.forEach((item, position) => item.classList.toggle('is-active', position === index));

        if (items[index]) {
            items[index].scrollIntoView({ block: 'nearest' });
        }
    };

    const choose = (index) => {
        if (items[index]) {
            input.value = items[index].dataset.label;
            close();
        }
    };

    const render = (suggestions) => {
        results.innerHTML = '';

        if (suggestions.length === 0) {
            close();

            return;
        }

        items = suggestions.map((suggestion) => {
            const item = document.createElement('a');
            item.className = 'dropdown-item';
            item.href = '#';
            item.textContent = suggestion.label;
            item.dataset.label = suggestion.label;
            item.addEventListener('mousedown', (event) => {
                // mousedown et non click : le blur du champ fermerait la liste
                // avant que le clic ne soit delivre.
                event.preventDefault();
                input.value = suggestion.label;
                close();
            });
            results.appendChild(item);

            return item;
        });

        results.hidden = false;
        active = -1;
    };

    input.addEventListener('input', () => {
        const query = input.value.trim();

        window.clearTimeout(timer);

        if (query.length < SUGGEST_MIN_CHARS) {
            close();

            return;
        }

        timer = window.setTimeout(async () => {
            if (query === lastQuery) {
                return;
            }

            lastQuery = query;

            try {
                const response = await fetch(`/planificateur/adresses?q=${encodeURIComponent(query)}`, {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                render(payload.results || []);
            } catch (error) {
                // Sans suggestion, le champ libre reste parfaitement utilisable :
                // le geocodage cote serveur, lui, ignore accents et casse.
            }
        }, SUGGEST_DELAY);
    });

    input.addEventListener('keydown', (event) => {
        if (results.hidden || items.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            highlight((active + 1) % items.length);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            highlight((active - 1 + items.length) % items.length);
        } else if (event.key === 'Enter' && active >= 0) {
            event.preventDefault();
            choose(active);
        } else if (event.key === 'Escape') {
            close();
        }
    });

    input.addEventListener('blur', () => window.setTimeout(close, 120));
}

// Filtre du choix des reseaux : la liste en compte plusieurs centaines, la
// derouler jusqu'a "ELECTRA" n'est pas raisonnable.
function wireNetworkFilter() {
    const filter = document.querySelector('[data-network-filter]');
    const select = document.getElementById('reseaux');

    if (!filter || !select) {
        return;
    }

    const options = Array.from(select.options);

    filter.addEventListener('input', () => {
        const needle = fold(filter.value.trim());

        options.forEach((option) => {
            // Un reseau deja coche reste visible, sinon le filtre donnerait
            // l'impression de l'avoir deselectionne.
            option.hidden = needle !== '' && !option.selected && !fold(option.text).includes(needle);
        });
    });

    // Entree dans un champ de filtre : ne pas lancer le calcul par megarde.
    filter.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
        }
    });
}

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
    document.querySelectorAll('[data-address-input]').forEach(wireAddressInput);
    wireNetworkFilter();
    renderMap();
});
