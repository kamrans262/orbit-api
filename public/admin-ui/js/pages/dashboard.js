import { api } from '../core/api.js';
import { ensureAuthenticated } from '../core/auth.js';
import { initShell } from '../core/shell.js';
import { compactNumber, dateTime, integer, minorUnits, path, relativeTime, toast } from '../core/ui.js';

let admin = null;
let snapshot = null;

bootstrap();

async function bootstrap() {
    admin = await ensureAuthenticated();
    if (!admin) return;
    initShell(admin);
    renderAdmin(admin);
    bindRefresh();
    await loadDashboard();
}

function bindRefresh() {
    document.querySelector('[data-dashboard-refresh]')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        button.classList.add('is-spinning');
        await loadDashboard(true);
        setTimeout(() => button.classList.remove('is-spinning'), 420);
    });
}

async function loadDashboard(manual = false) {
    const progress = document.querySelector('[data-route-progress]');
    progress?.classList.remove('is-active');
    void progress?.offsetWidth;
    progress?.classList.add('is-active');

    try {
        const data = await api('/api/admin/v1/dashboard');
        snapshot = data?.snapshot || {};
        renderSnapshot(snapshot);
        if (manual) toast('Dashboard refreshed', 'The latest business and operational snapshot is now displayed.', 'success', 2400);
    } catch (error) {
        toast('Dashboard unavailable', error.message || 'The operational snapshot could not be loaded.', 'danger', 6500);
        renderFailure();
    }
}

function renderAdmin(value) {
    const first = String(value.name || 'Administrator').split(/\s+/)[0];
    document.querySelector('[data-greeting-name]')?.replaceChildren(document.createTextNode(first));
    const hour = new Date().getHours();
    document.querySelector('[data-daypart]')?.replaceChildren(document.createTextNode(hour < 12 ? 'morning' : hour < 18 ? 'afternoon' : 'evening'));

    document.querySelector('[data-session-roles]')?.replaceChildren(document.createTextNode((value.roles || []).map(titleCase).join(', ') || '—'));
    document.querySelector('[data-session-permissions]')?.replaceChildren(document.createTextNode(integer((value.permissions || []).length)));
    document.querySelector('[data-session-expiry-card]')?.replaceChildren(document.createTextNode(value.session?.idle_expires_at ? relativeTime(value.session.idle_expires_at) : '—'));
}

function renderSnapshot(value) {
    const business = value.business || {};
    document.querySelector('[data-dashboard-updated]')?.replaceChildren(document.createTextNode(value.generated_at ? `Updated ${relativeTime(value.generated_at)}` : 'Updated now'));
    document.querySelector('[data-environment]')?.replaceChildren(document.createTextNode(titleCase(value.environment || 'unknown')));

    document.querySelectorAll('[data-stat-card]').forEach((card) => {
        const element = card.querySelector('[data-stat-value]');
        const raw = path(business, card.dataset.statKey, 0);
        const formatted = card.dataset.money === '1' ? minorUnits(raw) : compactNumber(raw);
        if (element) {
            element.className = '';
            animateValue(element, raw, formatted, card.dataset.money === '1');
        }
    });

    renderEngagement(business.engagement_today || {}, business.users || {});
    setText('[data-sos-active]', integer(business.safety?.active_sos));
    setText('[data-sos-today]', integer(business.safety?.sos_today));
    setText('[data-moderation-backlog]', integer(business.backlog?.moderation));
    setText('[data-support-backlog]', integer(business.backlog?.support));
    setText('[data-active-devices]', integer(business.users?.active_devices));
    document.querySelectorAll('[data-growth]').forEach((el) => setTextEl(el, integer(business.users?.[el.dataset.growth])));

    renderHealth(value.operations || {});
    renderIntegrations(value.operations?.integrations || []);
}

function renderEngagement(engagement, users) {
    const values = ['messages_routed', 'moments_created', 'pings_sent'].map((key) => Number(engagement[key] || 0));
    const max = Math.max(...values, 1);
    ['messages_routed', 'moments_created', 'pings_sent'].forEach((key) => {
        const amount = Number(engagement[key] || 0);
        setText(`[data-engagement="${key}"]`, integer(amount));
        const bar = document.querySelector(`[data-engagement-bar="${key}"]`);
        if (bar) bar.style.width = `${Math.max(8, Math.round((amount / max) * 100))}%`;
    });

    const dau = Number(users.dau || 0);
    const mau = Number(users.mau || 0);
    const ratio = mau > 0 ? Math.min(100, Math.round((dau / mau) * 100)) : 0;
    setText('[data-activity-ratio]', `${ratio}%`);
    document.querySelector('[data-activity-ring]')?.style.setProperty('--ratio', `${ratio * 3.6}deg`);
}

function renderHealth(health) {
    const definitions = [
        ['Database', health.database?.status === 'healthy' ? 'healthy' : 'danger', health.database?.driver || health.database?.status || 'unknown'],
        ['API', Number(health.api?.errors || 0) > 0 ? 'warning' : 'healthy', `${integer(health.api?.requests)} req · ${integer(health.api?.p95_latency_ms)}ms p95`],
        ['Queues', Number(health.queues?.failed || 0) > 0 ? 'warning' : 'healthy', `${integer(health.queues?.pending)} pending · ${integer(health.queues?.failed)} failed`],
        ['Notifications', Number(health.notifications?.failed || 0) > 0 ? 'warning' : 'healthy', `${integer(health.notifications?.failed)} failed`],
        ['Media', Number(health.media?.failed || 0) > 0 ? 'warning' : 'healthy', `${integer(health.media?.uploads)} uploads · ${integer(health.media?.failed)} failed`],
        ['Realtime', health.websocket?.status === 'no_telemetry' ? 'warning' : Number(health.websocket?.fanout_lag_ms || 0) > 1200 ? 'warning' : 'healthy', health.websocket?.status === 'no_telemetry' ? 'No telemetry' : `${integer(health.websocket?.connections)} connections · ${integer(health.websocket?.fanout_lag_ms)}ms lag`],
    ];

    const grid = document.querySelector('[data-health-grid]');
    if (grid) {
        grid.innerHTML = '';
        definitions.forEach(([name, state, secondary]) => {
            const row = document.createElement('div');
            row.className = `health-item is-${state}`;
            row.innerHTML = '<span class="health-item__icon">●</span><div><strong></strong><small></small></div><span class="health-indicator"></span>';
            row.querySelector('strong').textContent = name;
            row.querySelector('small').textContent = secondary;
            grid.append(row);
        });
    }

    const bad = definitions.filter(([, state]) => state === 'danger').length;
    const warn = definitions.filter(([, state]) => state === 'warning').length;
    const summary = document.querySelector('[data-health-summary]');
    if (summary) {
        summary.innerHTML = '<span class="status-dot"></span><span></span>';
        const dot = summary.querySelector('.status-dot');
        dot.classList.add(bad ? 'status-dot--danger' : warn ? 'status-dot--warning' : 'status-dot--success');
        summary.querySelector('span:last-child').textContent = bad ? `${bad} critical` : warn ? `${warn} need attention` : 'All core services healthy';
    }

    const sidebar = document.querySelector('[data-sidebar-health]');
    if (sidebar) {
        const dot = sidebar.querySelector('[data-sidebar-health-dot]');
        dot.className = `status-dot ${bad ? 'status-dot--danger' : warn ? 'status-dot--warning' : 'status-dot--success'}`;
        const strong = sidebar.querySelector('strong');
        const small = sidebar.querySelector('small');
        if (strong) strong.textContent = bad ? 'Platform degraded' : warn ? 'Needs attention' : 'Platform healthy';
        if (small) small.textContent = bad ? 'Review system health' : warn ? 'Some telemetry warnings' : 'Core services operational';
    }
}

function renderIntegrations(items) {
    const list = document.querySelector('[data-integration-list]');
    if (!list) return;
    list.innerHTML = '';
    if (!Array.isArray(items) || items.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'integration-row';
        empty.innerHTML = '<span class="integration-row__icon">i</span><div><strong>No integrations reported</strong><small>Run the integration catalog sync if expected.</small></div>';
        list.append(empty);
        return;
    }
    items.slice(0, 8).forEach((item) => {
        const health = String(item.health || (item.enabled ? 'unknown' : 'disabled')).toLowerCase();
        const state = ['healthy', 'ok', 'operational'].includes(health) ? 'healthy' : ['failed', 'down', 'error'].includes(health) ? 'danger' : item.enabled ? 'warning' : 'muted';
        const row = document.createElement('div');
        row.className = 'integration-row';
        row.innerHTML = '<span class="integration-row__icon"></span><div><strong></strong><small></small></div><span class="integration-state"></span>';
        row.querySelector('.integration-row__icon').textContent = String(item.service || 'I').slice(0, 1).toUpperCase();
        row.querySelector('strong').textContent = String(item.service || 'integration').replaceAll('_', ' ');
        row.querySelector('small').textContent = item.provider || (item.enabled ? 'Configured' : 'Not configured');
        const status = row.querySelector('.integration-state');
        status.className = `integration-state integration-state--${state}`;
        status.textContent = item.enabled ? health : 'disabled';
        list.append(row);
    });
}

function renderFailure() {
    document.querySelectorAll('[data-stat-value]').forEach((el) => { el.className = ''; el.textContent = '—'; });
    document.querySelector('[data-dashboard-updated]')?.replaceChildren(document.createTextNode('Snapshot unavailable'));
}

function animateValue(element, raw, finalValue, noCompact = false) {
    const numeric = Number(raw || 0);
    if (!Number.isFinite(numeric) || Math.abs(numeric) > 10000000 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        element.textContent = finalValue;
        return;
    }
    const start = performance.now();
    const duration = 520;
    const tick = (now) => {
        const progress = Math.min(1, (now - start) / duration);
        const eased = 1 - Math.pow(1 - progress, 3);
        const current = Math.round(numeric * eased);
        element.textContent = noCompact ? minorUnits(current) : compactNumber(current);
        if (progress < 1) requestAnimationFrame(tick); else element.textContent = finalValue;
    };
    requestAnimationFrame(tick);
}

function setText(selector, value) { const el = document.querySelector(selector); if (el) setTextEl(el, value); }
function setTextEl(el, value) { el.className = el.className.replace(/\bskeleton\S*/g, '').trim(); el.textContent = value ?? '—'; }
function titleCase(value) { return String(value || '').replaceAll('-', ' ').replace(/\b\w/g, (match) => match.toUpperCase()); }
