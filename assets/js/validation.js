/* =====================================================================
   Form validation before submit (the server always validates again in PHP)
   Applies to: <form class="needs-validation" novalidate>
   ===================================================================== */

(() => {
    function todayIso() {
        const d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    // Dates that cannot be in the future: <input type="date" data-max-today>
    document.querySelectorAll('input[type="date"][data-max-today]').forEach((input) => {
        input.max = todayIso();
    });

    function runCustomChecks(form) {
        // Two identical fields: <input data-match="#password">
        form.querySelectorAll('[data-match]').forEach((input) => {
            const other = form.querySelector(input.dataset.match);
            input.setCustomValidity(other && input.value !== other.value ? 'The two values do not match.' : '');
        });

        // Value strictly after another one: <input type="time" data-after="#start_time">
        form.querySelectorAll('[data-after]').forEach((input) => {
            const other = form.querySelector(input.dataset.after);
            const invalid = other && other.value && input.value && input.value <= other.value;
            input.setCustomValidity(invalid ? 'The value must be after the previous field.' : '');
        });

        // At least one ticked box in a group: <div data-require-one>
        form.querySelectorAll('[data-require-one]').forEach((group) => {
            const boxes = group.querySelectorAll('input[type="checkbox"][name]'); // the "tick all" box has no name
            const any = Array.from(boxes).some((box) => box.checked);
            if (boxes.length) boxes[0].setCustomValidity(any ? '' : 'Tick at least one row.');
            const message = document.querySelector(group.dataset.requireOne);
            if (message) message.hidden = any;
        });
    }

    document.querySelectorAll('form.needs-validation').forEach((form) => {
        form.addEventListener('submit', (event) => {
            runCustomChecks(form);
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });

        // Messages update while typing, once the form has been submitted
        form.addEventListener('input', () => {
            if (form.classList.contains('was-validated')) runCustomChecks(form);
        });
        form.addEventListener('change', () => {
            if (form.classList.contains('was-validated')) runCustomChecks(form);
        });
    });
})();
