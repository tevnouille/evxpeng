import {
    Chart,
    Filler,
    LineController,
    LineElement,
    PointElement,
    CategoryScale,
    LinearScale,
    Tooltip,
} from 'chart.js';

Chart.register(LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Filler);

function readData(canvas, attribute) {
    return JSON.parse(canvas.dataset[attribute]);
}

document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('curve-power-chart');

    if (!canvas) {
        return;
    }

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
                x: {
                    title: { display: true, text: 'Niveau de batterie (%)' },
                    ticks: {
                        callback(value) {
                            const soc = this.getLabelForValue(value);
                            return soc % 10 === 0 ? `${soc} %` : '';
                        },
                        autoSkip: false,
                        maxRotation: 0,
                    },
                },
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Puissance (kW)' },
                    ticks: { callback: (value) => `${value} kW` },
                },
            },
        },
    });
});
