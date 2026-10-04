/**
 * Filtrage des inventaires de paquets.
 *
 * Les trois tableaux depassent les 900 lignes a eux trois : sans filtre, la
 * page est illisible. Tout se fait sur le rendu deja envoye — aucune requete,
 * et les compteurs disent ce qui reste visible.
 */
function setupGroup(group) {
    const rows = Array.from(group.querySelectorAll('[data-package-row]'));
    const filter = group.querySelector('[data-package-filter]');
    const todoOnly = group.querySelector('[data-package-todo]');
    const counter = group.querySelector('[data-package-count]');

    if (rows.length === 0) {
        return;
    }

    const apply = () => {
        const needle = (filter?.value ?? '').trim().toLowerCase();
        const todo = todoOnly?.checked ?? false;
        let shown = 0;

        rows.forEach((row) => {
            const matchesText = needle === '' || row.dataset.search.includes(needle);
            const matchesTodo = !todo || !['a-jour', 'indirecte', 'inconnu'].includes(row.dataset.verdict);
            const visible = matchesText && matchesTodo;

            row.hidden = !visible;
            if (visible) {
                shown += 1;
            }
        });

        if (counter) {
            counter.textContent = shown === rows.length
                ? `${rows.length} affiché(s)`
                : `${shown} sur ${rows.length} affiché(s)`;
        }
    };

    filter?.addEventListener('input', apply);
    todoOnly?.addEventListener('change', apply);
    apply();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-package-group]').forEach(setupGroup);
});
