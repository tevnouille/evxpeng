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

function StatTile({ label, value, suffix, valueClass, note }) {
    return (
        <div className="column">
            <div className="box has-text-centered">
                <p className="heading">{label}</p>
                <p className={`title is-4 ${valueClass ?? ''}`}>{value}{suffix}</p>
                {note && <p className="has-text-grey is-size-7">{note}</p>}
            </div>
        </div>
    );
}

function Dashboard({ apiUrl, fuelPricesUrl, vehicles, years, currentYear }) {
    const [granularity, setGranularity] = useState('month');
    const [vehicleId, setVehicleId] = useState('');
    const [year, setYear] = useState(currentYear ? String(currentYear) : '');
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        setLoading(true);
        setError(null);
        const params = new URLSearchParams({ granularity });
        if (vehicleId) params.set('vehicle_id', vehicleId);
        if (year) params.set('year', year);
        fetch(`${apiUrl}?${params.toString()}`, { headers: { Accept: 'application/json' } })
            .then((res) => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json();
            })
            .then(setData)
            .catch((err) => setError(err.message))
            .finally(() => setLoading(false));
    }, [granularity, vehicleId, year, apiUrl]);

    const totals = useMemo(() => {
        if (!data) return null;
        const kwh = data.kwh.reduce((a, b) => a + b, 0);
        const cost = data.cost.reduce((a, b) => a + b, 0);
        const realCost = (data.real_cost ?? []).reduce((a, b) => a + b, 0);
        const sessions = data.sessions_count.reduce((a, b) => a + b, 0);
        return {
            kwh: kwh.toFixed(2),
            cost: cost.toFixed(2),
            realCost: realCost.toFixed(2),
            gain: cost - realCost,
            sessions,
            avgCostPerKwh: kwh > 0 ? (cost / kwh).toFixed(4) : '—',
        };
    }, [data]);

    // Superposer deux courbes identiques n'apprend rien : la courbe du cout reel
    // n'apparait que si au moins une periode s'ecarte du cout facture.
    const hasGain = useMemo(
        () => (data?.gain ?? []).some((g) => Math.abs(g) >= 0.01),
        [data]
    );

    return (
        <div>
            <div className="field is-grouped is-grouped-multiline mb-5">
                <div className="control">
                    <div className="field has-addons">
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
                </div>
                {vehicles.length > 0 && (
                    <div className="control">
                        <div className="select">
                            <select value={vehicleId} onChange={(e) => setVehicleId(e.target.value)}>
                                <option value="">Tous les véhicules</option>
                                {vehicles.map((v) => (
                                    <option value={v.id} key={v.id}>{v.name}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                )}
                {years.length > 0 && (
                    <div className="control">
                        <div className="select">
                            <select value={year} onChange={(e) => setYear(e.target.value)}>
                                <option value="">Toutes les années</option>
                                {years.map((y) => (
                                    <option value={y} key={y}>{y}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                )}
            </div>

            {error && <div className="notification is-danger is-light">Erreur de chargement : {error}</div>}

            {loading && <p>Chargement…</p>}

            {!loading && data && totals && (
                <>
                    <div className="columns is-mobile is-multiline mb-4">
                        <StatTile label="Total kWh" value={totals.kwh} />
                        <StatTile label="Total facturé" value={totals.cost} suffix=" €" />
                        <StatTile label="Total réel" value={totals.realCost} suffix=" €" />
                        <StatTile
                            label="Gain"
                            value={totals.gain.toFixed(2)}
                            suffix=" €"
                            valueClass={totals.gain < 0 ? 'has-text-success' : totals.gain > 0 ? 'has-text-danger' : ''}
                            note="coût facturé − coût réel"
                        />
                        <StatTile label="Coût moyen / kWh" value={totals.avgCostPerKwh} suffix=" €" />
                        <StatTile label="Nombre de recharges" value={totals.sessions} />
                    </div>

                    {data.fuel_equivalent && (
                        <>
                            <h2 className="title is-6 mb-2">Équivalent carburant</h2>
                            <div className="columns is-mobile is-multiline mb-1">
                                <StatTile label="Essence équivalente" value={data.fuel_equivalent.essence_liters} suffix=" L" />
                                <StatTile label="Coût essence équivalent" value={data.fuel_equivalent.essence_cost.toFixed(2)} suffix=" €" />
                                <StatTile
                                    label={data.fuel_equivalent.savings_essence >= 0 ? 'Économie vs essence' : 'Surcoût vs essence'}
                                    value={Math.abs(data.fuel_equivalent.savings_essence).toFixed(2)}
                                    suffix=" €"
                                />
                                <StatTile label="Diesel équivalent" value={data.fuel_equivalent.diesel_liters} suffix=" L" />
                                <StatTile label="Coût diesel équivalent" value={data.fuel_equivalent.diesel_cost.toFixed(2)} suffix=" €" />
                                <StatTile
                                    label={data.fuel_equivalent.savings_diesel >= 0 ? 'Économie vs diesel' : 'Surcoût vs diesel'}
                                    value={Math.abs(data.fuel_equivalent.savings_diesel).toFixed(2)}
                                    suffix=" €"
                                />
                            </div>
                            <p className="is-size-7 has-text-grey mb-5">
                                Consommation propre à chaque véhicule (Administration → Véhicules) — aucune valeur par défaut :
                                une recharge dont le véhicule n'a pas de consommation renseignée n'est pas comptée dans cet équivalent
                                {data.fuel_equivalent.configured_sessions < data.fuel_equivalent.total_sessions && (
                                    <> ({data.fuel_equivalent.configured_sessions}/{data.fuel_equivalent.total_sessions} recharge(s) concernée(s))</>
                                )}.
                                Prix appliqués par date de recharge réelle
                                (moyenne pondérée obtenue : {data.fuel_equivalent.avg_essence_price ?? '—'} €/L essence, {data.fuel_equivalent.avg_diesel_price ?? '—'} €/L diesel) —
                                {' '}{data.fuel_equivalent.known_price_sessions}/{data.fuel_equivalent.total_sessions} recharge(s) avec un prix du jour connu
                                {data.fuel_equivalent.estimated && ", le reste utilise l'estimation par défaut (1,95 €/L)"}.
                                {' '}
                                <a href={fuelPricesUrl}>Voir l'historique des prix carburants →</a>
                            </p>
                        </>
                    )}

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
                                <h2 className="title is-5">Coût électrique vs équivalent carburant (€)</h2>
                                <Line
                                    data={{
                                        labels: data.labels,
                                        datasets: [
                                            { label: 'Coût facturé (€)', data: data.cost, borderColor: '#3273dc', backgroundColor: '#3273dc', tension: 0.2 },
                                            ...(hasGain ? [{ label: 'Coût réel (€)', data: data.real_cost, borderColor: '#48c78e', backgroundColor: '#48c78e', tension: 0.2 }] : []),
                                            { label: 'Équivalent essence (€)', data: data.fuel_equivalent_essence_cost, borderColor: '#ff3860', backgroundColor: '#ff3860', tension: 0.2, borderDash: [6, 4] },
                                            { label: 'Équivalent diesel (€)', data: data.fuel_equivalent_diesel_cost, borderColor: '#ffa94d', backgroundColor: '#ffa94d', tension: 0.2, borderDash: [2, 3] },
                                        ],
                                    }}
                                    options={{ responsive: true, plugins: { legend: { display: true } } }}
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

                    {/* Recapitulatif chiffre sous les camemberts : ils donnent la
                        forme, le tableau donne les montants. Meme ordre que le
                        camembert des kWh, pour que les couleurs se retrouvent. */}
                    <div className="box">
                        <h2 className="title is-5">Récapitulatif par fournisseur</h2>
                        {data.by_provider.length > 0 ? (
                            <div className="table-container">
                                <table className="table is-fullwidth is-striped is-hoverable">
                                    <thead>
                                        <tr>
                                            <th>Fournisseur</th>
                                            <th className="has-text-right">Recharges</th>
                                            <th className="has-text-right">kWh</th>
                                            <th className="has-text-right">Coût</th>
                                            <th className="has-text-right">Coût / kWh</th>
                                            <th className="has-text-right">Part du coût</th>
                                            <th className="has-text-right">Gain</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.by_provider.map((p, i) => (
                                            <tr key={p.name}>
                                                <td>
                                                    <span
                                                        className="tag mr-2"
                                                        style={{
                                                            backgroundColor: PROVIDER_COLORS[i % PROVIDER_COLORS.length],
                                                            width: '.8rem',
                                                            minWidth: '.8rem',
                                                            padding: 0,
                                                        }}
                                                    />
                                                    {p.name}
                                                </td>
                                                <td className="has-text-right">{p.sessions_count}</td>
                                                <td className="has-text-right">{p.kwh.toFixed(2)}</td>
                                                <td className="has-text-right">{p.cost.toFixed(2)} €</td>
                                                <td className="has-text-right">
                                                    {p.avg_cost_per_kwh !== null ? `${p.avg_cost_per_kwh.toFixed(4)} €` : '—'}
                                                </td>
                                                <td className="has-text-right">
                                                    {p.cost_share !== null ? `${p.cost_share.toFixed(1)} %` : '—'}
                                                </td>
                                                <td className={`has-text-right ${p.gain < 0 ? 'has-text-success' : ''}`}>
                                                    {p.gain.toFixed(2)} €
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th>Total</th>
                                            <th className="has-text-right">
                                                {data.by_provider.reduce((n, p) => n + p.sessions_count, 0)}
                                            </th>
                                            <th className="has-text-right">
                                                {data.by_provider.reduce((n, p) => n + p.kwh, 0).toFixed(2)}
                                            </th>
                                            <th className="has-text-right">
                                                {data.by_provider.reduce((n, p) => n + p.cost, 0).toFixed(2)} €
                                            </th>
                                            <th />
                                            <th className="has-text-right">
                                                {data.by_provider.reduce((n, p) => n + (p.cost_share ?? 0), 0).toFixed(1)} %
                                            </th>
                                            <th className="has-text-right">
                                                {data.by_provider.reduce((n, p) => n + p.gain, 0).toFixed(2)} €
                                            </th>
                                        </tr>
                                    </tfoot>
                                </table>
                                <p className="has-text-grey is-size-7">
                                    Le <strong>gain</strong> compare ce qui a été débité à ce que la recharge valait&nbsp;:
                                    négatif quand elle a coûté moins que sa valeur. Une recharge sans fournisseur
                                    renseigné compte dans les totaux du haut mais pas ici, d'où une part cumulée
                                    parfois inférieure à 100&nbsp;%.
                                </p>
                            </div>
                        ) : (
                            <p className="has-text-grey">Pas encore de données.</p>
                        )}
                    </div>
                </>
            )}
        </div>
    );
}

const root = document.getElementById('ev-dashboard-root');
if (root) {
    const vehicles = JSON.parse(root.dataset.vehicles || '[]');
    const years = JSON.parse(root.dataset.years || '[]');
    const currentYear = root.dataset.currentYear || '';
    createRoot(root).render(<Dashboard apiUrl={root.dataset.apiUrl} fuelPricesUrl={root.dataset.fuelPricesUrl} vehicles={vehicles} years={years} currentYear={currentYear} />);
}
