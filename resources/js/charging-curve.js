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
