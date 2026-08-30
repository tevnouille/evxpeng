import { attachSuggestions, fetchJson } from './suggest';

// En dessous de ce nombre d'options, la liste deroulante native reste plus
// pratique qu'un champ de recherche.
const SEARCHABLE_MIN_OPTIONS = 12;

function makeSearchable(select) {
    if (!select || select.dataset.searchableApplied === 'true') {
        return;
    }

    const realOptions = Array.from(select.options).filter((opt) => !opt.disabled && opt.value !== '');

    // Le champ de recherche s'appuie sur un <datalist>, que le navigateur filtre
    // sur le texte deja saisi. Sur un formulaire de modification il est
    // pre-rempli avec la valeur courante ("50 kW"), et la liste se reduit alors
    // aux options qui la contiennent — on ne voyait plus que 50 et 150 kW. Sur
    // une liste courte, la liste native evite completement le probleme.
    if (realOptions.length < SEARCHABLE_MIN_OPTIONS) {
        return;
    }

    select.dataset.searchableApplied = 'true';

    const datalistId = `${select.id}-datalist`;
    const datalist = document.createElement('datalist');
    datalist.id = datalistId;

    const valueByLabel = new Map();
    Array.from(select.options).forEach((opt) => {
        if (opt.disabled || opt.value === '') {
            return;
        }
        const label = opt.textContent.trim();
        valueByLabel.set(label, opt.value);
        const dOpt = document.createElement('option');
        dOpt.value = label;
        datalist.appendChild(dOpt);
    });

    const input = document.createElement('input');
    input.type = 'text';
    input.id = `${select.id}-search`;
    input.className = 'input';
    input.setAttribute('list', datalistId);
    input.setAttribute('autocomplete', 'off');
    input.placeholder = 'Rechercher…';

    const currentOption = select.options[select.selectedIndex];
    if (currentOption && currentOption.value !== '') {
        input.value = currentOption.textContent.trim();
    }

    select.insertAdjacentElement('beforebegin', input);
    select.insertAdjacentElement('beforebegin', datalist);
    select.style.display = 'none';

    // Au focus on vide le champ pour que le navigateur propose de nouveau toutes
    // les entrees ; si l'utilisateur repart sans choisir, on remet la valeur.
    let valueBeforeFocus = '';

    input.addEventListener('focus', () => {
        valueBeforeFocus = input.value;
        input.value = '';
    });

    input.addEventListener('blur', () => {
        if (input.value.trim() === '') {
            input.value = valueBeforeFocus;
        }
    });

    input.addEventListener('input', () => {
        const matchedValue = valueByLabel.get(input.value.trim());
        if (matchedValue !== undefined) {
            select.value = matchedValue;
            select.dispatchEvent(new Event('change'));
        }
    });
}

function setupOtherToggle(selectId, wrapperId) {
    const select = document.getElementById(selectId);
    const wrapper = document.getElementById(wrapperId);

    if (!select || !wrapper) {
        return;
    }

    const toggle = () => {
        wrapper.style.display = select.value === 'other' ? 'block' : 'none';
    };
    select.addEventListener('change', toggle);
    toggle();
}

function setupGeolocationButton() {
    const button = document.getElementById('geolocate_button');
    const select = document.getElementById('location_choice');
    const otherInput = document.getElementById('location_other');

    if (!button || !select || !otherInput) {
        return;
    }

    button.addEventListener('click', () => {
        if (!navigator.geolocation) {
            alert("La géolocalisation n'est pas disponible sur ce navigateur.");
            return;
        }

        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Localisation…';

        const restoreButton = () => {
            button.disabled = false;
            button.textContent = originalText;
        };

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const { latitude, longitude } = position.coords;
                const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latitude}&lon=${longitude}&zoom=14&addressdetails=1`;

                fetch(url, { headers: { Accept: 'application/json' } })
                    .then((res) => res.json())
                    .then((data) => {
                        const address = data.address || {};
                        const place = address.city || address.town || address.village
                            || address.municipality || address.suburb
                            || (data.display_name ? data.display_name.split(',')[0] : null);

                        if (!place) {
                            alert("Impossible de déterminer un nom de lieu à partir de cette position.");
                            return;
                        }

                        select.value = 'other';
                        select.dispatchEvent(new Event('change'));
                        otherInput.value = place;

                        const searchInput = document.getElementById('location_choice-search');
                        if (searchInput) {
                            searchInput.value = 'Autre…';
                        }
                    })
                    .catch(() => {
                        alert('Erreur lors de la récupération du nom du lieu.');
                    })
                    .finally(restoreButton);
            },
            (error) => {
                alert("Impossible d'obtenir votre position : " + error.message);
                restoreButton();
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });
}

// Comparaison "comme on tape" : sans accents ni casse.
function fold(value) {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
}

/**
 * Pose la valeur d'un select, y compris quand il a ete remplace par un champ de
 * recherche (voir makeSearchable) : sans mettre a jour le champ miroir,
 * l'utilisateur verrait l'ancien libelle.
 */
function selectValue(select, value) {
    select.value = value;
    select.dispatchEvent(new Event('change'));

    const mirror = document.getElementById(`${select.id}-search`);

    if (mirror) {
        const option = select.options[select.selectedIndex];
        mirror.value = option ? option.textContent.trim() : '';
    }
}

/**
 * Assistance a la saisie depuis la base nationale des bornes.
 *
 * Choisir une borne renseigne la localisation, et complete fournisseur et
 * puissance s'ils sont encore vides — jamais s'ils ont deja ete choisis, on ne
 * defait pas la saisie de l'utilisateur.
 */
function setupChargerSuggestions() {
    const locationOther = document.getElementById('location_other');
    const providerOther = document.getElementById('provider_other');
    const providerSelect = document.getElementById('provider_choice');
    const powerSelect = document.getElementById('power_rating_id');

    const fillProvider = (operator) => {
        if (!operator || !providerSelect) {
            return;
        }

        const alreadyChosen = providerSelect.value !== ''
            && (providerSelect.value !== 'other' || (providerOther && providerOther.value.trim() !== ''));

        if (alreadyChosen) {
            return;
        }

        const known = Array.from(providerSelect.options).find(
            (option) => option.value !== '' && option.value !== 'other'
                && fold(option.textContent.trim()) === fold(operator)
        );

        if (known) {
            selectValue(providerSelect, known.value);

            return;
        }

        selectValue(providerSelect, 'other');

        if (providerOther) {
            providerOther.value = operator;
        }
    };

    const fillPower = (power) => {
        if (!power || !powerSelect || powerSelect.value !== '') {
            return;
        }

        // La borne annonce sa puissance nominale ; on retient le palier
        // disponible immediatement inferieur ou egal, faute d'exact.
        const options = Array.from(powerSelect.options)
            .filter((option) => option.value !== '' && option.value !== 'other')
            .map((option) => ({ option, kw: parseFloat(option.textContent) }))
            .filter((entry) => !Number.isNaN(entry.kw))
            .sort((a, b) => a.kw - b.kw);

        const best = options.filter((entry) => entry.kw <= power).pop() ?? options[0];

        if (best) {
            selectValue(powerSelect, best.option.value);
        }
    };

    if (locationOther) {
        attachSuggestions(locationOther, {
            search: (query) => fetchJson(`/recharges/bornes?q=${encodeURIComponent(query)}`),
            render: (node, station) => {
                node.appendChild(document.createTextNode(station.city || station.name));

                const detail = document.createElement('span');
                detail.className = 'has-text-grey ml-2';
                detail.textContent = [station.name, station.operator, `${station.power_kw} kW`]
                    .filter(Boolean).join(' · ');
                node.appendChild(detail);
            },
            pick: (station) => {
                locationOther.value = station.city || station.name;
                fillProvider(station.operator);
                fillPower(station.power_kw);
            },
        });
    }

    if (providerOther) {
        attachSuggestions(providerOther, {
            search: (query) => fetchJson(`/recharges/fournisseurs?q=${encodeURIComponent(query)}`),
            render: (node, operator) => {
                node.appendChild(document.createTextNode(operator.name));

                const detail = document.createElement('span');
                detail.className = 'has-text-grey ml-2';
                detail.textContent = `${operator.stations} borne(s)`;
                node.appendChild(detail);
            },
            pick: (operator) => {
                providerOther.value = operator.name;
            },
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('select[data-searchable]').forEach(makeSearchable);

    const quantity = document.getElementById('quantity_kwh');
    const unitCost = document.getElementById('unit_cost');
    const totalCost = document.getElementById('total_cost');

    if (quantity && unitCost && totalCost) {
        let totalManuallyEdited = totalCost.value !== '' && parseFloat(totalCost.value) !== 0;

        totalCost.addEventListener('input', () => {
            totalManuallyEdited = true;
        });

        const recompute = () => {
            if (totalManuallyEdited) {
                return;
            }
            const q = parseFloat(quantity.value);
            const u = parseFloat(unitCost.value);
            if (!isNaN(q) && !isNaN(u)) {
                totalCost.value = (q * u).toFixed(2);
            }
        };

        quantity.addEventListener('input', recompute);
        unitCost.addEventListener('input', recompute);

        // En modification le total est toujours deja rempli, donc le calcul
        // automatique est desactive d'entree. Ce bouton le force, et reactive le
        // suivi tant que l'utilisateur ne retouche pas le total lui-meme.
        const recomputeButton = document.getElementById('recompute_total');

        if (recomputeButton) {
            recomputeButton.addEventListener('click', () => {
                const q = parseFloat(quantity.value);
                const u = parseFloat(unitCost.value);

                if (isNaN(q) || isNaN(u)) {
                    recomputeButton.classList.add('is-danger');
                    recomputeButton.title = 'Renseignez la quantité et le coût unitaire.';
                    setTimeout(() => recomputeButton.classList.remove('is-danger'), 1500);

                    return;
                }

                totalCost.value = (q * u).toFixed(2);
                totalManuallyEdited = false;

                recomputeButton.classList.add('is-success');
                setTimeout(() => recomputeButton.classList.remove('is-success'), 800);
            });
        }
    }

    setupOtherToggle('location_choice', 'location_other_wrapper');
    setupOtherToggle('provider_choice', 'provider_other_wrapper');
    setupChargerSuggestions();
    setupGeolocationButton();
});
