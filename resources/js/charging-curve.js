import {
    Chart,
    Filler,
    Legend,
    LineController,
    LineElement,
    PointElement,
    CategoryScale,
    LinearScale,
    Tooltip,
} from 'chart.js';

Chart.register(LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend, Filler);

function readData(canvas, attribute) {
    return JSON.parse(canvas.dataset[attribute]);
}

function formatMinutes(minutes) {
    const total = Math.round(minutes * 60);

    return `${Math.floor(total / 60)} min ${String(total % 60).padStart(2, '0')} s`;
}

// Axe des abscisses partage par les deux graphiques : un repere tous les 10 %.
function socAxis() {
    return {
        title: { display: true, text: 'Niveau de batterie (%)' },
        ticks: {
            callback(value) {
                const soc = this.getLabelForValue(value);

                return soc % 10 === 0 ? `${soc} %` : '';
            },
            autoSkip: false,
            maxRotation: 0,
        },
    };
}

// Marqueur du niveau de charge reel sur la courbe. Rouge en charge (on suit sa
// progression), gris sinon (simple reperage). Le dataset ne porte qu'un point :
// tous les autres index sont nuls.
function currentPositionDataset(canvas, labels) {
    const soc = canvas.dataset.currentSoc;
    const kw = canvas.dataset.currentKw;

    if (soc === '' || kw === '') {
        return null;
    }

    const index = labels.indexOf(Number(soc));

    if (index < 0) {
        return null;
    }

    const charging = canvas.dataset.charging === '1';
    const color = charging ? '#f14668' : '#7a7a7a';
    const data = new Array(labels.length).fill(null);
    data[index] = Number(kw);

    return {
        label: charging ? 'Niveau actuel (en charge)' : 'Niveau actuel',
        data,
        borderColor: color,
        backgroundColor: color,
        pointRadius: 7,
        pointHoverRadius: 9,
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        showLine: false,
    };
}

function renderPowerChart(canvas) {
    const labels = readData(canvas, 'labels');
    const values = readData(canvas, 'values');

    const datasets = [
        {
            label: 'Puissance de charge',
            data: values,
            borderColor: '#3e8ed0',
            backgroundColor: 'rgba(62, 142, 208, 0.15)',
            borderWidth: 2,
            fill: true,
            tension: 0.3,
            pointRadius: 0,
            pointHitRadius: 12,
        },
    ];

    const current = currentPositionDataset(canvas, labels);

    if (current) {
        datasets.push(current);
    }

    new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets,
        },
        options: {
            responsive: true,
            aspectRatio: 3,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    display: true,
                    labels: { filter: (item) => item.text !== 'Puissance de charge' },
                },
                tooltip: {
                    callbacks: {
                        title: (items) => `${items[0].label} %`,
                        label: (item) => `${item.dataset.label} : ${item.parsed.y} kW`,
                    },
                },
            },
            scales: {
                x: socAxis(),
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Puissance (kW)' },
                    ticks: { callback: (value) => `${value} kW` },
                },
            },
        },
    });
}

function renderRemainingChart(canvas) {
    const labels = readData(canvas, 'labels');

    const series = [
        { target: 80, attribute: 'to80', color: '#48c78e' },
        { target: 90, attribute: 'to90', color: '#ffe08a' },
        { target: 100, attribute: 'to100', color: '#f14668' },
    ];

    new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: series.map(({ target, attribute, color }) => ({
                label: `Jusqu'à ${target} %`,
                data: readData(canvas, attribute),
                borderColor: color,
                backgroundColor: color,
                borderWidth: 2,
                tension: 0.3,
                pointRadius: 0,
                pointHitRadius: 12,
                // Les cibles deja atteintes valent null : on veut une courbe qui s'arrete,
                // pas un trait qui rejoint le point suivant.
                spanGaps: false,
            })),
        },
        options: {
            responsive: true,
            aspectRatio: 3,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: true, position: 'bottom' },
                tooltip: {
                    callbacks: {
                        title: (items) => `${items[0].label} %`,
                        label: (item) => `${item.dataset.label} : ${formatMinutes(item.parsed.y)}`,
                    },
                },
            },
            scales: {
                x: socAxis(),
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Temps restant (minutes)' },
                    ticks: { callback: (value) => `${value} min` },
                },
            },
        },
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const powerChart = document.getElementById('curve-power-chart');
    if (powerChart) {
        renderPowerChart(powerChart);
    }

    const remainingChart = document.getElementById('curve-remaining-chart');
    if (remainingChart) {
        renderRemainingChart(remainingChart);
    }
});

// --- Rafraichissement du bloc "Niveau actuel" -----------------------------
//
// La page interroge notre propre endpoint, pas ABRP : c'est la commande
// planifiee qui va chercher la donnee. En charge, la planification passe a la
// minute, donc un sondage toutes les 30 s suffit a ne rien manquer. A l'arret,
// la donnee bouge une fois par heure : inutile de sonder.

const LIVE_INTERVAL_CHARGING = 30000;
const LIVE_INTERVAL_IDLE = 300000;

function frenchNumber(value, digits = 1) {
    return value === null || value === undefined
        ? '—'
        : value.toFixed(digits).replace('.', ',').replace(/,0$/, '');
}

function setText(id, text) {
    const node = document.getElementById(id);

    if (node) {
        node.textContent = text;
    }
}

function humanMinutes(minutes) {
    if (minutes < 60) {
        return `${minutes} min`;
    }

    return `${Math.floor(minutes / 60)} h ${String(minutes % 60).padStart(2, '0')}`;
}

// Estimations calculees sur la puissance mesuree : ce sont elles qui sont
// comparables a l'affichage de la voiture.
function renderLiveRemaining(state) {
    const container = document.getElementById('tlm-live-remaining');

    if (!container) {
        return;
    }

    const targets = [[80, state.live_to_80], [90, state.live_to_90], [100, state.live_to_100]];
    const usable = targets.filter(([, minutes]) => minutes !== null && minutes !== undefined);

    if (usable.length === 0) {
        container.innerHTML = '<span class="tag">indisponible</span>';

        return;
    }

    container.innerHTML = usable
        .map(([target, minutes]) => `<span class="tag is-success">${target} % &nbsp;<strong>${humanMinutes(minutes)}</strong></span>`)
        .join('');
}

function applyState(state) {
    if (!state.available) {
        return;
    }

    const soc = state.soc === null ? null : Math.round(state.soc);

    setText('tlm-soc', soc === null ? '—' : `${soc} %`);

    const progress = document.getElementById('tlm-progress');
    if (progress) {
        progress.value = soc ?? 0;
    }

    const tag = document.getElementById('tlm-state');
    if (tag) {
        tag.textContent = state.is_charging ? 'en charge' : 'stationné';
        tag.classList.toggle('is-success', state.is_charging);
        tag.classList.toggle('is-light', !state.is_charging);
    }

    if (state.available_kwh !== null) {
        setText('tlm-available', `${frenchNumber(state.available_kwh)} kWh`);
    }

    setText('tlm-to80', state.to_80 ?? 'atteint');
    setText('tlm-to90', state.to_90 ?? 'atteint');
    setText('tlm-to100', state.to_100 ?? 'atteint');

    const charging = document.getElementById('tlm-charging');
    if (charging) {
        charging.classList.toggle('is-hidden', !state.is_charging);
    }

    setText('tlm-power', frenchNumber(state.power_kw));
    setText('tlm-power-sense', state.power_incoming === null
        ? ' '
        : (state.power_incoming ? 'entrante' : 'consommée'));
    setText('tlm-batt-temp', frenchNumber(state.batt_temp));
    setText('tlm-session-kwh', frenchNumber(state.session_kwh, 2));

    setText('tlm-session-detail', state.session_soc_start === null || state.session_soc_start === undefined
        ? ' '
        : `depuis ${Math.round(state.session_soc_start)} % — ${state.session_started_at}`);

    setText('tlm-session-duration', state.session_minutes === null || state.session_minutes === undefined
        ? '—'
        : `${Math.floor(state.session_minutes / 60)} h ${String(state.session_minutes % 60).padStart(2, '0')}`);

    renderLiveRemaining(state);

    setText('tlm-recorded', `${state.recorded_at_human} (${state.recorded_at})`);

    const now = new Date();
    setText('tlm-refreshed', `Page actualisée à ${now.toLocaleTimeString('fr-FR')}.`);
}

function startLiveUpdates() {
    const box = document.getElementById('tlm-live');

    if (!box) {
        return;
    }

    let timer = null;

    const schedule = (charging) => {
        if (timer) {
            clearInterval(timer);
        }

        timer = setInterval(refresh, charging ? LIVE_INTERVAL_CHARGING : LIVE_INTERVAL_IDLE);
    };

    let charging = box.dataset.charging === '1';

    async function refresh() {
        try {
            const response = await fetch(box.dataset.url, { headers: { Accept: 'application/json' } });

            if (!response.ok) {
                return;
            }

            const state = await response.json();
            applyState(state);

            // Le rythme suit l'etat : inutile de sonder toutes les 30 s une
            // voiture debranchee, ni d'attendre 5 min quand elle charge.
            if (state.available && state.is_charging !== charging) {
                charging = state.is_charging;
                schedule(charging);
            }
        } catch (error) {
            // Reseau indisponible : on retentera au prochain tick.
        }
    }

    schedule(charging);
    refresh();
}

startLiveUpdates();
