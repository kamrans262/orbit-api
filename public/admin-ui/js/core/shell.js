import { api } from './api.js';
import { logout } from './auth.js';
import { preferences } from './storage.js';
import { initTheme } from './theme.js';
import { initials, toast } from './ui.js';

const implementedRoutes = new Set(['/admin', '/admin/dashboard']);
let commandTimer = null;
let adminContext = null;

export function initShell(admin) {
    adminContext = admin;
    initTheme();
    initSidebar();
    initProfile(admin);
    initPermissions(admin.permissions || []);
    initSearch();
    initStaticActions();
    initAuthExpiry();
    document.body.classList.remove('is-auth-pending');
}

function initSidebar() {
    const collapsed = preferences.get('sidebar-collapsed', '0') === '1';
    document.body.classList.toggle('is-sidebar-collapsed', collapsed);

    document.querySelector('[data-sidebar-collapse]')?.addEventListener('click', () => {
        const next = !document.body.classList.contains('is-sidebar-collapsed');
        document.body.classList.toggle('is-sidebar-collapsed', next);
        preferences.set('sidebar-collapsed', next ? '1' : '0');
    });

    const closeMobile = () => document.body.classList.remove('is-mobile-menu-open');
    document.querySelector('[data-mobile-menu]')?.addEventListener('click', () => document.body.classList.add('is-mobile-menu-open'));
    document.querySelector('[data-mobile-scrim]')?.addEventListener('click', closeMobile);
    window.addEventListener('resize', () => { if (window.innerWidth > 900) closeMobile(); }, { passive: true });
}

function initProfile(admin) {
    const name = admin.name || 'Administrator';
    const role = (admin.roles || [])[0]?.replaceAll('-', ' ') || 'Administrator';
    const avatar = initials(name);
    document.querySelectorAll('[data-admin-name], [data-admin-menu-name]').forEach((el) => { el.textContent = name; });
    document.querySelector('[data-admin-role]')?.replaceChildren(document.createTextNode(titleCase(role)));
    document.querySelectorAll('[data-admin-email]').forEach((el) => { el.textContent = admin.email || ''; });
    document.querySelectorAll('[data-admin-avatar]').forEach((el) => { el.textContent = avatar; });

    const expiry = admin.session?.idle_expires_at || admin.session?.expires_at;
    if (expiry) {
        const display = new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(new Date(expiry));
        document.querySelector('[data-session-expiry]')?.replaceChildren(document.createTextNode(`Idle expiry ${display}`));
    }

    const trigger = document.querySelector('[data-profile-trigger]');
    const menu = document.querySelector('[data-profile-menu]');
    trigger?.addEventListener('click', (event) => {
        event.stopPropagation();
        const open = menu?.hidden ?? true;
        if (menu) menu.hidden = !open;
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', (event) => {
        if (menu && !menu.hidden && !menu.contains(event.target) && event.target !== trigger) {
            menu.hidden = true;
            trigger?.setAttribute('aria-expanded', 'false');
        }
    });

    document.querySelector('[data-admin-logout]')?.addEventListener('click', async () => {
        const button = document.querySelector('[data-admin-logout]');
        if (button) button.disabled = true;
        try { await logout(); } finally { window.location.replace('/admin/login'); }
    });
}

function initPermissions(permissions) {
    const set = new Set(permissions);
    document.querySelectorAll('[data-permission]').forEach((node) => {
        const permission = node.dataset.permission;
        if (permission && !set.has(permission)) node.hidden = true;
    });
}

function initStaticActions() {
    document.querySelectorAll('[data-planned-module]').forEach((node) => {
        node.addEventListener('click', () => toast(
            `${node.dataset.plannedModule} UI is next`,
            'The backend is already complete. This UI foundation is intentionally shipping dashboard-first so each operational module can be built on the same component system.',
            'info',
            5200,
        ));
    });
    document.querySelectorAll('[data-static-info]').forEach((node) => {
        node.addEventListener('click', () => toast('Coming into this shell next', node.dataset.staticInfo, 'info'));
    });
}

function initAuthExpiry() {
    window.addEventListener('orbit:auth-expired', () => {
        toast('Session expired', 'Your secure administrator session ended. Sign in again to continue.', 'warning', 2200);
        setTimeout(() => window.location.replace('/admin/login'), 450);
    });
}

function initSearch() {
    const palette = document.querySelector('[data-command-palette]');
    const input = document.querySelector('[data-command-input]');
    const body = document.querySelector('[data-command-body]');
    const openButtons = document.querySelectorAll('[data-global-search-open]');

    const open = () => {
        if (!palette) return;
        palette.hidden = false;
        document.documentElement.style.overflow = 'hidden';
        setTimeout(() => input?.focus(), 0);
    };
    const close = () => {
        if (!palette) return;
        palette.hidden = true;
        document.documentElement.style.overflow = '';
        if (input) input.value = '';
        renderSearchIntro(body);
    };

    openButtons.forEach((button) => button.addEventListener('click', open));
    document.querySelectorAll('[data-command-palette-close]').forEach((button) => button.addEventListener('click', close));
    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); open(); }
        if (event.key === 'Escape' && palette && !palette.hidden) close();
    });

    input?.addEventListener('input', () => {
        clearTimeout(commandTimer);
        const query = input.value.trim();
        if (query.length < 2) { renderSearchIntro(body); return; }
        renderSearchLoading(body);
        commandTimer = setTimeout(() => runSearch(query, body), 220);
    });
}

async function runSearch(query, body) {
    try {
        const data = await api(`/api/admin/v1/search?q=${encodeURIComponent(query)}&limit=6`);
        renderSearchResults(data, body);
    } catch (error) {
        body.innerHTML = '';
        const empty = document.createElement('div');
        empty.className = 'command-empty';
        empty.innerHTML = '<strong>Search unavailable</strong><p></p>';
        empty.querySelector('p').textContent = error.message || 'Try again.';
        body.append(empty);
    }
}

function renderSearchResults(data, body) {
    if (!body) return;
    body.innerHTML = '';
    let count = 0;
    const results = data?.results || {};
    Object.entries(results).forEach(([group, items]) => {
        if (!Array.isArray(items) || items.length === 0) return;
        const wrap = document.createElement('div');
        wrap.className = 'command-group';
        const label = document.createElement('p');
        label.className = 'command-group__label';
        label.textContent = titleCase(group.replaceAll('_', ' '));
        wrap.append(label);
        items.forEach((item) => { wrap.append(resultButton(item)); count += 1; });
        body.append(wrap);
    });

    const commands = Array.isArray(data?.commands) ? data.commands : [];
    if (commands.length) {
        const wrap = document.createElement('div');
        wrap.className = 'command-group';
        const label = document.createElement('p');
        label.className = 'command-group__label';
        label.textContent = 'Quick commands';
        wrap.append(label);
        commands.forEach((command) => { wrap.append(resultButton({ ...command, type: 'command', secondary: 'Quick action' })); count += 1; });
        body.append(wrap);
    }

    if (!count) {
        const empty = document.createElement('div');
        empty.className = 'command-empty';
        empty.innerHTML = '<strong>No matching results</strong><p>Try a name, email, Circle, incident, report, ticket or identifier.</p>';
        body.append(empty);
    }
}

function resultButton(item) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'command-result';
    const icon = document.createElement('span');
    icon.className = 'command-result__icon';
    icon.textContent = symbolFor(item.type);
    const copy = document.createElement('span');
    copy.className = 'command-result__copy';
    const strong = document.createElement('strong');
    strong.textContent = item.label || item.id || 'Result';
    const small = document.createElement('small');
    small.textContent = item.secondary || item.id || '';
    copy.append(strong, small);
    const type = document.createElement('span');
    type.className = 'command-result__type';
    type.textContent = item.type?.replaceAll('_', ' ') || 'result';
    button.append(icon, copy, type);
    button.addEventListener('click', () => navigateSearchResult(item));
    return button;
}

function navigateSearchResult(item) {
    const target = item.deep_link || '';
    if (implementedRoutes.has(target)) {
        window.location.assign(target);
        return;
    }
    toast('Result found', `${item.label || 'This result'} is available in the backend. Its dedicated UI page will use this same shell and search context in the next module build.`, 'success', 5200);
}

function renderSearchLoading(body) {
    if (!body) return;
    body.innerHTML = '<div class="command-loading"><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div></div>';
}

function renderSearchIntro(body) {
    if (!body) return;
    body.innerHTML = '<div class="command-empty"><span class="command-empty__icon">⌕</span><strong>Search across Orbit</strong><p>Results are filtered by your administrator permissions and sensitive fields remain masked by the backend.</p></div>';
}

function titleCase(value) { return String(value || '').replace(/\b\w/g, (match) => match.toUpperCase()); }
function symbolFor(type) {
    const symbols = { user: 'U', circle: 'C', device: 'D', sos: '!', report: 'R', support_ticket: 'S', subscription: '$', payment: '$', audit: 'A', system_incident: 'I', command: '→' };
    return symbols[type] || '•';
}
