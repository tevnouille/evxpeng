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

// Une cinquantaine de courbes sur la meme page : chacune reste volontairement
// sobre — pas de legende, pas de point, une graduation allegee. C'est la forme
// qu'on lit d'un coup d'oeil, le detail se prend au survol.
function renderChart(canvas) {
    const labels = JSON.parse(canvas.dataset.labels || '[]');
    const values = JSON.parse(canvas.dataset.values || '[]');
    const unit = canvas.dataset.unit || '';

    if (labels.length === 0) {
        return;
    }

    new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    data: values,
                    borderColor: '#3e8ed0',
                    backgroundColor: 'rgba(62, 142, 208, 0.12)',
                    borderWidth: 1.5,
                    fill: true,
                    tension: 0.25,
                    pointRadius: 0,
                    pointHitRadius: 10,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (item) => `${item.parsed.y}${unit ? ' ' + unit : ''}`,
                    },
                },
            },
            scales: {
                x: {
                    ticks: {
                        maxTicksLimit: 6,
                        autoSkip: true,
                        maxRotation: 0,
                    },
                    grid: { display: false },
                },
                y: {
                    ticks: { maxTicksLimit: 5 },
                    // Pas de zero force : sur une tension de 620 a 640 V, partir
                    // de zero ecraserait toute la variation en une ligne plate.
                    beginAtZero: false,
                },
            },
        },
    });
}

// La page porte une cinquantaine de courbes, soit des dizaines de milliers de
// points : les tracer toutes au chargement fige le navigateur plusieurs
// secondes. Chacune n'est donc construite qu'a son approche, et une seule fois.
//
// Un IntersectionObserver serait l'outil naturel, mais il ne rapporte rien dans
// un contexte qui ne compose pas la page — onglet jamais affiche, vue integree,
// capture automatisee. Le symptome serait cinquante cadres vides sans la
// moindre erreur. On mesure donc les positions soi-meme : c'est une poignee de
// lignes, et cela ne peut pas ne rien faire.
const pending = new Set(document.querySelectorAll('canvas.obd-chart'));

function renderNearViewport() {
    if (pending.size === 0) {
        return;
    }

    const margin = 250;

    pending.forEach((canvas) => {
        const box = canvas.getBoundingClientRect();

        if (box.top < window.innerHeight + margin && box.bottom > -margin) {
            pending.delete(canvas);
            renderChart(canvas);
        }
    });

    if (pending.size === 0) {
        window.removeEventListener('scroll', schedule);
        window.removeEventListener('resize', schedule);
    }
}

// Etranglement a l'horodatage plutot qu'a la trame d'affichage. Un
// requestAnimationFrame serait plus naturel, mais il depend du compositeur au
// meme titre que l'IntersectionObserver : dans un contexte qui ne dessine pas,
// il n'est jamais rappele, et la page resterait vide sans erreur. Le compteur
// et le minuteur, eux, fonctionnent partout.
const THROTTLE_MS = 100;
let lastRun = 0;
let trailing = null;

function schedule() {
    const wait = THROTTLE_MS - (Date.now() - lastRun);

    if (wait <= 0) {
        lastRun = Date.now();
        renderNearViewport();

        return;
    }

    // Un defilement qui s'arrete entre deux mesures doit tout de meme finir par
    // etre pris en compte, d'ou ce dernier passage differe.
    if (trailing === null) {
        trailing = window.setTimeout(() => {
            trailing = null;
            lastRun = Date.now();
            renderNearViewport();
        }, wait);
    }
}

window.addEventListener('scroll', schedule, { passive: true });
window.addEventListener('resize', schedule, { passive: true });
renderNearViewport();
