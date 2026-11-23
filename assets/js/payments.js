/* =====================================================================
   Payment: total recomputed on every ticked box, teacher summary via fetch()
   ===================================================================== */

(() => {
    // Amount due summary when a teacher is chosen
    const picker = document.getElementById('teacherPicker');
    if (picker) {
        const select = document.getElementById('teacher_id');
        const info = document.getElementById('dueInfo');
        const showSummary = async () => {
            if (!select.value) {
                info.hidden = true;
                return;
            }
            try {
                const response = await fetch(`${picker.dataset.apiDue}?teacher_id=${encodeURIComponent(select.value)}`);
                if (!response.ok) throw new Error('HTTP ' + response.status);
                const data = await response.json();
                info.hidden = false;
                info.className = 'info-box' + (data.balance > 0 ? '' : ' warning');
                info.textContent = `Due: ${formatMoney(data.due)} · paid: ${formatMoney(data.paid)} · balance: ${formatMoney(data.balance)}`;
            } catch (error) {
                info.hidden = true;
            }
        };
        select.addEventListener('change', showSummary);
        showSummary();
    }

    // Payment total
    const form = document.getElementById('paymentForm');
    if (!form) return;
    const checks = form.querySelectorAll('.pay-check');
    const checkAll = document.getElementById('checkAllPay');
    const total = document.getElementById('paymentTotal');

    function updateTotal() {
        let sum = 0;
        let count = 0;
        checks.forEach((box) => {
            if (box.checked) {
                sum += parseFloat(box.dataset.amount);
                count++;
            }
        });
        total.textContent = formatMoney(sum);
        if (checkAll) checkAll.checked = count === checks.length;
        return { sum, count };
    }

    checks.forEach((box) => box.addEventListener('change', updateTotal));
    if (checkAll) {
        checkAll.addEventListener('change', () => {
            checks.forEach((box) => { box.checked = checkAll.checked; });
            updateTotal();
        });
    }

    form.addEventListener('submit', () => {
        const { sum, count } = updateTotal();
        // The confirmation window itself is shown by main.js
        if (count > 0) {
            form.dataset.confirm = `Record a payment of ${formatMoney(sum)} for ${count} session(s)?`;
        } else {
            delete form.dataset.confirm;
        }
    });

    updateTotal();
})();
