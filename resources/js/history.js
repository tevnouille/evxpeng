import { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

document.addEventListener('DOMContentLoaded', () => {
    const kwhCanvas = document.getElementById('history-kwh-chart');
    const costCanvas = document.getElementById('history-cost-chart');

    if (!kwhCanvas || !costCanvas) {
        return;
    }

    const labels = JSON.parse(kwhCanvas.dataset.labels || '[]');
    const kwhValues = JSON.parse(kwhCanvas.dataset.values || '[]');
    const costValues = JSON.parse(costCanvas.dataset.values || '[]');

    new Chart(kwhCanvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{ label: 'kWh', data: kwhValues, backgroundColor: '#00d1b2' }],
        },
        options: { responsive: true, aspectRatio: 4, plugins: { legend: { display: false } } },
    });

    new Chart(costCanvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{ label: 'Coût (€)', data: costValues, backgroundColor: '#3273dc' }],
        },
        options: { responsive: true, aspectRatio: 4, plugins: { legend: { display: false } } },
    });
});
