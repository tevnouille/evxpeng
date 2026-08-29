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
 * Graphique commun aux deux vues de l'historique : par mois sur la vue annuelle,
 * par jour sur la vue mensuelle. kWh et cout n'ont pas la meme echelle, d'ou
 * deux axes Y et deux types de trace pour les distinguer d'un coup d'oeil.
 */
function renderCombinedChart(canvas) {
    const prefix = canvas.dataset.labelPrefix || '';

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
                        title: (items) => `${prefix}${items[0].label}`,
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

document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('history-combined-chart');

    if (canvas) {
        renderCombinedChart(canvas);
    }
});
