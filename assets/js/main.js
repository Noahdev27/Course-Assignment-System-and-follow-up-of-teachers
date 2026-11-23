/* =====================================================================
   Behaviour shared by every page (plain JavaScript)
   ===================================================================== */

document.addEventListener('DOMContentLoaded', () => {
    // Asks for confirmation before a sensitive action, in a friendly window:
    // <form data-confirm="Delete this course?">
    // Delegated on the document, so a script can set data-confirm just before the submit.
    const modalElement = document.getElementById('confirmModal');
    const modal = modalElement && window.bootstrap ? new bootstrap.Modal(modalElement) : null;
    let pending = null;

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || event.defaultPrevented) return;
        if (form.dataset.confirmed === '1') {
            delete form.dataset.confirmed;
            return;
        }
        event.preventDefault();
        if (!modal) {
            if (window.confirm(form.dataset.confirm)) submitConfirmed(form, event.submitter);
            return;
        }
        pending = { form, submitter: event.submitter };
        document.getElementById('confirmModalText').textContent = form.dataset.confirm;
        modal.show();
    });

    const okButton = document.getElementById('confirmModalOk');
    if (okButton) {
        okButton.addEventListener('click', () => {
            if (!pending) return;
            const { form, submitter } = pending;
            pending = null;
            modal.hide();
            submitConfirmed(form, submitter);
        });
    }

    function submitConfirmed(form, submitter) {
        form.dataset.confirmed = '1';
        if (submitter && form.requestSubmit) form.requestSubmit(submitter);
        else if (form.requestSubmit) form.requestSubmit();
        else form.submit();
    }

    // Show / hide a password: <button data-toggle-password="#password">
    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
        const input = document.querySelector(button.dataset.togglePassword);
        if (!input) return;
        button.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
            button.innerHTML = show
                ? '<i class="bi bi-eye-slash" aria-hidden="true"></i> Hide'
                : '<i class="bi bi-eye" aria-hidden="true"></i> Show';
        });
    });

    // Demo accounts on the login page: one click fills the form
    document.querySelectorAll('[data-fill-login]').forEach((button) => {
        button.addEventListener('click', () => {
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            if (!email || !password) return;
            email.value = button.dataset.fillLogin;
            password.value = button.dataset.password || '';
            document.querySelectorAll('[data-fill-login]').forEach((b) => b.classList.remove('active'));
            button.classList.add('active');
            document.getElementById('loginButton')?.focus();
        });
    });

    // Little celebration when something was saved (skipped if the user prefers less motion)
    const success = document.querySelector('[data-celebrate]');
    if (success && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        celebrate();
    }

    // Table filters without reloading:
    // <input data-filter-for="#coursesTable" data-field="text">
    // <select data-filter-for="#coursesTable" data-field="dept">  (compared with <tr data-dept="...">)
    // <input type="checkbox" data-filter-for="#coursesTable" data-field="incomplete"> (keeps <tr data-incomplete="1">)
    const tables = new Set();
    document.querySelectorAll('[data-filter-for]').forEach((control) => {
        const table = document.querySelector(control.dataset.filterFor);
        if (!table) return;
        tables.add(table);
        const eventName = control.tagName === 'SELECT' || control.type === 'checkbox' ? 'change' : 'input';
        control.addEventListener(eventName, () => applyTableFilters(table));
    });
    tables.forEach(applyTableFilters);

    // Print button: <button data-print>
    document.querySelectorAll('[data-print]').forEach((button) => {
        button.addEventListener('click', () => window.print());
    });

    // Block shown depending on a field value: <div data-show-if="#role:teacher,head">
    document.querySelectorAll('[data-show-if]').forEach((block) => {
        const [selector, values] = block.dataset.showIf.split(':');
        const field = document.querySelector(selector);
        if (!field) return;
        const allowed = values.split(',');
        const update = () => {
            const visible = field.type === 'checkbox' ? field.checked : allowed.includes(field.value);
            block.hidden = !visible;
            // A hidden field is not required by browser validation
            block.querySelectorAll('[data-required-when-visible]').forEach((input) => {
                input.required = visible;
            });
        };
        field.addEventListener('change', update);
        update();
    });
});

function applyTableFilters(table) {
    const controls = document.querySelectorAll(`[data-filter-for="#${table.id}"]`);
    const rows = table.querySelectorAll('tbody tr[data-row]');
    let visible = 0;

    rows.forEach((row) => {
        let show = true;
        controls.forEach((control) => {
            const field = control.dataset.field;
            if (control.type === 'checkbox') {
                if (control.checked && row.dataset[field] !== '1') show = false;
                return;
            }
            const value = control.value.trim().toLowerCase();
            if (value === '') return;
            if (field === 'text') {
                if (!row.textContent.toLowerCase().includes(value)) show = false;
            } else if ((row.dataset[field] || '').toLowerCase() !== value) {
                show = false;
            }
        });
        row.hidden = !show;
        if (show) visible++;
    });

    const counter = document.querySelector(`[data-count-for="#${table.id}"]`);
    if (counter) counter.textContent = visible;

    const empty = table.querySelector('tr[data-empty]');
    if (empty) empty.hidden = visible > 0;
}

/** Formats a number like PHP does: 125,000 FCFA */
function formatMoney(value) {
    return Math.round(value).toLocaleString('en-US') + ' FCFA';
}

function formatHours(value) {
    const rounded = Math.round(value * 10) / 10;
    return rounded.toLocaleString('en-US') + ' h';
}

/** A short burst of confetti from the top of the page (pure CSS animation, removed after 2 s). */
function celebrate() {
    const colors = ['#0f766e', '#f59e0b', '#2563eb', '#db2777', '#16a34a'];
    const layer = document.createElement('div');
    layer.className = 'confetti-layer';
    layer.setAttribute('aria-hidden', 'true');
    for (let i = 0; i < 40; i++) {
        const piece = document.createElement('span');
        piece.style.left = Math.random() * 100 + 'vw';
        piece.style.background = colors[i % colors.length];
        piece.style.animationDelay = (Math.random() * 0.4) + 's';
        piece.style.transform = `rotate(${Math.random() * 360}deg)`;
        layer.appendChild(piece);
    }
    document.body.appendChild(layer);
    setTimeout(() => layer.remove(), 2200);
}
