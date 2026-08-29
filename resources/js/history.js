import {
    Chart,
    BarController,
    BarElement,
    LineController,
    LineElement,
    PointElement,
    CategoryScale,
    LinearScale,
    Legend,
    Tooltip,
} from 'chart.js';

Chart.register(
    BarController,
    BarElement,
    LineController,
    LineElement,
    PointElement,
    CategoryScale,
    LinearScale,
    Legend,
    Tooltip,
);

const KWH_COLOR = '#00d1b2';
const COST_COLOR = '#3273dc';

function readJson(canvas, attribute) {
    return JSON.parse(canvas.dataset[attribute] || '[]');
}

/**
 * Vue mensuelle : kWh et cout sur un seul graphique. Les deux grandeurs n'ont
 * pas la meme echelle, d'ou deux axes Y et un rendu different (barres / ligne)
 * pour qu'on distingue immediatement laquelle est laquelle.
 */
function renderCombinedChart(canvas) {
    new Chart(canvas, {
        data: {
            labels: readJson(canvas, 'labels'),
            datasets: [
                {
                    type: 'bar',
                    label: 'kWh',
                    data: readJson(canvas, 'kwh'),
                    backgroundColor: KWH_COLOR,
                    yAxisID: 'y',
                    order: 2,
                },
                {
                    type: 'line',
                    label: 'Coût (€)',
                    data: readJson(canvas, 'cost'),
                    borderColor: COST_COLOR,
                    backgroundColor: COST_COLOR,
                    borderWidth: 2,
                    tension: 0.3,
                    pointRadius: 2,
                    pointHitRadius: 12,
                    yAxisID: 'y1',
                    order: 1,
                },
            ],
        },
        options: {
            responsive: true,
            aspectRatio: 4,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: true, position: 'bottom' },
                tooltip: {
                    callbacks: {
                        title: (items) => `Jour ${items[0].label}`,
                        label: (item) => (item.dataset.yAxisID === 'y1'
                            ? `Coût : ${item.parsed.y.toFixed(2)} €`
                            : `${item.parsed.y} kWh`),
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    position: 'left',
                    title: { display: true, text: 'kWh' },
                    ticks: { color: KWH_COLOR },
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    title: { display: true, text: '€' },
                    ticks: { color: COST_COLOR },
                    // Pas de seconde grille : elle se superposerait a celle de l'axe kWh.
                    grid: { drawOnChartArea: false },
                },
            },
        },
    });
}

/** Vue annuelle : un graphique par grandeur. */
function renderSeparateCharts(kwhCanvas, costCanvas) {
    const labels = readJson(kwhCanvas, 'labels');
    const baseOptions = { responsive: true, aspectRatio: 4, plugins: { legend: { display: false } } };

    new Chart(kwhCanvas, {
        type: 'bar',
        data: { labels, datasets: [{ label: 'kWh', data: readJson(kwhCanvas, 'values'), backgroundColor: KWH_COLOR }] },
        options: baseOptions,
    });

    new Chart(costCanvas, {
        type: 'bar',
        data: { labels, datasets: [{ label: 'Coût (€)', data: readJson(costCanvas, 'values'), backgroundColor: COST_COLOR }] },
        options: baseOptions,
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const combined = document.getElementById('history-daily-chart');

    if (combined) {
        renderCombinedChart(combined);

        return;
    }

    const kwhCanvas = document.getElementById('history-kwh-chart');
    const costCanvas = document.getElementById('history-cost-chart');

    if (kwhCanvas && costCanvas) {
        renderSeparateCharts(kwhCanvas, costCanvas);
    }
});
