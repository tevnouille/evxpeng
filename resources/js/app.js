document.addEventListener('DOMContentLoaded', () => {
    const quantity = document.getElementById('quantity_kwh');
    const unitCost = document.getElementById('unit_cost');
    const totalCost = document.getElementById('total_cost');

    if (!quantity || !unitCost || !totalCost) {
        return;
    }

    let totalManuallyEdited = totalCost.value !== '';

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
});
