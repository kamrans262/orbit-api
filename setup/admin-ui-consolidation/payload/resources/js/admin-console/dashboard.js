import {OrbitAdminApiError, firstSuccessfulEndpoint, unwrap} from './api-client.js';
import {errorText, escapeHtml, fmtNumber, valueOrDash} from './ui.js';

// Canonical dashboard adapter for the completed administrator backend.
// The M9 contract is wrapped as data.snapshot, with business / operations /
// environment namespaces. Metric aliases intentionally remain explicit so the
// UI can tolerate historical field placement without inventing values.
const METRICS = [
    ['Total users', ['business.users.total', 'users.total', 'total_users', 'users_total']],
    ['New users today', ['business.users.new_today', 'business.users.created_today', 'users.new_today', 'users.created_today', 'new_users_today']],
    ['Daily active users', ['business.users.dau', 'users.dau', 'dau', 'active_users_daily']],
    ['Monthly active users', ['business.users.mau', 'users.mau', 'mau', 'active_users_monthly']],
    ['Online users', ['business.users.online', 'business.users.online_now', 'users.online', 'users.online_now', 'online_users']],
    ['Active devices', ['business.devices.active', 'devices.active', 'active_devices']],
    ['Active Circles', ['business.circles.active', 'business.circles.total', 'circles.active', 'circles.total', 'active_circles', 'circles_total']],
    ['Active SOS', ['operations.safety.active_sos', 'operations.sos.active', 'safety.active_sos', 'sos.active', 'active_sos', 'active_sos_incidents']],
];

const ENGAGEMENT = [
    ['WAU', ['business.users.wau', 'users.wau', 'wau']],
    ['Messages routed', ['business.engagement.messages_routed', 'business.messaging.routed', 'engagement.messages_routed', 'messaging.routed', 'messages_routed', 'messages.total']],
    ['Moments created', ['business.engagement.moments_created', 'business.moments.created', 'engagement.moments_created', 'moments.created', 'moments_created', 'moments.total']],
    ['Pings sent', ['business.engagement.pings_sent', 'business.pings.sent', 'engagement.pings_sent', 'pings.sent', 'pings_sent', 'pings.total']],
    ['Circles created today', ['business.circles.created_today', 'circles.created_today', 'circles_created_today']],
];

const SAFETY = [
    ['SOS today', ['operations.safety.sos_today', 'operations.sos.today', 'safety.sos_today', 'sos.today', 'sos_today', 'sos_events_today']],
    ['Pending reports', ['operations.moderation.pending_reports', 'moderation.pending_reports', 'reports.pending', 'pending_reports']],
    ['Moderation backlog', ['operations.moderation.backlog', 'moderation.backlog', 'moderation_backlog']],
    ['Support backlog', ['operations.support.backlog', 'support.backlog', 'support_backlog']],
];

const HEALTH = [
    ['API', ['operations.health.api.status', 'operations.api.status', 'environment.health.api.status', 'health.api.status', 'api.status', 'api_health']],
    ['WebSocket', ['operations.health.websocket.status', 'operations.websocket.status', 'environment.health.websocket.status', 'health.websocket.status', 'websocket.status', 'websocket_health']],
    ['Queues', ['operations.health.queues.status', 'operations.queues.status', 'environment.health.queues.status', 'health.queues.status', 'queues.status', 'queue_health']],
    ['Push / SMS', ['operations.health.providers.status', 'operations.providers.status', 'environment.health.providers.status', 'health.providers.status', 'providers.status', 'provider_health']],
    ['Storage', ['operations.health.storage.status', 'operations.storage.status', 'environment.health.storage.status', 'health.storage.status', 'storage.status', 'storage_health']],
];

function getPath(source, path) {
    return path.split('.').reduce((value, segment) => value?.[segment], source);
}

function walkLeaves(source, prefix = [], output = []) {
    if (source === null || source === undefined) return output;

    if (typeof source !== 'object' || Array.isArray(source)) {
        output.push({path: prefix.join('.'), value: source});
        return output;
    }

    for (const [key, value] of Object.entries(source)) {
        const next = [...prefix, key];
        if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
            walkLeaves(value, next, output);
        } else {
            output.push({path: next.join('.'), value});
        }
    }

    return output;
}

function suffixValue(source, candidate) {
    const suffix = `.${candidate}`;
    const matches = walkLeaves(source).filter(({path, value}) => (
        value !== undefined
        && value !== null
        && (path === candidate || path.endsWith(suffix))
    ));

    // A suffix fallback is only safe when it resolves to one unambiguous leaf.
    return matches.length === 1 ? matches[0].value : null;
}

export function resolveDashboardValue(source, paths) {
    for (const path of paths) {
        const value = getPath(source, path);
        if (value !== undefined && value !== null) return value;
    }

    for (const path of paths) {
        const value = suffixValue(source, path);
        if (value !== undefined && value !== null) return value;
    }

    return null;
}

function primitive(value) {
    if (value === null || value === undefined) return null;
    if (typeof value === 'object') {
        return value.value ?? value.count ?? value.status ?? value.state ?? null;
    }
    return value;
}

function metricCard(label, value, index) {
    const normalized = primitive(value);
    return `<article class="orbit-metric-card" style="--metric-delay:${index * 28}ms">
        <div class="orbit-metric-card__header"><span>${escapeHtml(label)}</span><span class="orbit-metric-card__dot" aria-hidden="true"></span></div>
        <strong>${escapeHtml(fmtNumber(normalized))}</strong>
        <small>Live server aggregate</small>
    </article>`;
}

function listHtml(source, definitions) {
    return definitions.map(([label, paths]) => {
        const value = primitive(resolveDashboardValue(source, paths));
        return `<div class="orbit-stat-row"><span>${escapeHtml(label)}</span><strong>${escapeHtml(fmtNumber(value))}</strong></div>`;
    }).join('');
}

function healthHtml(source) {
    return HEALTH.map(([label, paths]) => {
        const raw = primitive(resolveDashboardValue(source, paths));
        const value = valueOrDash(raw);
        const tone = /healthy|ok|operational|online|green|normal/i.test(String(value)) ? 'good' : /degrad|warn|yellow/i.test(String(value)) ? 'warn' : raw === null ? 'neutral' : 'bad';
        return `<div class="orbit-health-row"><span><i class="orbit-health-dot orbit-health-dot--${tone}"></i>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`;
    }).join('');
}

export function normalizeDashboard(payload) {
    const data = unwrap(payload);
    const snapshot = data?.snapshot ?? data?.summary?.snapshot ?? data?.dashboard?.snapshot ?? data?.summary ?? data?.dashboard ?? data;

    if (!snapshot || typeof snapshot !== 'object' || Array.isArray(snapshot)) {
        throw new OrbitAdminApiError('The dashboard response did not contain the expected operational snapshot.', {
            code: 'dashboard_contract_invalid',
        });
    }

    // This field is explicitly guaranteed by the completed M9 dashboard
    // contract. Fail visibly rather than silently rendering an all-dash board
    // if a future frontend/backend change breaks the response shape.
    const totalUsers = resolveDashboardValue(snapshot, ['business.users.total', 'users.total']);
    if (totalUsers === null || totalUsers === undefined) {
        throw new OrbitAdminApiError('The dashboard response contract is incompatible with this Admin Console build.', {
            code: 'dashboard_contract_mismatch',
        });
    }

    return snapshot;
}

export function dashboardViewModel(payload) {
    const snapshot = normalizeDashboard(payload);

    return {
        snapshot,
        metrics: Object.fromEntries(METRICS.map(([label, paths]) => [label, primitive(resolveDashboardValue(snapshot, paths))])),
        engagement: Object.fromEntries(ENGAGEMENT.map(([label, paths]) => [label, primitive(resolveDashboardValue(snapshot, paths))])),
        safety: Object.fromEntries(SAFETY.map(([label, paths]) => [label, primitive(resolveDashboardValue(snapshot, paths))])),
        health: Object.fromEntries(HEALTH.map(([label, paths]) => [label, primitive(resolveDashboardValue(snapshot, paths))])),
    };
}

export function initDashboard(root) {
    let loading = false;

    async function load() {
        if (loading) return;
        loading = true;
        root.querySelector('[data-dashboard-loading]').hidden = false;
        root.querySelector('[data-dashboard-error]').hidden = true;
        root.querySelector('[data-dashboard-content]').hidden = true;

        try {
            const {payload} = await firstSuccessfulEndpoint([
                '/api/admin/v1/dashboard',
                '/api/admin/v1/dashboard/summary',
                '/api/admin/v1/dashboard/overview',
            ]);
            const data = normalizeDashboard(payload);

            root.querySelector('[data-dashboard-metrics]').innerHTML = METRICS
                .map(([label, paths], index) => metricCard(label, resolveDashboardValue(data, paths), index))
                .join('');
            root.querySelector('[data-dashboard-engagement]').innerHTML = listHtml(data, ENGAGEMENT);
            root.querySelector('[data-dashboard-safety]').innerHTML = listHtml(data, SAFETY);
            root.querySelector('[data-dashboard-health]').innerHTML = healthHtml(data);

            root.querySelector('[data-dashboard-loading]').hidden = true;
            root.querySelector('[data-dashboard-content]').hidden = false;
        } catch (error) {
            root.querySelector('[data-dashboard-loading]').hidden = true;
            root.querySelector('[data-dashboard-error]').hidden = false;
            root.querySelector('[data-dashboard-error-message]').textContent = errorText(error);
        } finally {
            loading = false;
        }
    }

    root.querySelector('[data-dashboard-reload]')?.addEventListener('click', load);
    root.querySelector('[data-dashboard-retry]')?.addEventListener('click', load);
    load();
}
