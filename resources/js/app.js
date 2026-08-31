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

/**
 * Bascule entre la liste existante et la saisie d'une nouvelle entree.
 *
 * La liste des localisations depasse la vingtaine : chercher "Autre..." tout en
 * bas d'un menu deroulant devenait penible. Les deux modes s'excluent donc a
 * l'ecran — on ne voit que celui dans lequel on est — et on y entre par un
 * bouton visible autant que par la liste, ou l'option est passee en tete.
 *
 * @param {string} selectId
 * @param {string} wrapperId conteneur de la saisie libre
 * @param {string} pickWrapperId conteneur de la liste
 */
function setupOtherToggle(selectId, wrapperId, pickWrapperId) {
    const select = document.getElementById(selectId);
    const wrapper = document.getElementById(wrapperId);
    const pickWrapper = document.getElementById(pickWrapperId);

    if (!select || !wrapper) {
        return;
    }

    // Valeur a restaurer si l'utilisateur revient a la liste sans rien creer.
    let lastListValue = select.value === 'other' ? '' : select.value;

    const toggle = () => {
        const isOther = select.value === 'other';
        wrapper.style.display = isOther ? 'block' : 'none';

        if (pickWrapper) {
            pickWrapper.style.display = isOther ? 'none' : 'block';
        }
        if (!isOther) {
            lastListValue = select.value;
        }
    };

    select.addEventListener('change', toggle);
    toggle();

    document.querySelectorAll(`[data-other-for="${selectId}"]`).forEach((button) => {
        button.addEventListener('click', () => {
            select.value = 'other';
            select.dispatchEvent(new Event('change'));

            const input = document.getElementById(button.dataset.otherInput);
            if (input) {
                input.focus();
            }
        });
    });

    document.querySelectorAll(`[data-back-to-list="${selectId}"]`).forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            selectValue(select, lastListValue);
        });
    });
}

/**
 * Ouvre le calendrier (ou l'horloge) des le clic dans le champ.
 *
 * Sans cela, seule la petite icone ouvre le selecteur natif : cliquer dans le
 * champ ne propose que la saisie au clavier. showPicker() n'existe pas partout
 * et refuse d'etre appelee hors geste utilisateur, d'ou le filet.
 */
function setupNativePickers() {
    document.querySelectorAll('input[type="date"], input[type="time"]').forEach((input) => {
        if (typeof input.showPicker !== 'function') {
            return;
        }

        input.addEventListener('click', () => {
            try {
                input.showPicker();
            } catch {
                // Navigateur qui refuse : la saisie clavier reste possible.
            }
        });
    });
}

/**
 * Position courante du navigateur, sous forme de promesse.
 *
 * Deux boutons en ont besoin — nommer la ville, et chercher les bornes
 * alentour — d'ou la mise en commun, y compris de l'etat "en cours" du bouton
 * qui l'a demandee.
 */
function currentPosition(button, pendingLabel) {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error("La géolocalisation n'est pas disponible sur ce navigateur."));

            return;
        }

        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = pendingLabel;

        const restore = () => {
            button.disabled = false;
            button.textContent = originalText;
        };

        navigator.geolocation.getCurrentPosition(
            (position) => {
                restore();
                resolve(position.coords);
            },
            (error) => {
                restore();
                reject(new Error("Impossible d'obtenir votre position : " + error.message));
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });
}

function setupGeolocationButton() {
    const button = document.getElementById('geolocate_button');
    const select = document.getElementById('location_choice');
    const otherInput = document.getElementById('location_other');

    if (!button || !select || !otherInput) {
        return;
    }

    button.addEventListener('click', () => {
        currentPosition(button, 'Localisation…')
            .then(({ latitude, longitude }) => {
                const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latitude}&lon=${longitude}&zoom=14&addressdetails=1`;

                return fetch(url, { headers: { Accept: 'application/json' } })
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

                        selectValue(select, 'other');
                        otherInput.value = place;
                    })
                    .catch(() => {
                        alert('Erreur lors de la récupération du nom du lieu.');
                    });
            })
            .catch((error) => alert(error.message));
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
 * Renseigne le fournisseur a partir de l'operateur declare par la borne.
 *
 * Par defaut on ne touche a rien si un fournisseur est deja choisi : une
 * suggestion ne doit pas defaire la saisie en cours. `force` leve cette reserve
 * pour les gestes explicites — cliquer "Ajouter" sur une borne, c'est demander
 * qu'elle remplace ce qui etait la.
 */
function fillProvider(operator, { force = false } = {}) {
    const providerSelect = document.getElementById('provider_choice');
    const providerOther = document.getElementById('provider_other');

    if (!operator || !providerSelect) {
        return;
    }

    const alreadyChosen = providerSelect.value !== ''
        && (providerSelect.value !== 'other' || (providerOther && providerOther.value.trim() !== ''));

    if (alreadyChosen && !force) {
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
}

function fillPower(power, { force = false } = {}) {
    const powerSelect = document.getElementById('power_rating_id');

    if (!power || !powerSelect || (powerSelect.value !== '' && !force)) {
        return;
    }

    // La borne annonce sa puissance nominale ; on retient le palier disponible
    // immediatement inferieur ou egal, faute d'exact.
    const options = Array.from(powerSelect.options)
        .filter((option) => option.value !== '' && option.value !== 'other')
        .map((option) => ({ option, kw: parseFloat(option.textContent) }))
        .filter((entry) => !Number.isNaN(entry.kw))
        .sort((a, b) => a.kw - b.kw);

    const best = options.filter((entry) => entry.kw <= power).pop() ?? options[0];

    if (best) {
        selectValue(powerSelect, best.option.value);
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

/**
 * Renseigne la localisation a partir d'une borne.
 *
 * Si une localisation du meme nom existe deja dans la liste de l'utilisateur,
 * on la reutilise plutot que d'en creer une deuxieme a l'identique.
 */
function fillLocation(station) {
    const select = document.getElementById('location_choice');
    const otherInput = document.getElementById('location_other');

    if (!select) {
        return;
    }

    const label = station.city || station.name;

    const known = Array.from(select.options).find(
        (option) => option.value !== '' && option.value !== 'other'
            && fold(option.textContent.trim()) === fold(label)
    );

    if (known) {
        selectValue(select, known.value);

        return;
    }

    selectValue(select, 'other');

    if (otherInput) {
        otherInput.value = label;
    }
}

/**
 * Recherche des bornes autour de la position courante.
 *
 * La liste s'ouvre dans une fenetre modale : une colonne de formulaire est trop
 * etroite pour montrer nom, reseau, puissance et distance cote a cote.
 */
function setupNearbySearch() {
    const button = document.getElementById('nearby_button');
    const modal = document.getElementById('nearby_modal');
    const results = document.getElementById('nearby_results');
    const summary = document.getElementById('nearby_summary');

    if (!button || !modal || !results || !summary) {
        return;
    }

    const close = () => modal.classList.remove('is-active');

    modal.querySelectorAll('[data-nearby-close]').forEach((node) => {
        node.addEventListener('click', close);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }
    });

    const pick = (station) => {
        fillLocation(station);
        fillProvider(station.operator, { force: true });
        fillPower(station.power_kw, { force: true });

        // La position exacte distingue deux bornes d'une meme ville : c'est elle
        // qui permettra de reconnaitre celle-ci la prochaine fois.
        const latitude = document.getElementById('latitude');
        const longitude = document.getElementById('longitude');

        if (latitude && longitude) {
            latitude.value = station.lat;
            longitude.value = station.lon;
        }

        close();
        document.getElementById('formulaire')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const render = (payload) => {
        results.replaceChildren();

        if (payload.results.length === 0) {
            summary.textContent = `Aucune borne publique trouvée à moins de ${payload.radius_km} km.`;

            return;
        }

        summary.textContent = `${payload.results.length} borne(s) à moins de ${payload.radius_km} km, la plus proche d'abord.`;

        const table = document.createElement('table');
        table.className = 'table is-fullwidth is-narrow is-hoverable';

        const body = document.createElement('tbody');

        payload.results.forEach((station) => {
            const row = document.createElement('tr');

            const identity = document.createElement('td');
            const title = document.createElement('strong');
            title.textContent = station.name;
            identity.appendChild(title);

            if (station.address || station.city) {
                const address = document.createElement('p');
                address.className = 'has-text-grey is-size-7';
                address.textContent = [station.address, station.city].filter(Boolean).join(' · ');
                identity.appendChild(address);
            }

            const network = document.createElement('td');
            network.textContent = station.operator || '—';

            const power = document.createElement('td');
            power.className = 'has-text-right';
            power.textContent = `${station.power_kw} kW`;

            const distance = document.createElement('td');
            distance.className = 'has-text-right';
            distance.textContent = `${String(station.distance_km).replace('.', ',')} km`;

            const action = document.createElement('td');
            action.className = 'has-text-right';
            const add = document.createElement('button');
            add.type = 'button';
            add.className = 'button is-small is-primary is-light';
            add.textContent = 'Ajouter';
            add.addEventListener('click', () => pick(station));
            action.appendChild(add);

            [identity, network, power, distance, action].forEach((cell) => row.appendChild(cell));
            body.appendChild(row);
        });

        table.appendChild(body);

        const container = document.createElement('div');
        container.className = 'table-container';
        container.appendChild(table);
        results.appendChild(container);
    };

    button.addEventListener('click', () => {
        currentPosition(button, 'Localisation…')
            .then(({ latitude, longitude }) => {
                summary.textContent = 'Recherche en cours…';
                results.replaceChildren();
                modal.classList.add('is-active');

                return fetch(`/recharges/bornes-proches?lat=${latitude}&lon=${longitude}`, {
                    headers: { Accept: 'application/json' },
                })
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error('La recherche a échoué.');
                        }

                        return response.json();
                    })
                    .then(render)
                    .catch(() => {
                        summary.textContent = "La recherche a échoué. Réessayez dans un instant.";
                    });
            })
            .catch((error) => alert(error.message));
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('select[data-searchable]').forEach(makeSearchable);

    const quantity = document.getElementById('quantity_kwh');
    const unitCost = document.getElementById('unit_cost');
    const totalCost = document.getElementById('total_cost');
    const realCost = document.getElementById('real_cost');
    const extraCost = document.getElementById('extra_cost');

    if (quantity && unitCost && totalCost && realCost) {
        // Deux calculs en chaine : le cout reel vaut quantite x cout unitaire,
        // le total facture vaut ce cout reel plus les frais annexes. Chaque
        // champ decroche du calcul des qu'il est saisi a la main.

        // Le total rouvert est repute calcule tant qu'il vaut exactement cout
        // reel + cout additionnel. C'est ce qui permet, en modification, de
        // reporter une correction du cout reel sur le total — sans cela le
        // total garderait la valeur d'avant, et d'autant plus visiblement que
        // le cout additionnel est desormais reaffiche.
        const derivedTotal = () => {
            const total = parseFloat(totalCost.value);
            const real = parseFloat(realCost.value);

            if (isNaN(total) || isNaN(real)) {
                return false;
            }

            const extra = extraCost ? parseFloat(extraCost.value) : NaN;

            return Math.abs(total - (real + (isNaN(extra) ? 0 : extra))) < 0.005;
        };

        // Un total a zero en face d'un cout reel non nul, c'est « Gratuit » :
        // une decision, pas un champ vide. Le recalculer effacerait l'info.
        const freeCharge = () => parseFloat(totalCost.value) === 0 && parseFloat(realCost.value) > 0;

        let totalManuallyEdited = totalCost.value !== ''
            && (freeCharge() || (parseFloat(totalCost.value) !== 0 && !derivedTotal()));
        let realManuallyEdited = realCost.value !== '' && parseFloat(realCost.value) !== 0;

        const recomputeTotal = () => {
            if (totalManuallyEdited) {
                return;
            }
            const base = parseFloat(realCost.value);
            if (isNaN(base)) {
                return;
            }
            const extra = extraCost ? parseFloat(extraCost.value) : NaN;
            totalCost.value = (base + (isNaN(extra) ? 0 : extra)).toFixed(2);
        };

        const recompute = () => {
            const q = parseFloat(quantity.value);
            const u = parseFloat(unitCost.value);
            if (!isNaN(q) && !isNaN(u) && !realManuallyEdited) {
                realCost.value = (q * u).toFixed(2);
            }
            recomputeTotal();
        };

        totalCost.addEventListener('input', () => {
            totalManuallyEdited = true;
        });
        realCost.addEventListener('input', () => {
            realManuallyEdited = true;
            recomputeTotal();
        });

        // Saisir des frais annexes, c'est demander explicitement le calcul du
        // total : cela reprend la main meme apres une saisie manuelle ou un
        // clic sur Gratuit. A l'inverse, cliquer Gratuit ensuite remet zero.
        if (extraCost) {
            extraCost.addEventListener('input', () => {
                totalManuallyEdited = false;
                recomputeTotal();
            });
        }

        quantity.addEventListener('input', recompute);
        unitCost.addEventListener('input', recompute);

        // Force le calcul du cout reel et reactive son suivi automatique.
        const recomputeButton = document.getElementById('recompute_real');

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

                realCost.value = (q * u).toFixed(2);
                realManuallyEdited = false;
                recomputeTotal();

                recomputeButton.classList.add('is-success');
                setTimeout(() => recomputeButton.classList.remove('is-success'), 800);
            });
        }

        // Recharge gratuite ou non debitee : facture a zero, et on fige le
        // calcul automatique pour qu'il ne le remplace pas ensuite.
        const freeButton = document.getElementById('free_charge');

        if (freeButton) {
            freeButton.addEventListener('click', () => {
                totalCost.value = '0.00';
                totalManuallyEdited = true;

                freeButton.classList.add('is-success');
                setTimeout(() => freeButton.classList.remove('is-success'), 800);
            });
        }
    }

    setupOtherToggle('location_choice', 'location_other_wrapper', 'location_pick_wrapper');
    setupOtherToggle('provider_choice', 'provider_other_wrapper', 'provider_pick_wrapper');
    setupNativePickers();
    setupChargerSuggestions();
    setupGeolocationButton();
    setupNearbySearch();
});
