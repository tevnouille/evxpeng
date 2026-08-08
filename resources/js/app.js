function makeSearchable(select) {
    if (!select || select.dataset.searchableApplied === 'true') {
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
    }

    setupOtherToggle('location_choice', 'location_other_wrapper');
    setupOtherToggle('provider_choice', 'provider_other_wrapper');
});
