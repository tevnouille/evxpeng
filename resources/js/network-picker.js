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

        const apply = () => {
            const needle = fold(filter.value.trim());

            // Les options sont relues a chaque fois, jamais capturees : dans le
            // selecteur a deux colonnes elles changent de <select>, et un
            // instantane pris au chargement continuait d'en masquer qui etaient
            // passees dans la colonne des reseaux retenus.
            Array.from(select.options).forEach((option) => {
                option.hidden = needle !== '' && !fold(option.text).includes(needle);
            });
        };

        filter.addEventListener('input', apply);
        // Rejoue le filtre apres un aller-retour entre les deux colonnes.
        select.addEventListener('optionsmoved', apply);

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

    // Les compteurs sont rendus par le serveur : sans cela ils resteraient
    // figes sur l'etat d'ouverture de la page, et un reseau ajoute donnerait
    // l'impression de n'avoir rien change.
    const counters = {
        disponibles: document.querySelector('[data-network-count="disponibles"]'),
        retenus: document.querySelector('[data-network-count="retenus"]'),
    };
    const emptyNote = document.querySelector('[data-network-empty]');

    const refresh = () => {
        if (counters.disponibles) {
            counters.disponibles.textContent = String(available.options.length);
        }

        if (counters.retenus) {
            counters.retenus.textContent = String(chosen.options.length);
        }

        if (emptyNote) {
            emptyNote.hidden = chosen.options.length > 0;
        }
    };

    const move = (from, to) => {
        const moved = Array.from(from.selectedOptions);

        if (moved.length === 0) {
            return;
        }

        moved.forEach((option) => {
            option.selected = false;
            option.hidden = false;
            to.appendChild(option);
        });

        Array.from(to.options).sort(byLabel).forEach((option) => to.appendChild(option));

        // Le filtre eventuellement pose sur la colonne de gauche doit se
        // reappliquer aux options qui viennent d'y revenir.
        [from, to].forEach((select) => select.dispatchEvent(new Event('optionsmoved')));

        refresh();
    };

    document.querySelector('[data-network-add]')?.addEventListener('click', () => move(available, chosen));
    document.querySelector('[data-network-remove]')?.addEventListener('click', () => move(chosen, available));

    // Double-clic : le geste attendu sur ce genre de liste.
    available.addEventListener('dblclick', () => move(available, chosen));
    chosen.addEventListener('dblclick', () => move(chosen, available));

    // Un <select multiple> ne poste que ses options selectionnees : sans cela,
    // le formulaire n'enverrait que les lignes surlignees de la colonne droite.
    refresh();

    chosen.form?.addEventListener('submit', () => {
        Array.from(chosen.options).forEach((option) => {
            option.selected = true;
        });
    });
}
