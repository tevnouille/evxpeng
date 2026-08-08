import React, { useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Tooltip,
    Legend,
} from 'chart.js';
import { Bar, Line, Doughnut } from 'react-chartjs-2';

ChartJS.register(CategoryScale, LinearScale, BarElement, LineElement, PointElement, ArcElement, Tooltip, Legend);

const GRANULARITIES = [
    { value: 'day', label: 'Jour' },
    { value: 'week', label: 'Semaine' },
    { value: 'month', label: 'Mois' },
    { value: 'year', label: 'Année' },
];

const PROVIDER_COLORS = ['#00d1b2', '#3273dc', '#ffdd57', '#ff3860', '#b86bff', '#ff9f40', '#48c78e', '#f14668'];

function StatTile({ label, value, suffix }) {
    return (
        <div className="column">
            <div className="box has-text-centered">
                <p className="heading">{label}</p>
                <p className="title is-4">{value}{suffix}</p>
            </div>
        </div>
    );
}

function Dashboard({ apiUrl }) {
    const [granularity, setGranularity] = useState('month');
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        setLoading(true);
        setError(null);
        fetch(`${apiUrl}?granularity=${granularity}`, { headers: { Accept: 'application/json' } })
            .then((res) => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json();
            })
            .then(setData)
            .catch((err) => setError(err.message))
            .finally(() => setLoading(false));
    }, [granularity, apiUrl]);

    const totals = useMemo(() => {
        if (!data) return null;
        const kwh = data.kwh.reduce((a, b) => a + b, 0);
        const cost = data.cost.reduce((a, b) => a + b, 0);
        const sessions = data.sessions_count.reduce((a, b) => a + b, 0);
        return {
            kwh: kwh.toFixed(2),
            cost: cost.toFixed(2),
            sessions,
            avgCostPerKwh: kwh > 0 ? (cost / kwh).toFixed(4) : '—',
        };
    }, [data]);

    return (
        <div>
            <div className="field has-addons mb-5">
                {GRANULARITIES.map((g) => (
                    <p className="control" key={g.value}>
                        <button
                            className={`button ${granularity === g.value ? 'is-primary' : ''}`}
                            onClick={() => setGranularity(g.value)}
                        >
                            {g.label}
                        </button>
                    </p>
                ))}
            </div>

            {error && <div className="notification is-danger is-light">Erreur de chargement : {error}</div>}

            {loading && <p>Chargement…</p>}

            {!loading && data && totals && (
                <>
                    <div className="columns is-mobile is-multiline mb-4">
                        <StatTile label="Total kWh" value={totals.kwh} />
                        <StatTile label="Total facturé" value={totals.cost} suffix=" €" />
                        <StatTile label="Coût moyen / kWh" value={totals.avgCostPerKwh} suffix=" €" />
                        <StatTile label="Nombre de recharges" value={totals.sessions} />
                    </div>

                    <div className="columns is-multiline">
                        <div className="column is-6">
                            <div className="box">
                                <h2 className="title is-5">kWh par période</h2>
                                <Bar
                                    data={{
                                        labels: data.labels,
                                        datasets: [{ label: 'kWh', data: data.kwh, backgroundColor: '#00d1b2' }],
                                    }}
                                    options={{ responsive: true, plugins: { legend: { display: false } } }}
                                />
                            </div>
                        </div>

                        <div className="column is-6">
                            <div className="box">
                                <h2 className="title is-5">Coût total par période (€)</h2>
                                <Line
                                    data={{
                                        labels: data.labels,
                                        datasets: [{ label: 'Coût (€)', data: data.cost, borderColor: '#3273dc', backgroundColor: '#3273dc', tension: 0.2 }],
                                    }}
                                    options={{ responsive: true, plugins: { legend: { display: false } } }}
                                />
                            </div>
                        </div>

                        <div className="column is-6">
                            <div className="box">
                                <h2 className="title is-5">Coût moyen par kWh (€)</h2>
                                <Line
                                    data={{
                                        labels: data.labels,
                                        datasets: [{ label: '€/kWh', data: data.avg_cost_per_kwh, borderColor: '#ffdd57', backgroundColor: '#ffdd57', tension: 0.2 }],
                                    }}
                                    options={{ responsive: true, plugins: { legend: { display: false } } }}
                                />
                            </div>
                        </div>

                        <div className="column is-6">
                            <div className="box">
                                <h2 className="title is-5">Répartition kWh par fournisseur</h2>
                                {data.by_provider.length > 0 ? (
                                    <Doughnut
                                        data={{
                                            labels: data.by_provider.map((p) => p.name),
                                            datasets: [{
                                                data: data.by_provider.map((p) => p.kwh),
                                                backgroundColor: data.by_provider.map((_, i) => PROVIDER_COLORS[i % PROVIDER_COLORS.length]),
                                            }],
                                        }}
                                        options={{ responsive: true }}
                                    />
                                ) : (
                                    <p className="has-text-grey">Pas encore de données.</p>
                                )}
                            </div>
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}

const root = document.getElementById('ev-dashboard-root');
if (root) {
    createRoot(root).render(<Dashboard apiUrl={root.dataset.apiUrl} />);
}
