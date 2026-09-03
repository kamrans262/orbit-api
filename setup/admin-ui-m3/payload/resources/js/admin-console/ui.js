export const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
}[char]));

export const valueOrDash = (value) => value === null || value === undefined || value === '' ? '—' : value;

export function maskIdentifier(value, visibleStart = 6, visibleEnd = 4) {
    const text = String(value ?? '');
    if (!text) return '—';
    if (text.length <= visibleStart + visibleEnd + 3) return text;
    return `${text.slice(0, visibleStart)}…${text.slice(-visibleEnd)}`;
}

export function fmtDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? String(value)
        : new Intl.DateTimeFormat(undefined, {dateStyle: 'medium', timeStyle: 'short'}).format(date);
}

export function fmtNumber(value) {
    if (value === null || value === undefined || value === '') return '—';
    const number = Number(value);
    return Number.isFinite(number) ? new Intl.NumberFormat().format(number) : String(value);
}

export function badge(status) {
    const raw = valueOrDash(status);
    const cls = String(raw).toLowerCase().replace(/[^a-z0-9]+/g, '-');
    return `<span class="orbit-badge orbit-badge--${escapeHtml(cls)}">${escapeHtml(raw)}</span>`;
}

export function toast(message, tone = 'info') {
    const region = document.querySelector('[data-orbit-toasts]');
    if (!region) return;

    const element = document.createElement('div');
    element.className = `orbit-toast orbit-toast--${tone}`;
    element.setAttribute('role', tone === 'error' ? 'alert' : 'status');
    element.textContent = message;
    region.append(element);

    requestAnimationFrame(() => element.classList.add('is-visible'));
    window.setTimeout(() => {
        element.classList.remove('is-visible');
        window.setTimeout(() => element.remove(), 220);
    }, 4200);
}

export function errorText(error) {
    return `${error.message}${error.requestId ? ` Request ID: ${error.requestId}` : ''}`;
}

export function kv(items) {
    return items.map(([label, value]) => `<div><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(valueOrDash(value))}</dd></div>`).join('');
}

export function debounce(fn, delay = 280) {
    let timer = null;
    return (...args) => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => fn(...args), delay);
    };
}

export function setBusy(button, busy) {
    if (!button) return;
    button.disabled = busy;
    button.classList.toggle('is-busy', busy);
    button.setAttribute('aria-busy', busy ? 'true' : 'false');
}

export function askForm({title, message, fields = [], confirm = 'Continue', danger = false, eyebrow = 'Confirmation'}) {
    return new Promise((resolve) => {
        const dialog = document.createElement('dialog');
        dialog.className = 'orbit-dialog';

        const controls = fields.map((field) => {
            const name = escapeHtml(field.name);
            const label = escapeHtml(field.label ?? field.name);
            const required = field.required === false ? '' : ' required';
            const maxLength = Number.isFinite(Number(field.maxLength)) ? ` maxlength="${Number(field.maxLength)}"` : '';
            const autocomplete = field.autocomplete ? ` autocomplete="${escapeHtml(field.autocomplete)}"` : '';
            const value = field.value === null || field.value === undefined ? '' : String(field.value);

            if (field.type === 'select') {
                const options = (field.options ?? []).map((option) => {
                    const optionValue = typeof option === 'object' ? option.value : option;
                    const optionLabel = typeof option === 'object' ? option.label : option;
                    const selected = String(optionValue ?? '') === value ? ' selected' : '';
                    return `<option value="${escapeHtml(optionValue ?? '')}"${selected}>${escapeHtml(optionLabel ?? '')}</option>`;
                }).join('');
                return `<label class="orbit-field"><span>${label}</span><select name="${name}"${required}>${options}</select></label>`;
            }

            if (field.type === 'textarea') {
                return `<label class="orbit-field"><span>${label}</span><textarea name="${name}" rows="${Number(field.rows ?? 3)}"${maxLength}${required}>${escapeHtml(value)}</textarea></label>`;
            }

            const type = ['password', 'email', 'number', 'search', 'text'].includes(field.type) ? field.type : 'text';
            const inputMode = field.inputMode ? ` inputmode="${escapeHtml(field.inputMode)}"` : '';
            return `<label class="orbit-field"><span>${label}</span><input name="${name}" type="${type}" value="${escapeHtml(value)}"${maxLength}${autocomplete}${inputMode}${required}></label>`;
        }).join('');

        dialog.innerHTML = `
            <form method="dialog">
                <div class="orbit-dialog__head">
                    <div><p class="orbit-eyebrow">${escapeHtml(eyebrow)}</p><h2>${escapeHtml(title)}</h2></div>
                    <button type="button" data-dialog-cancel class="orbit-icon-button" aria-label="Close">×</button>
                </div>
                <p>${escapeHtml(message)}</p>
                <div class="orbit-dialog__fields">${controls}</div>
                <div class="orbit-dialog__actions">
                    <button type="button" data-dialog-cancel class="orbit-button orbit-button--quiet">Cancel</button>
                    <button type="submit" value="confirm" class="orbit-button ${danger ? 'orbit-button--danger' : 'orbit-button--primary'}">${escapeHtml(confirm)}</button>
                </div>
            </form>`;

        document.body.append(dialog);
        dialog.showModal();
        dialog.querySelectorAll('[data-dialog-cancel]').forEach((button) => {
            button.addEventListener('click', () => dialog.close('cancel'));
        });
        dialog.querySelector('input, textarea, select')?.focus();

        dialog.addEventListener('close', () => {
            const accepted = dialog.returnValue === 'confirm';
            const form = dialog.querySelector('form');
            const values = {};

            if (accepted) {
                const data = new FormData(form);
                fields.forEach((field) => {
                    const raw = data.get(field.name);
                    values[field.name] = typeof raw === 'string' ? raw.trim() : raw;
                });
            }

            dialog.remove();
            resolve(accepted ? values : null);
        }, {once: true});
    });
}

export function askText({title, message, label = 'Reason', confirm = 'Continue', danger = false, required = true}) {
    return new Promise((resolve) => {
        const dialog = document.createElement('dialog');
        dialog.className = 'orbit-dialog';
        dialog.innerHTML = `
            <form method="dialog">
                <div class="orbit-dialog__head">
                    <div><p class="orbit-eyebrow">Confirmation</p><h2>${escapeHtml(title)}</h2></div>
                    <button type="button" data-dialog-cancel class="orbit-icon-button" aria-label="Close">×</button>
                </div>
                <p>${escapeHtml(message)}</p>
                <label class="orbit-field"><span>${escapeHtml(label)}</span><textarea name="value" rows="3" maxlength="500" ${required ? 'required' : ''}></textarea></label>
                <div class="orbit-dialog__actions">
                    <button type="button" data-dialog-cancel class="orbit-button orbit-button--quiet">Cancel</button>
                    <button type="submit" value="confirm" class="orbit-button ${danger ? 'orbit-button--danger' : 'orbit-button--primary'}">${escapeHtml(confirm)}</button>
                </div>
            </form>`;

        document.body.append(dialog);
        dialog.showModal();
        dialog.querySelectorAll('[data-dialog-cancel]').forEach((button) => {
            button.addEventListener('click', () => dialog.close('cancel'));
        });
        const field = dialog.querySelector('textarea');
        field.focus();

        dialog.addEventListener('close', () => {
            const accepted = dialog.returnValue === 'confirm';
            const value = field.value.trim();
            dialog.remove();
            resolve(accepted && (!required || value) ? value : null);
        }, {once: true});
    });
}

export function state(root, name, message = '') {
    root.querySelector('[data-loading]')?.toggleAttribute('hidden', name !== 'loading');
    root.querySelector('[data-error]')?.toggleAttribute('hidden', name !== 'error');
    root.querySelector('[data-empty]')?.toggleAttribute('hidden', name !== 'empty');
    root.querySelector('[data-table-wrap]')?.toggleAttribute('hidden', name !== 'ready');
    if (message) {
        const target = root.querySelector('[data-error-message]');
        if (target) target.textContent = message;
    }
}
