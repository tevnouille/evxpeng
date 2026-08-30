const SUGGEST_DELAY = 250;

/**
 * Liste de suggestions maison attachee a un champ texte.
 *
 * Liste maison et non <datalist> : le navigateur refiltre lui-meme les options
 * d'un datalist sur le texte saisi, en tenant compte des accents. Taper
 * "ville l'eveque" masquait ainsi "Rue de la Ville l'Eveque" que l'API venait
 * pourtant de renvoyer. Ici tout ce que le serveur propose reste visible.
 *
 * Le conteneur d'affichage est le premier `[data-suggestions]` voisin du champ.
 *
 * @param {HTMLInputElement} input
 * @param {{search: Function, render: Function, pick: Function, type?: Function, minChars?: number}} handlers
 */
export function attachSuggestions(input, handlers) {
    const results = input.parentElement.querySelector('[data-suggestions]');

    if (!results) {
        return;
    }

    const minChars = handlers.minChars ?? 3;

    let timer = null;
    let lastQuery = null;
    let items = [];
    let active = -1;

    const close = () => {
        results.hidden = true;
        items = [];
        active = -1;
    };

    const choose = (index) => {
        if (items[index]) {
            handlers.pick(items[index].suggestion);
            close();
        }
    };

    const highlight = (index) => {
        active = index;
        items.forEach((item, position) => item.classList.toggle('is-active', position === index));
        items[index]?.scrollIntoView({ block: 'nearest' });
    };

    const draw = (suggestions) => {
        results.innerHTML = '';

        if (suggestions.length === 0) {
            close();

            return;
        }

        items = suggestions.map((suggestion) => {
            const item = document.createElement('a');
            item.className = 'dropdown-item';
            item.href = '#';
            handlers.render(item, suggestion);
            item.addEventListener('mousedown', (event) => {
                // mousedown et non click : le blur du champ fermerait la liste
                // avant que le clic ne soit delivre.
                event.preventDefault();
                handlers.pick(suggestion);
                close();
            });
            item.suggestion = suggestion;
            results.appendChild(item);

            return item;
        });

        results.hidden = false;
        active = -1;
    };

    input.addEventListener('input', () => {
        const query = input.value.trim();

        handlers.type?.();
        window.clearTimeout(timer);

        if (query.length < minChars) {
            close();

            return;
        }

        timer = window.setTimeout(async () => {
            if (query === lastQuery) {
                return;
            }

            lastQuery = query;

            try {
                draw(await handlers.search(query));
            } catch (error) {
                // Sans suggestion, la saisie libre reste parfaitement utilisable.
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
            choose(active);
        } else if (event.key === 'Escape') {
            close();
        }
    });

    input.addEventListener('blur', () => window.setTimeout(close, 120));
}

/** @return {Promise<Array>} */
export async function fetchJson(url) {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });

    if (!response.ok) {
        return [];
    }

    const payload = await response.json();

    return payload.results ?? [];
}
