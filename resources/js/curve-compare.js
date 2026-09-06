import {
    Chart,
    Legend,
    LineController,
    LineElement,
    PointElement,
    LinearScale,
    Tooltip,
} from 'chart.js';

Chart.register(LineController, LineElement, PointElement, LinearScale, Tooltip, Legend);

// Axe des abscisses en niveau de charge et non en temps : c'est ce qui permet
// de superposer une charge a la courbe du vehicule, qui n'est definie que par
// rapport au SoC. Echelle lineaire et points {x, y} — les mesures tombent sur
// des niveaux fractionnaires, une echelle par categories les mal placerait.
function renderComparison(canvas) {
    const measured = JSON.parse(canvas.dataset.measured || '[]');
    const reference = JSON.parse(canvas.dataset.reference || '[]');

    if (measured.length === 0) {
        return;
    }

    new Chart(canvas, {
        type: 'line',
        data: {
            datasets: [
                {
                    label: 'Référence du véhicule',
                    data: reference,
                    borderColor: '#b5b5b5',
                    borderWidth: 1.5,
                    borderDash: [5, 4],
                    fill: false,
                    pointRadius: 0,
                    tension: 0.3,
                },
                {
                    label: 'Recharge mesurée',
                    data: measured,
                    borderColor: '#3e8ed0',
                    backgroundColor: '#3e8ed0',
                    borderWidth: 2,
                    fill: false,
                    pointRadius: 2.5,
                    tension: 0,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            parsing: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 18 } },
                tooltip: {
                    callbacks: {
                        title: (items) => `${items[0].parsed.x} %`,
                        label: (item) => `${item.dataset.label} : ${item.parsed.y} kW`,
                    },
                },
            },
            scales: {
                x: {
                    type: 'linear',
                    title: { display: true, text: 'Niveau de charge (%)' },
                    ticks: { maxTicksLimit: 12 },
                },
                y: {
                    title: { display: true, text: 'Puissance (kW)' },
                    beginAtZero: true,
                },
            },
        },
    });
}

document.querySelectorAll('canvas.curve-compare').forEach(renderComparison);
