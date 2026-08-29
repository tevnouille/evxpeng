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

function renderSocChart() {
    const canvas = document.getElementById('telemetry-soc-chart');

    if (!canvas) {
        return;
    }

    const labels = JSON.parse(canvas.dataset.labels);
    const soc = JSON.parse(canvas.dataset.soc);
    const charging = JSON.parse(canvas.dataset.charging);

    new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Niveau de charge',
                    data: soc,
                    borderColor: '#3273dc',
                    backgroundColor: 'rgba(50, 115, 220, 0.12)',
                    fill: true,
                    tension: 0.2,
                    // Un point vert marque les releves pris pendant une charge.
                    pointBackgroundColor: charging.map((c) => (c ? '#48c774' : '#3273dc')),
                    pointRadius: charging.map((c) => (c ? 4 : 2)),
                    // Les trous de telemetrie ne doivent pas etre relies : une
                    // ligne droite sur douze heures sans releve serait un mensonge.
                    spanGaps: false,
                },
            ],
        },
        options: {
            aspectRatio: 3,
            scales: {
                y: {
                    min: 0,
                    max: 100,
                    title: { display: true, text: 'Niveau de charge (%)' },
                    ticks: { callback: (value) => `${value} %` },
                },
                x: {
                    ticks: { maxTicksLimit: 12, autoSkip: true },
                },
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label(context) {
                            const suffix = charging[context.dataIndex] ? ' (en charge)' : '';

                            return `${context.parsed.y} %${suffix}`;
                        },
                    },
                },
            },
        },
    });
}

// La carte OpenStreetMap n'est inseree qu'au clic : tant que l'utilisateur ne
// la demande pas, la position du vehicule ne quitte pas le serveur.
function setUpMapToggle() {
    const button = document.getElementById('map-toggle');
    const container = document.getElementById('map-container');

    if (!button || !container) {
        return;
    }

    button.addEventListener('click', () => {
        if (container.childElementCount > 0) {
            container.innerHTML = '';
            button.textContent = 'Afficher la carte';

            return;
        }

        const lat = parseFloat(button.dataset.lat);
        const lon = parseFloat(button.dataset.lon);
        const delta = 0.008;
        const bbox = [lon - delta, lat - delta, lon + delta, lat + delta].join(',');

        const frame = document.createElement('iframe');
        frame.src = `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${lat},${lon}`;
        frame.width = '100%';
        frame.height = '360';
        frame.style.border = '1px solid #dbdbdb';
        frame.loading = 'lazy';
        frame.referrerPolicy = 'no-referrer';

        container.appendChild(frame);
        button.textContent = 'Masquer la carte';
    });
}

renderSocChart();
setUpMapToggle();
