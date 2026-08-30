// Comparaison "comme on tape" : sans accents ni casse. "electra" doit trouver
// "ELECTRA", "energie" doit trouver "Alterna Energie".
function fold(value) {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
}

/**
 * Filtre de recherche pose au-dessus d'une liste de reseaux.
 *
 * La liste en compte plusieurs centaines : la derouler jusqu'a "ELECTRA" n'est
 * pas raisonnable. Le select vise est celui que designe `aria-controls`, ce qui
 * permet au meme code de servir la liste simple du planificateur et la colonne
 * de gauche du selecteur des favoris.
 */
export function wireNetworkFilter() {
    document.querySelectorAll('[data-network-filter]').forEach((filter) => {
        const select = document.getElementById(filter.getAttribute('aria-controls') ?? 'reseaux');

        if (!select) {
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
    });
}

/**
 * Selecteur a deux colonnes : disponibles a gauche, retenus a droite.
 *
 * Un simple <select multiple> ne dit pas ce qui est retenu sans faire defiler
 * toute la liste ; ici la colonne de droite ne contient que les choix faits.
 */
export function wireNetworkPicker() {
    const available = document.getElementById('reseaux-disponibles');
    const chosen = document.getElementById('reseaux');

    if (!available || !chosen) {
        return;
    }

    const byLabel = (a, b) => a.text.localeCompare(b.text, 'fr');

    const move = (from, to) => {
        Array.from(from.selectedOptions).forEach((option) => {
            option.selected = false;
            option.hidden = false;
            to.appendChild(option);
        });

        Array.from(to.options).sort(byLabel).forEach((option) => to.appendChild(option));
    };

    document.querySelector('[data-network-add]')?.addEventListener('click', () => move(available, chosen));
    document.querySelector('[data-network-remove]')?.addEventListener('click', () => move(chosen, available));

    // Double-clic : le geste attendu sur ce genre de liste.
    available.addEventListener('dblclick', () => move(available, chosen));
    chosen.addEventListener('dblclick', () => move(chosen, available));

    // Un <select multiple> ne poste que ses options selectionnees : sans cela,
    // le formulaire n'enverrait que les lignes surlignees de la colonne droite.
    chosen.form?.addEventListener('submit', () => {
        Array.from(chosen.options).forEach((option) => {
            option.selected = true;
        });
    });
}
