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

let powerChart = null;
let powerChartLabels = [];

function setRemainingMarker(soc) {
    if (!remainingChart || Number.isNaN(soc)) {
        return;
    }

    remainingChart.$currentIndex = remainingChart.data.labels.indexOf(soc);
    remainingChart.update('none');
}

function renderPowerChart(canvas) {
    const labels = readData(canvas, 'labels');
    const values = readData(canvas, 'values');

    // Reference constructeur, tracee en pointille sous la mesure : sans elle on
    // verrait la courbe de la voiture sans savoir de combien elle s'en ecarte.
    const reference = readData(canvas, 'reference');

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

    if (Array.isArray(reference) && reference.some((v) => v !== null && v !== undefined)) {
        datasets.push({
            label: 'Référence constructeur',
            data: reference,
            borderColor: '#b5b5b5',
            borderWidth: 1.5,
            borderDash: [5, 4],
            fill: false,
            tension: 0.3,
            pointRadius: 0,
            pointHitRadius: 8,
        });
    }

    const current = currentPositionDataset(canvas, labels);

    if (current) {
        datasets.push(current);
    }

    powerChartLabels = labels;
    powerChart = new Chart(canvas, {
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

// Trait vertical marquant le niveau actuel. Un point par courbe serait illisible
// ici : ce qui compte est de lire les trois temps restants a son niveau.
const currentSocLine = {
    id: 'currentSocLine',
    afterDatasetsDraw(chart) {
        const index = chart.$currentIndex;

        if (index === undefined || index === null || index < 0) {
            return;
        }

        const x = chart.scales.x.getPixelForValue(index);
        const { top, bottom } = chart.chartArea;
        const { ctx } = chart;

        ctx.save();
        ctx.strokeStyle = '#f14668';
        ctx.lineWidth = 2;
        ctx.setLineDash([5, 4]);
        ctx.beginPath();
        ctx.moveTo(x, top);
        ctx.lineTo(x, bottom);
        ctx.stroke();

        ctx.setLineDash([]);
        ctx.fillStyle = '#f14668';
        ctx.font = 'bold 11px sans-serif';
        ctx.textAlign = x > chart.chartArea.right - 60 ? 'right' : 'left';
        ctx.fillText(`${chart.data.labels[index]} %`, x + (ctx.textAlign === 'right' ? -6 : 6), top + 12);
        ctx.restore();
    },
};

let remainingChart = null;

function renderRemainingChart(canvas) {
    const labels = readData(canvas, 'labels');

    const series = [
        { target: 80, attribute: 'to80', color: '#48c78e' },
        { target: 90, attribute: 'to90', color: '#ffe08a' },
        { target: 100, attribute: 'to100', color: '#f14668' },
    ];

    remainingChart = new Chart(canvas, {
        type: 'line',
        plugins: [currentSocLine],
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

    const remainingCanvas = document.getElementById('curve-remaining-chart');
    if (remainingCanvas) {
        renderRemainingChart(remainingCanvas);
        setRemainingMarker(Number(remainingCanvas.dataset.currentSoc));
    }
});


// --- Tableaux : se placer sur la ligne du niveau actuel -------------------

// Deplace la surbrillance sans faire defiler : pendant une charge la valeur
// bouge toutes les minutes, et deplacer la vue sous les yeux du lecteur serait
// plus genant qu'utile.
function highlightCurrentRows(soc) {
    document.querySelectorAll('[data-autoscroll] tr[data-soc]').forEach((row) => {
        row.classList.toggle('is-selected', Number(row.dataset.soc) === soc);
    });
}

// Au chargement seulement : on centre la ligne courante dans chaque tableau,
// sinon il faut faire defiler jusqu'a son niveau de charge a chaque visite.
function scrollToCurrentRow() {
    document.querySelectorAll('[data-autoscroll]').forEach((container) => {
        const row = container.querySelector('tr.is-selected');

        if (!row) {
            return;
        }

        // scrollIntoView ferait aussi defiler la page entiere ; on n'agit que
        // sur le conteneur.
        container.scrollTop = Math.max(0, row.offsetTop - (container.clientHeight / 2) + (row.offsetHeight / 2));
    });
}

scrollToCurrentRow();

// --- Rafraichissement du bloc "Niveau actuel" -----------------------------
//
// La page interroge notre propre endpoint, pas ABRP : c'est la commande
// planifiee qui va chercher la donnee. En charge, la planification passe a la
// minute, donc un sondage toutes les 30 s suffit a ne rien manquer. A l'arret,
// la donnee bouge une fois par heure : inutile de sonder souvent.

const LIVE_INTERVAL_CHARGING = 30000;
const LIVE_INTERVAL_IDLE = 300000;

let liveLastRefresh = null;
let liveNextRefresh = null;
let liveCharging = false;

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

// Ligne d'etat du rafraichissement, reecrite chaque seconde pour que le compte a
// rebours avance meme entre deux appels reseau.
function renderRefreshLine() {
    const node = document.getElementById('tlm-refreshed');

    if (!node || liveLastRefresh === null) {
        return;
    }

    const stamp = `${liveLastRefresh.toLocaleDateString('fr-FR')} à ${liveLastRefresh.toLocaleTimeString('fr-FR')}`;

    if (!liveCharging || liveNextRefresh === null) {
        node.textContent = `Actualisée le ${stamp}.`;

        return;
    }

    const seconds = Math.max(0, Math.round((liveNextRefresh - Date.now()) / 1000));

    node.textContent = seconds === 0
        ? `Actualisée le ${stamp} — actualisation en cours…`
        : `Actualisée le ${stamp} — prochaine dans ${seconds} s.`;
}

// Deplace le marqueur de position sur le graphique de puissance sans recharger
// la page : c'est tout l'interet de le suivre pendant une charge.
function moveCurrentMarker(state) {
    if (!powerChart || state.soc_rounded === null || state.curve_kw === null) {
        return;
    }

    const index = powerChartLabels.indexOf(state.soc_rounded);

    if (index < 0) {
        return;
    }

    const color = state.is_charging ? '#f14668' : '#7a7a7a';
    const data = new Array(powerChartLabels.length).fill(null);
    data[index] = state.curve_kw;

    let dataset = powerChart.data.datasets[1];

    // Le marqueur n'existe pas si la page a ete rendue sans telemetrie.
    if (!dataset) {
        dataset = {
            data,
            pointRadius: 7,
            pointHoverRadius: 9,
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            showLine: false,
        };
        powerChart.data.datasets.push(dataset);
    }

    dataset.label = state.is_charging ? 'Niveau actuel (en charge)' : 'Niveau actuel';
    dataset.data = data;
    dataset.borderColor = color;
    dataset.backgroundColor = color;

    powerChart.update('none');
}

function applyState(state) {
    if (!state.available) {
        return;
    }

    const soc = state.soc === null ? null : Math.round(state.soc);

    setText('tlm-soc', state.soc === null
        ? '—'
        : `${String(Math.round(state.soc * 10) / 10).replace('.', ',')} %`);

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
        ? ' '
        : (state.power_incoming ? 'entrante' : 'consommée'));
    setText('tlm-batt-temp', frenchNumber(state.batt_temp));
    setText('tlm-session-kwh', frenchNumber(state.session_kwh, 2));

    setText('tlm-session-detail', state.session_soc_start === null || state.session_soc_start === undefined
        ? ' '
        : `depuis ${Math.round(state.session_soc_start)} % — ${state.session_started_at}`);

    setText('tlm-session-duration', state.session_minutes === null || state.session_minutes === undefined
        ? '—'
        : `${Math.floor(state.session_minutes / 60)} h ${String(state.session_minutes % 60).padStart(2, '0')}`);

    renderLiveRemaining(state);
    moveCurrentMarker(state);

    if (state.soc_rounded !== null) {
        highlightCurrentRows(state.soc_rounded);
        setRemainingMarker(state.soc_rounded);
    }

    setText('tlm-recorded', `${state.recorded_at_human} (${state.recorded_at})`);

    liveLastRefresh = new Date();
    liveCharging = state.is_charging;
    renderRefreshLine();
}

function startLiveUpdates() {
    const box = document.getElementById('tlm-live');

    if (!box) {
        return;
    }

    let timer = null;

    liveCharging = box.dataset.charging === '1';

    const interval = () => (liveCharging ? LIVE_INTERVAL_CHARGING : LIVE_INTERVAL_IDLE);

    const schedule = () => {
        if (timer) {
            clearInterval(timer);
        }

        timer = setInterval(refresh, interval());
        liveNextRefresh = Date.now() + interval();
    };

    async function refresh() {
        liveNextRefresh = Date.now() + interval();

        try {
            const response = await fetch(box.dataset.url, { headers: { Accept: 'application/json' } });

            if (!response.ok) {
                return;
            }

            const state = await response.json();
            const wasCharging = liveCharging;

            applyState(state);

            // Le rythme suit l'etat : inutile de sonder toutes les 30 s une
            // voiture debranchee, ni d'attendre 5 min quand elle charge.
            if (state.available && state.is_charging !== wasCharging) {
                schedule();
            }
        } catch (error) {
            // Reseau indisponible : on retentera au prochain tick.
        }
    }

    schedule();
    refresh();

    // Le compte a rebours avance seul, independamment des appels reseau.
    setInterval(renderRefreshLine, 1000);
}

startLiveUpdates();
