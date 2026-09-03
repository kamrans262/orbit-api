import {adminApi, firstSuccessfulEndpoint, listRows, OrbitAdminApiError, unwrap} from './api-client.js';
import {clearAdminSession, readAdminSession, updateAdminIdentity} from './auth-session.js';
import {debounce, errorText, escapeHtml} from './ui.js';

const THEME_KEY = 'orbit.admin.theme.v1';
const RETURN_TO_KEY = 'orbit.admin.console.return_to.v1';
const LOGIN_PATH = '/admin/login';
const THEMES = ['system', 'light', 'dark'];

let validatedAdmin = null;

function initials(value) {
    const parts = String(value ?? 'Administrator').trim().split(/\s+/).filter(Boolean);
    return (parts.slice(0, 2).map((part) => part[0]).join('') || 'A').toUpperCase();
}

function adminIdentity(payload) {
    const source = unwrap(payload);
    const roles = source.roles ?? source.role_names ?? source.role ?? [];
    const role = Array.isArray(roles)
        ? (roles[0]?.name ?? roles[0] ?? 'Administrator')
        : (roles?.name ?? roles ?? 'Administrator');

    return {
        id: source.id ?? source.admin_id ?? null,
        name: source.name ?? source.display_name ?? source.email ?? 'Administrator',
        email: source.email ?? '',
        role: String(role).replaceAll('-', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()),
        permissions: source.permissions ?? [],
    };
}

function renderProfile(admin = null) {
    const name = admin?.name ?? 'Administrator';
    const role = admin?.role ?? 'Verifying session';
    const email = admin?.email ?? 'Secure session required';

    document.querySelectorAll('[data-admin-name]').forEach((element) => { element.textContent = name; });
    document.querySelectorAll('[data-admin-role]').forEach((element) => { element.textContent = role; });
    document.querySelectorAll('[data-admin-avatar]').forEach((element) => { element.textContent = initials(name); });
    document.querySelectorAll('[data-menu-admin-name]').forEach((element) => { element.textContent = name; });
    document.querySelectorAll('[data-menu-admin-email]').forEach((element) => { element.textContent = email; });
}

function safeAdminPath(value) {
    if (typeof value !== 'string' || !value.startsWith('/admin')) return null;
    if (value.startsWith('/admin/login') || value.startsWith('/admin/mfa')) return null;
    return value;
}

function currentAdminPath() {
    return safeAdminPath(`${window.location.pathname}${window.location.search}${window.location.hash}`) ?? '/admin';
}

function rememberReturnTarget() {
    try {
        window.sessionStorage.setItem(RETURN_TO_KEY, currentAdminPath());
    } catch (_) {
        // A blocked storage API should not prevent secure redirection to login.
    }
}

function consumeReturnTarget() {
    try {
        const target = safeAdminPath(window.sessionStorage.getItem(RETURN_TO_KEY));
        if (target) window.sessionStorage.removeItem(RETURN_TO_KEY);
        return target;
    } catch (_) {
        return null;
    }
}

function redirectToFoundationLogin({clearSession = false} = {}) {
    if (clearSession) clearAdminSession();
    rememberReturnTarget();
    window.location.replace(LOGIN_PATH);
}

function revealConsole(admin) {
    validatedAdmin = admin;
    const gate = document.querySelector('[data-auth-gate]');
    const shell = document.querySelector('[data-orbit-shell]');
    renderProfile(admin);
    if (gate) gate.hidden = true;
    if (shell) shell.hidden = false;
}

function showAuthGateError(error) {
    const gate = document.querySelector('[data-auth-gate]');
    if (!gate) return;

    gate.classList.add('is-error');
    const title = gate.querySelector('[data-auth-gate-title]');
    const message = gate.querySelector('[data-auth-gate-message]');
    const actions = gate.querySelector('[data-auth-gate-actions]');
    const spinner = gate.querySelector('.orbit-auth-gate__spinner');

    if (title) title.textContent = 'Administrator session could not be verified';
    if (message) message.textContent = errorText(error);
    if (actions) actions.hidden = false;
    if (spinner) spinner.hidden = true;
}

async function validateCurrentSession() {
    try {
        const payload = await adminApi('/api/admin/v1/auth/me');
        const admin = adminIdentity(payload);
        updateAdminIdentity(admin);
        revealConsole(admin);
        return admin;
    } catch (error) {
        if (error instanceof OrbitAdminApiError && error.status === 401) {
            redirectToFoundationLogin({clearSession: true});
            return null;
        }
        throw error;
    }
}

async function signOutAndReturnToLogin() {
    try {
        await adminApi('/api/admin/v1/auth/logout', {method: 'POST'});
    } catch (_) {
        // The local credential is still cleared if the server has already expired it.
    } finally {
        clearAdminSession();
        try { window.sessionStorage.removeItem(RETURN_TO_KEY); } catch (_) { /* no-op */ }
        window.location.assign(LOGIN_PATH);
    }
}

async function switchAdministrator() {
    try {
        await adminApi('/api/admin/v1/auth/logout', {method: 'POST'});
    } catch (_) {
        // Switching remains possible when the previous server session is already gone.
    } finally {
        clearAdminSession();
        rememberReturnTarget();
        window.location.assign(LOGIN_PATH);
    }
}

function initAuthenticationBridge() {
    renderProfile(null);

    window.addEventListener('orbit:admin-auth-required', () => {
        redirectToFoundationLogin({clearSession: true});
    });

    document.querySelector('[data-auth-gate-retry]')?.addEventListener('click', () => {
        window.location.reload();
    });

    document.querySelector('[data-profile-switch]')?.addEventListener('click', () => {
        document.querySelector('[data-profile-menu]')?.setAttribute('hidden', '');
        switchAdministrator();
    });

    document.querySelector('[data-profile-sign-out]')?.addEventListener('click', () => {
        document.querySelector('[data-profile-menu]')?.setAttribute('hidden', '');
        signOutAndReturnToLogin();
    });

    validateCurrentSession().then((admin) => {
        if (!admin) return;

        const returnTarget = consumeReturnTarget();
        if (returnTarget && returnTarget !== currentAdminPath()) {
            window.location.replace(returnTarget);
            return;
        }

        window.dispatchEvent(new CustomEvent('orbit:admin-ready', {detail: admin}));
    }).catch((error) => {
        console.error('Orbit administrator session validation failed.', error);
        showAuthGateError(error);
    });
}

function initTheme() {
    let selected = window.localStorage.getItem(THEME_KEY);
    if (!THEMES.includes(selected)) selected = 'system';

    const apply = () => {
        document.documentElement.dataset.orbitTheme = selected;
        const label = selected[0].toUpperCase() + selected.slice(1);
        document.querySelectorAll('[data-theme-label]').forEach((element) => { element.textContent = `Theme: ${label}`; });
    };

    apply();
    document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => {
        selected = THEMES[(THEMES.indexOf(selected) + 1) % THEMES.length];
        window.localStorage.setItem(THEME_KEY, selected);
        apply();
    });
}

function initSidebar() {
    const shell = document.querySelector('[data-orbit-shell]');
    const scrim = document.querySelector('[data-sidebar-scrim]');
    const open = () => { shell?.classList.add('is-sidebar-open'); if (scrim) scrim.hidden = false; };
    const close = () => { shell?.classList.remove('is-sidebar-open'); if (scrim) scrim.hidden = true; };

    document.querySelector('[data-sidebar-open]')?.addEventListener('click', open);
    document.querySelector('[data-sidebar-close]')?.addEventListener('click', close);
    scrim?.addEventListener('click', close);
    window.matchMedia('(min-width: 1024px)').addEventListener('change', (event) => { if (event.matches) close(); });
}

function initProfileMenu() {
    const button = document.querySelector('[data-profile-toggle]');
    const menu = document.querySelector('[data-profile-menu]');
    if (!button || !menu) return;

    const close = () => { menu.hidden = true; button.setAttribute('aria-expanded', 'false'); };
    button.addEventListener('click', (event) => {
        event.stopPropagation();
        menu.hidden = !menu.hidden;
        button.setAttribute('aria-expanded', menu.hidden ? 'false' : 'true');
    });
    document.addEventListener('click', (event) => { if (!menu.contains(event.target) && event.target !== button) close(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
}

function flattenSearchRows(payload) {
    const direct = listRows(payload);
    if (direct.length) return direct;

    const data = payload?.data ?? payload ?? {};
    const groups = data.results ?? data.groups ?? data;
    if (!groups || typeof groups !== 'object' || Array.isArray(groups)) return [];

    const rows = [];
    for (const [group, items] of Object.entries(groups)) {
        if (!Array.isArray(items)) continue;
        for (const item of items) rows.push({...item, _group: group});
    }
    return rows;
}

function searchHref(item) {
    const type = String(item.type ?? item.resource_type ?? item.kind ?? item._group ?? '').toLowerCase();
    const id = item.id ?? item.resource_id ?? item.target_id ?? null;
    const explicit = item.admin_url ?? item.url ?? item.href ?? null;
    if (explicit && String(explicit).startsWith('/admin')) return explicit;
    if (!id) return null;
    if (type.includes('user')) return `/admin/operations/users/${encodeURIComponent(id)}`;
    if (type.includes('circle')) return `/admin/operations/circles/${encodeURIComponent(id)}`;
    return null;
}

function searchRowHtml(item, index) {
    const title = item.title ?? item.name ?? item.display_name ?? item.label ?? item.email ?? item.id ?? 'Result';
    const subtitle = item.subtitle ?? item.description ?? item.masked_email ?? item.email_masked ?? item.status ?? item.id ?? '';
    const type = item.type ?? item.resource_type ?? item.kind ?? item._group ?? 'Record';
    const href = searchHref(item);
    const tag = href ? 'a' : 'div';
    const hrefAttr = href ? ` href="${escapeHtml(href)}"` : '';
    const disabled = href ? '' : ' aria-disabled="true"';

    return `<${tag}${hrefAttr}${disabled} class="orbit-search-result" data-search-result data-search-index="${index}">
        <span class="orbit-search-result__icon" aria-hidden="true">${escapeHtml(String(type).slice(0, 1).toUpperCase())}</span>
        <span class="orbit-search-result__text"><strong>${escapeHtml(title)}</strong><small>${escapeHtml(subtitle)}</small></span>
        <span class="orbit-search-result__type">${escapeHtml(type)}</span>
        ${href ? '<span class="orbit-search-result__arrow" aria-hidden="true">→</span>' : ''}
    </${tag}>`;
}

function initGlobalSearch() {
    const dialog = document.querySelector('[data-global-search-dialog]');
    const input = document.querySelector('[data-global-search-input]');
    const body = document.querySelector('[data-global-search-body]');
    if (!dialog || !input || !body) return;

    let controller = null;
    let activeIndex = -1;

    const open = () => {
        if (!validatedAdmin && !readAdminSession()) {
            redirectToFoundationLogin();
            return;
        }
        if (!dialog.open) dialog.showModal();
        activeIndex = -1;
        input.focus();
        input.select();
    };

    const close = () => {
        controller?.abort();
        if (dialog.open) dialog.close();
    };

    const markActive = (index) => {
        const results = [...body.querySelectorAll('[data-search-result]')];
        if (!results.length) return;
        activeIndex = Math.max(0, Math.min(index, results.length - 1));
        results.forEach((result, resultIndex) => result.classList.toggle('is-active', resultIndex === activeIndex));
        results[activeIndex].scrollIntoView({block: 'nearest'});
    };

    const perform = debounce(async () => {
        const query = input.value.trim();
        controller?.abort();
        activeIndex = -1;

        if (query.length < 2) {
            body.innerHTML = '<div class="orbit-search-hint"><span class="orbit-search-hint__icon">⌘</span><div><strong>Search Orbit</strong><p>Enter at least 2 characters. Results are permission-filtered by the server.</p></div></div>';
            return;
        }

        controller = new AbortController();
        body.innerHTML = '<div class="orbit-search-loading" aria-label="Searching"><span></span><span></span><span></span></div>';

        try {
            const encoded = encodeURIComponent(query);
            const {payload} = await firstSuccessfulEndpoint([
                `/api/admin/v1/search?q=${encoded}`,
                `/api/admin/v1/global-search?q=${encoded}`,
                `/api/admin/v1/search?query=${encoded}`,
            ], {signal: controller.signal});

            const rows = flattenSearchRows(payload).slice(0, 12);
            if (!rows.length) {
                body.innerHTML = `<div class="orbit-search-hint"><span class="orbit-search-hint__icon">⌕</span><div><strong>No results</strong><p>No permission-visible records matched “${escapeHtml(query)}”.</p></div></div>`;
                return;
            }

            body.innerHTML = `<div class="orbit-search-results">${rows.map(searchRowHtml).join('')}</div>`;
        } catch (error) {
            if (error.name === 'AbortError') return;
            body.innerHTML = `<div class="orbit-search-hint orbit-search-hint--error"><span class="orbit-search-hint__icon">!</span><div><strong>Search unavailable</strong><p>${escapeHtml(errorText(error))}</p></div></div>`;
        }
    }, 260);

    document.querySelector('[data-global-search-open]')?.addEventListener('click', open);
    input.addEventListener('input', perform);
    dialog.addEventListener('click', (event) => { if (event.target === dialog) close(); });
    dialog.addEventListener('cancel', (event) => { event.preventDefault(); close(); });
    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            open();
            return;
        }
        if (!dialog.open) return;
        if (event.key === 'ArrowDown') { event.preventDefault(); markActive(activeIndex + 1); }
        if (event.key === 'ArrowUp') { event.preventDefault(); markActive(activeIndex <= 0 ? 0 : activeIndex - 1); }
        if (event.key === 'Enter' && activeIndex >= 0) {
            const active = body.querySelector(`[data-search-index="${activeIndex}"]`);
            if (active?.tagName === 'A') {
                event.preventDefault();
                window.location.assign(active.href);
            }
        }
    });
}

export function initShell() {
    initTheme();
    initSidebar();
    initProfileMenu();
    initAuthenticationBridge();
    initGlobalSearch();
}
