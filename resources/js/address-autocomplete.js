import { attachSuggestions, fetchJson } from './suggest';

/**
 * Saisie assistee des adresses du planificateur et des trajets favoris.
 */
export function wireAddressInputs() {
    document.querySelectorAll('[data-address-input]').forEach((input) => {
        // Champs caches recevant le point exact de la suggestion choisie.
        // Re-geocoder le libelle affiche ne suffit pas : la BAN ne rend pas
        // toujours le meme resultat pour le texte qu'elle vient de proposer.
        const form = input.form;
        const latField = form?.querySelector(`[name="${input.name}_lat"]`);
        const lonField = form?.querySelector(`[name="${input.name}_lon"]`);

        const setPoint = (suggestion) => {
            if (latField && lonField) {
                latField.value = suggestion ? suggestion.lat : '';
                lonField.value = suggestion ? suggestion.lon : '';
            }
        };

        attachSuggestions(input, {
            search: (query) => fetchJson(`/planificateur/adresses?q=${encodeURIComponent(query)}`),
            render: (node, suggestion) => {
                node.appendChild(document.createTextNode(suggestion.label));

                if (suggestion.context) {
                    const context = document.createElement('span');
                    context.className = 'has-text-grey ml-2';
                    context.textContent = suggestion.context;
                    node.appendChild(context);
                }
            },
            pick: (suggestion) => {
                // Le libelle seul, sans le contexte affiche a cote : "Villabe
                // (91, Essonne...)" renvoie a la BAN un chemin d'une commune
                // voisine.
                input.value = suggestion.label;
                setPoint(suggestion);
            },
            // Le texte ne correspond plus a la suggestion retenue : on repasse
            // au geocodage du texte libre.
            type: () => setPoint(null),
        });
    });
}
