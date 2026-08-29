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

function renderPowerChart(canvas) {
    const labels = readData(canvas, 'labels');
    const values = readData(canvas, 'values');

    new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    data: values,
                    borderColor: '#3e8ed0',
                    backgroundColor: 'rgba(62, 142, 208, 0.15)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 0,
                    pointHitRadius: 12,
                },
            ],
        },
        options: {
            responsive: true,
            aspectRatio: 3,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: (items) => `${items[0].label} %`,
                        label: (item) => `${item.parsed.y} kW`,
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
