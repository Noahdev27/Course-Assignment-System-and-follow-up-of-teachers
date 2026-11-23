/* =====================================================================
   Sessions: live duration, multiple selection and rejection dialog
   ===================================================================== */

(() => {
    // Duration shown live on the declaration form
    const start = document.getElementById('start_time');
    const end = document.getElementById('end_time');
    const durationOutput = document.getElementById('computedDuration');
    const assignment = document.getElementById('assignment_id');
    const remainingInfo = document.getElementById('remainingInfo');

    function minutes(value) {
        const [h, m] = value.split(':').map(Number);
        return h * 60 + m;
    }

    function updateDuration() {
        if (!start || !end || !durationOutput) return;
        if (!start.value || !end.value) {
            durationOutput.textContent = '—';
            return;
        }
        const diff = minutes(end.value) - minutes(start.value);
        durationOutput.textContent = diff > 0 ? formatHours(diff / 60) : 'The end time must be after the start time';
        durationOutput.classList.toggle('text-danger', diff <= 0);
    }

    function updateRemaining() {
        if (!assignment || !remainingInfo) return;
        const option = assignment.selectedOptions[0];
        if (!option || !option.value) {
            remainingInfo.hidden = true;
            return;
        }
        remainingInfo.hidden = false;
        remainingInfo.textContent = `You still have ${formatHours(parseFloat(option.dataset.remaining))} to teach on this course `
            + `(${formatHours(parseFloat(option.dataset.hours))} given to you, ${formatHours(parseFloat(option.dataset.used))} already declared).`;
    }

    if (start && end) {
        start.addEventListener('input', updateDuration);
        end.addEventListener('input', updateDuration);
        updateDuration();
    }
    if (assignment) {
        assignment.addEventListener('change', updateRemaining);
        updateRemaining();
    }

    // Bulk approval
    const checkAll = document.getElementById('checkAll');
    const bulkButton = document.getElementById('bulkValidate');
    const checks = document.querySelectorAll('.session-check');

    function updateBulk() {
        const count = Array.from(checks).filter((box) => box.checked).length;
        if (bulkButton) {
            bulkButton.disabled = count === 0;
            bulkButton.textContent = count > 0 ? `Approve selection (${count})` : 'Approve selection';
        }
        if (checkAll) checkAll.checked = count > 0 && count === checks.length;
    }

    if (checkAll) {
        checkAll.addEventListener('change', () => {
            checks.forEach((box) => { box.checked = checkAll.checked; });
            updateBulk();
        });
    }
    checks.forEach((box) => box.addEventListener('change', updateBulk));

    // Rejection dialog: receives the session of the clicked button
    const modal = document.getElementById('rejectModal');
    if (modal) {
        modal.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            document.getElementById('rejectSessionId').value = button.dataset.sessionId;
            document.getElementById('rejectSessionLabel').textContent = button.dataset.sessionLabel;
            const form = modal.querySelector('form');
            form.classList.remove('was-validated');
            form.querySelector('textarea').value = '';
        });
        modal.addEventListener('shown.bs.modal', () => modal.querySelector('textarea').focus());
    }
})();
