const SUGGEST_DELAY = 300;
const SUGGEST_MIN_CHARS = 3;

/**
 * Saisie assistee des adresses.
 *
 * Liste maison et non <datalist> : le navigateur refiltre lui-meme les options
 * d'un datalist sur le texte saisi, en tenant compte des accents. Taper
 * "ville l'eveque" masquait donc "Rue de la Ville l'Eveque" que l'API venait
 * pourtant de renvoyer. Ici tout ce que le serveur propose reste visible.
 */
function wire(input) {
    const results = input.parentElement.querySelector('[data-address-results]');

    if (!results) {
        return;
    }

    // Champs caches recevant le point exact de la suggestion choisie. Re-geocoder
    // le libelle affiche ne suffit pas : la BAN ne rend pas toujours le meme
    // resultat pour le texte qu'elle vient de proposer.
    const form = input.form;
    const latField = form?.querySelector(`[name="${input.name}_lat"]`);
    const lonField = form?.querySelector(`[name="${input.name}_lon"]`);

    const setPoint = (suggestion) => {
        if (latField && lonField) {
            latField.value = suggestion ? suggestion.lat : '';
            lonField.value = suggestion ? suggestion.lon : '';
        }
    };

    let timer = null;
    let lastQuery = null;
    let items = [];
    let active = -1;

    const close = () => {
        results.hidden = true;
        items = [];
        active = -1;
    };

    const pick = (suggestion) => {
        // Le libelle seul, sans le contexte affiche a cote : "Villabe (91,
        // Essonne...)" renvoie a la BAN un chemin d'une commune voisine.
        input.value = suggestion.label;
        setPoint(suggestion);
        close();
    };

    const highlight = (index) => {
        active = index;
        items.forEach((item, position) => item.classList.toggle('is-active', position === index));

        if (items[index]) {
            items[index].scrollIntoView({ block: 'nearest' });
        }
    };

    const render = (suggestions) => {
        results.innerHTML = '';

        if (suggestions.length === 0) {
            close();

            return;
        }

        items = suggestions.map((suggestion) => {
            const item = document.createElement('a');
            item.className = 'dropdown-item';
            item.href = '#';
            item.appendChild(document.createTextNode(suggestion.label));

            if (suggestion.context) {
                const context = document.createElement('span');
                context.className = 'has-text-grey ml-2';
                context.textContent = suggestion.context;
                item.appendChild(context);
            }

            item.addEventListener('mousedown', (event) => {
                // mousedown et non click : le blur du champ fermerait la liste
                // avant que le clic ne soit delivre.
                event.preventDefault();
                pick(suggestion);
            });
            results.appendChild(item);
            item.suggestion = suggestion;

            return item;
        });

        results.hidden = false;
        active = -1;
    };

    input.addEventListener('input', () => {
        const query = input.value.trim();

        // Le texte ne correspond plus a la suggestion retenue : on repasse au
        // geocodage du texte libre.
        setPoint(null);
        window.clearTimeout(timer);

        if (query.length < SUGGEST_MIN_CHARS) {
            close();

            return;
        }

        timer = window.setTimeout(async () => {
            if (query === lastQuery) {
                return;
            }

            lastQuery = query;

            try {
                const response = await fetch(`/planificateur/adresses?q=${encodeURIComponent(query)}`, {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                render(payload.results || []);
            } catch (error) {
                // Sans suggestion, le champ libre reste parfaitement utilisable :
                // le geocodage cote serveur, lui, ignore accents et casse.
            }
        }, SUGGEST_DELAY);
    });

    input.addEventListener('keydown', (event) => {
        if (results.hidden || items.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            highlight((active + 1) % items.length);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            highlight((active - 1 + items.length) % items.length);
        } else if (event.key === 'Enter' && active >= 0) {
            event.preventDefault();
            pick(items[active].suggestion);
        } else if (event.key === 'Escape') {
            close();
        }
    });

    input.addEventListener('blur', () => window.setTimeout(close, 120));
}

export function wireAddressInputs() {
    document.querySelectorAll('[data-address-input]').forEach(wire);
}
