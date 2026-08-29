import {
    Chart,
    ArcElement,
    BarController,
    BarElement,
    LineController,
    LineElement,
    PieController,
    PointElement,
    CategoryScale,
    LinearScale,
    Legend,
    Tooltip,
} from 'chart.js';

Chart.register(
    ArcElement,
    BarController,
    BarElement,
    LineController,
    LineElement,
    PieController,
    PointElement,
    CategoryScale,
    LinearScale,
    Legend,
    Tooltip,
);

const KWH_COLOR = '#00d1b2';
const COST_COLOR = '#3273dc';

// Palette des camemberts : couleurs distinctes, reutilisees cycliquement
// si un mois compte plus de fournisseurs que de teintes.
const PALETTE = [
    '#00d1b2', '#3273dc', '#ffdd57', '#ff3860', '#7957d5',
    '#48c774', '#ff851b', '#209cee', '#b86bff', '#f14668',
];

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

/** Repartition par fournisseur (kWh ou cout). */
function renderPieChart(canvas) {
    const labels = readJson(canvas, 'labels');
    const values = readJson(canvas, 'values');
    const unit = canvas.dataset.unit || '';
    const total = values.reduce((sum, value) => sum + value, 0);

    new Chart(canvas, {
        type: 'pie',
        data: {
            labels,
            datasets: [
                {
                    data: values,
                    backgroundColor: labels.map((_, index) => PALETTE[index % PALETTE.length]),
                    borderColor: '#fff',
                    borderWidth: 1,
                },
            ],
        },
        options: {
            responsive: true,
            aspectRatio: 1.4,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: (item) => {
                            const share = total > 0 ? (item.parsed / total) * 100 : 0;

                            return `${item.label} : ${item.parsed.toFixed(2)} ${unit} (${share.toFixed(1)} %)`;
                        },
                    },
                },
            },
        },
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const combined = document.getElementById('history-combined-chart');

    if (combined) {
        renderCombinedChart(combined);
    }

    ['provider-kwh-pie', 'provider-cost-pie'].forEach((id) => {
        const canvas = document.getElementById(id);

        if (canvas) {
            renderPieChart(canvas);
        }
    });
});
