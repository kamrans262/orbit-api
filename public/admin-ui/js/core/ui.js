const icon = (type) => {
    if (type === 'success') return '<svg viewBox="0 0 24 24" width="17" height="17" fill="none"><path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    if (type === 'danger') return '<svg viewBox="0 0 24 24" width="17" height="17" fill="none"><path d="M12 3 2.8 19h18.4L12 3Z" stroke="currentColor" stroke-width="1.8"/><path d="M12 9v4m0 3h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
    if (type === 'warning') return '<svg viewBox="0 0 24 24" width="17" height="17" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v6m0 4h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
    return '<svg viewBox="0 0 24 24" width="17" height="17" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 11v6m0-10h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
};

export function toast(title, message = '', type = 'info', duration = 4200) {
    const region = document.getElementById('toast-region');
    if (!region) return;
    const node = document.createElement('div');
    node.className = `toast toast--${type}`;
    node.innerHTML = `<span class="toast__icon">${icon(type)}</span><span class="toast__copy"><strong></strong><span></span></span><button class="toast__close" type="button" aria-label="Dismiss">×</button>`;
    node.querySelector('strong').textContent = title;
    node.querySelector('.toast__copy span').textContent = message;
    const close = () => { node.style.opacity = '0'; node.style.transform = 'translateX(12px)'; setTimeout(() => node.remove(), 180); };
    node.querySelector('.toast__close').addEventListener('click', close);
    region.append(node);
    if (duration > 0) setTimeout(close, duration);
}

export function setButtonLoading(button, loading) {
    if (!button) return;
    const content = button.querySelector('.ui-button__content');
    if (loading) {
        button.dataset.originalHtml = content?.innerHTML || '';
        button.classList.add('is-loading');
        button.disabled = true;
        const label = button.dataset.loadingText;
        if (content && label) content.textContent = label;
    } else {
        button.classList.remove('is-loading');
        button.disabled = false;
        if (content && button.dataset.originalHtml) content.innerHTML = button.dataset.originalHtml;
    }
}

export function clearFieldErrors(form) {
    form?.querySelectorAll('.field-error').forEach((el) => { el.textContent = ''; });
}

export function applyFieldErrors(form, errors) {
    if (!errors || !form) return;
    Object.entries(errors).forEach(([field, messages]) => {
        const target = form.querySelector(`[data-error-for="${CSS.escape(field)}"]`);
        if (target) target.textContent = Array.isArray(messages) ? messages[0] : String(messages);
    });
}

export function compactNumber(value) {
    const number = Number(value || 0);
    if (Math.abs(number) < 1000) return new Intl.NumberFormat().format(number);
    return new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 }).format(number);
}

export function integer(value) {
    return new Intl.NumberFormat().format(Number(value || 0));
}

export function minorUnits(value) {
    return new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(Number(value || 0));
}

export function dateTime(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}

export function relativeTime(value) {
    if (!value) return 'just now';
    const date = new Date(value);
    const diff = Math.round((date.getTime() - Date.now()) / 1000);
    const abs = Math.abs(diff);
    const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
    if (abs < 60) return rtf.format(diff, 'second');
    if (abs < 3600) return rtf.format(Math.round(diff / 60), 'minute');
    if (abs < 86400) return rtf.format(Math.round(diff / 3600), 'hour');
    return rtf.format(Math.round(diff / 86400), 'day');
}

export function path(object, dotted, fallback = 0) {
    return dotted.split('.').reduce((value, key) => value?.[key], object) ?? fallback;
}

export function initials(value = 'A') {
    return String(value).trim().split(/\s+/).slice(0, 2).map((part) => part[0] || '').join('').toUpperCase() || 'A';
}
