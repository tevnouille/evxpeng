// Comparaison "comme on tape" : sans accents ni casse. "electra" doit trouver
// "ELECTRA", "energie" doit trouver "Alterna Energie".
function fold(value) {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
}

/**
 * Filtre du choix des reseaux : la liste en compte plusieurs centaines, la
 * derouler jusqu'a "ELECTRA" n'est pas raisonnable.
 */
export function wireNetworkFilter() {
    const filter = document.querySelector('[data-network-filter]');
    const select = document.getElementById('reseaux');

    if (!filter || !select) {
        return;
    }

    const options = Array.from(select.options);

    filter.addEventListener('input', () => {
        const needle = fold(filter.value.trim());

        options.forEach((option) => {
            // Un reseau deja coche reste visible, sinon le filtre donnerait
            // l'impression de l'avoir deselectionne.
            option.hidden = needle !== '' && !option.selected && !fold(option.text).includes(needle);
        });
    });

    // Entree dans un champ de filtre : ne pas soumettre le formulaire par megarde.
    filter.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
        }
    });
}
