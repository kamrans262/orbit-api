import {adminApi, OrbitAdminApiError, pagination, unwrap} from './api-client.js';
import {SOS_API} from './sos-contract.js';
import {readAdminSession} from './auth-session.js';
import {askForm, badge, debounce, errorText, escapeHtml, fmtDate, fmtNumber, kv, maskIdentifier, setBusy, state, toast, valueOrDash} from './ui.js';

const ACTIVE_REFRESH_MS = 10000;
const DETAIL_REFRESH_MS = 8000;
const SENSITIVE_REVEAL_MS = 60000;

function apiPath(route, id = null) {
    if (!route?.uri) throw new OrbitAdminApiError('The required SOS backend route is unavailable.', {code: 'sos_route_unavailable'});
    if (id === null) return route.uri;
    return route.uri.replace(/\{[^}]+\}/, encodeURIComponent(id));
}

function routeFor(kind) {
    return SOS_API[kind] ?? null;
}

function closurePayload(values) {
    const route = routeFor('closure');
    const fields = route?.fields ?? {};
    if (!fields.status || !fields.resolution || !fields.reason) {
        throw new OrbitAdminApiError('The SOS operational-closure request contract is unavailable. Re-run the M3 installer after verifying the backend.', {code: 'sos_closure_contract_unavailable'});
    }

    return {
        [fields.status]: 'closed',
        [fields.resolution]: values.resolution,
        [fields.reason]: values.reason,
    };
}

function adminPermissionSet() {
    const source = readAdminSession()?.admin?.permissions;
    if (!Array.isArray(source)) return new Set();

    return new Set(source.flatMap((permission) => {
        if (typeof permission === 'string') return [permission];
        if (!permission || typeof permission !== 'object') return [];
        return [permission.name, permission.slug, permission.key, permission.permission].filter(Boolean);
    }).map((permission) => String(permission).trim()).filter(Boolean));
}

function routeAllowed(kind) {
    const route = routeFor(kind);
    if (!route) return false;
    const required = Array.isArray(route.permissions) ? route.permissions.filter(Boolean).map(String) : [];
    if (!required.length) return true;

    const granted = adminPermissionSet();
    // Some older Foundation /auth/me payloads do not include permission names.
    // In that case the server remains authoritative rather than the browser
    // accidentally hiding every permitted action.
    if (!granted.size) return true;
    return required.every((permission) => granted.has(permission));
}

function applyActionPermissions(root) {
    const actionRoutes = {
        assign: 'assignment',
        classify: 'classification',
        'add-note': 'notes',
        close: 'closure',
        export: 'export',
        'reveal-location': 'location',
        'reveal-recording': 'recording',
        'access-history': 'accessHistory',
    };

    for (const [action, kind] of Object.entries(actionRoutes)) {
        root.querySelectorAll(`[data-action="${action}"]`).forEach((button) => {
            const allowed = routeAllowed(kind);
            button.disabled = !allowed;
            if (!allowed) {
                button.setAttribute('aria-disabled', 'true');
                button.title = 'Your administrator role does not include the permission required for this action.';
            } else {
                button.removeAttribute('aria-disabled');
                button.removeAttribute('title');
            }
        });
    }
}

function requestRoute(kind, id, body = undefined, options = {}) {
    const route = routeFor(kind);
    const method = route?.method ?? 'GET';
    return adminApi(apiPath(route, id), {
        method,
        ...(body === undefined ? {} : {body}),
        ...options,
    });
}

function sosRows(payload) {
    const candidates = [
        payload?.data,
        payload?.data?.incidents,
        payload?.data?.items,
        payload?.incidents,
        payload?.items,
        payload?.results,
    ];

    for (const candidate of candidates) {
        if (Array.isArray(candidate)) return candidate;
        if (Array.isArray(candidate?.data)) return candidate.data;
        if (Array.isArray(candidate?.items)) return candidate.items;
    }

    return [];
}

function sosPagination(payload, rowsLength = 0) {
    const sources = [
        payload,
        payload?.data,
        payload?.data?.incidents,
        payload?.data?.items,
        payload?.incidents,
        payload?.items,
    ];

    for (const source of sources) {
        if (!source || typeof source !== 'object') continue;
        const meta = source?.meta ?? source;
        if (meta?.current_page !== undefined || meta?.page !== undefined || meta?.last_page !== undefined || meta?.total !== undefined) {
            return pagination(source, rowsLength);
        }
    }

    return pagination(payload, rowsLength);
}

function sosDetail(payload) {
    const data = unwrap(payload);
    return data?.incident ?? data?.sos ?? data?.event ?? data ?? {};
}

function controlOf(incident) {
    return incident?.control ?? incident?.incident_control ?? incident?.admin_control ?? incident?.operations ?? {};
}

function incidentId(incident) {
    return incident?.id ?? incident?.sos_id ?? incident?.sos_event_id ?? '—';
}

function originatorOf(incident) {
    return incident?.user ?? incident?.originator ?? incident?.consumer ?? {};
}

function circleOf(incident) {
    return incident?.circle ?? {};
}

function statusOf(incident) {
    return incident?.status ?? incident?.consumer_status ?? incident?.sos_status ?? 'unknown';
}

function operationalStatusOf(incident) {
    const control = controlOf(incident);
    return control?.operational_status ?? incident?.operational_status ?? 'open';
}

function stageOf(incident) {
    const value = incident?.escalation_stage ?? incident?.stage ?? controlOf(incident)?.escalation_stage ?? 0;
    const stage = Number(value);
    return Number.isFinite(stage) ? stage : 0;
}

function assignedAdminOf(incident) {
    const control = controlOf(incident);
    return control?.assigned_admin ?? incident?.assigned_admin ?? incident?.assignee ?? {};
}

function assignedAdminId(incident) {
    const assigned = assignedAdminOf(incident);
    const control = controlOf(incident);
    return assigned?.id ?? control?.assigned_admin_id ?? incident?.assigned_admin_id ?? incident?.assignee_admin_id ?? null;
}

function activationTime(incident) {
    return incident?.activated_at ?? incident?.activation_time ?? incident?.created_at ?? null;
}

function elapsedLabel(value) {
    if (!value) return '—';
    const started = new Date(value).getTime();
    if (!Number.isFinite(started)) return '—';
    const seconds = Math.max(0, Math.floor((Date.now() - started) / 1000));
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainder = seconds % 60;
    if (hours > 0) return `${hours}h ${minutes}m`;
    if (minutes > 0) return `${minutes}m ${remainder}s`;
    return `${remainder}s`;
}

function responderSummary(incident) {
    const summary = incident?.responder_summary ?? incident?.responders_summary ?? {};
    const responders = Array.isArray(incident?.responders) ? incident.responders : [];
    const engaged = Number(summary?.engaged ?? responders.filter((row) => String(row?.status).toLowerCase() === 'engaged').length);
    const acknowledged = Number(summary?.acknowledged ?? summary?.responded ?? responders.filter((row) => row?.responded_at).length);
    const total = Number(summary?.total ?? responders.length);
    if (!Number.isFinite(total) || total === 0) return 'No responders';
    return `${engaged} engaged · ${acknowledged}/${total} ack`;
}

function fallbackLabel(incident) {
    const fallback = incident?.fallback ?? incident?.sms_fallback ?? incident?.delivery?.fallback ?? {};
    const status = fallback?.status ?? incident?.fallback_status ?? incident?.sms_fallback_status ?? null;
    if (status) return String(status);
    return stageOf(incident) >= 2 ? 'fallback stage reached' : 'not reached';
}

function flagsOf(incident) {
    const control = controlOf(incident);
    return {
        falseAlarm: Boolean(control?.false_alarm ?? incident?.false_alarm),
        technicalFailure: Boolean(control?.technical_failure ?? incident?.technical_failure),
        abuseFlag: Boolean(control?.abuse_flag ?? incident?.abuse_flag),
        internalEscalation: Boolean(control?.internal_escalation ?? control?.internally_escalated ?? incident?.internal_escalation),
    };
}

function flagPills(incident) {
    const flags = flagsOf(incident);
    const labels = [];
    if (flags.falseAlarm) labels.push('<span class="orbit-flag orbit-flag--muted">False alarm</span>');
    if (flags.technicalFailure) labels.push('<span class="orbit-flag orbit-flag--warning">Technical</span>');
    if (flags.abuseFlag) labels.push('<span class="orbit-flag orbit-flag--danger">Abuse</span>');
    if (flags.internalEscalation) labels.push('<span class="orbit-flag orbit-flag--critical">Escalated</span>');
    return labels.join('');
}

function updateElapsed(root) {
    root.querySelectorAll('[data-elapsed-at]').forEach((element) => {
        element.textContent = elapsedLabel(element.dataset.elapsedAt);
    });
}

function summaryFromPayload(payload) {
    const source = payload?.meta?.summary ?? payload?.data?.summary ?? payload?.summary ?? null;
    if (!source || typeof source !== 'object' || Array.isArray(source)) return null;
    return source;
}

function renderSummary(root, payload) {
    const target = root.querySelector('[data-sos-summary]');
    if (!target) return;
    const summary = summaryFromPayload(payload);
    if (!summary) {
        target.hidden = true;
        target.innerHTML = '';
        return;
    }

    const cards = [
        ['Active', summary.active ?? summary.active_incidents],
        ['Unassigned', summary.unassigned ?? summary.unassigned_incidents],
        ['Escalated', summary.escalated ?? summary.high_escalation],
        ['Fallback used', summary.fallback_used ?? summary.fallback_incidents],
    ].filter(([, value]) => value !== undefined && value !== null);

    if (!cards.length) {
        target.hidden = true;
        return;
    }

    target.innerHTML = cards.map(([label, value]) => `<div><span>${escapeHtml(label)}</span><strong>${escapeHtml(fmtNumber(value))}</strong></div>`).join('');
    target.hidden = false;
}

export function initSosIndex(root) {
    const form = root.querySelector('[data-sos-filters]');
    const body = root.querySelector('[data-sos-body]');
    const detailBase = root.dataset.detailBase;
    const scopeInput = root.querySelector('[data-sos-scope-input]');
    let page = 1;
    let meta = null;
    let controller = null;
    let loading = false;
    let refreshTimer = null;

    const load = async ({background = false} = {}) => {
        if (loading && background) return;
        controller?.abort();
        controller = new AbortController();
        loading = true;
        if (!background) state(root, 'loading');

        const data = new FormData(form);
        const params = new URLSearchParams();
        for (const [key, value] of data.entries()) {
            if (String(value).trim() !== '') params.set(key, String(value).trim());
        }
        params.set('page', String(page));

        try {
            const base = apiPath(SOS_API.list);
            const payload = await adminApi(`${base}?${params.toString()}`, {signal: controller.signal});
            const rows = sosRows(payload);
            meta = sosPagination(payload, rows.length);
            renderSummary(root, payload);

            if (!rows.length) {
                const history = scopeInput.value === 'history';
                root.querySelector('[data-empty-title]').textContent = history ? 'No historical incidents match these filters.' : 'No active incidents match these filters.';
                root.querySelector('[data-empty-copy]').textContent = history ? 'Change the search or filters and try again.' : 'Change the filters or switch to incident history.';
                state(root, 'empty');
                return;
            }

            body.innerHTML = rows.map((incident) => {
                const id = incidentId(incident);
                const originator = originatorOf(incident);
                const circle = circleOf(incident);
                const assigned = assignedAdminOf(incident);
                const activatedAt = activationTime(incident);
                const stage = stageOf(incident);
                const flags = flagPills(incident);
                const userName = originator?.name ?? originator?.display_name ?? incident?.user_name ?? (originator?.id ?? incident?.user_id ? `User ${originator?.id ?? incident?.user_id}` : 'User');
                const circleName = circle?.name ?? incident?.circle_name ?? (circle?.id ?? incident?.circle_id ? `Circle ${maskIdentifier(circle?.id ?? incident?.circle_id)}` : 'Circle');
                const assignment = assigned?.name ?? assigned?.display_name ?? (assignedAdminId(incident) ? `Admin ${maskIdentifier(assignedAdminId(incident))}` : 'Unassigned');
                const criticalClass = String(statusOf(incident)).toLowerCase() === 'active' ? ' class="orbit-sos-row--active"' : '';

                return `<tr${criticalClass}>
                    <td><div class="orbit-primary-cell"><strong>${escapeHtml(maskIdentifier(id, 8, 5))}</strong><span class="orbit-elapsed" data-elapsed-at="${escapeHtml(activatedAt ?? '')}">${escapeHtml(elapsedLabel(activatedAt))}</span>${flags ? `<small class="orbit-inline-flags">${flags}</small>` : ''}</div></td>
                    <td>${badge(statusOf(incident))}<small class="orbit-cell-substatus">${badge(operationalStatusOf(incident))}</small></td>
                    <td><div class="orbit-primary-cell"><strong>${escapeHtml(userName)}</strong><span>${escapeHtml(circleName)}</span></div></td>
                    <td><span class="orbit-stage-badge orbit-stage-badge--${stage}">Stage ${stage}</span><small class="orbit-cell-substatus">${escapeHtml(fallbackLabel(incident))}</small></td>
                    <td>${escapeHtml(responderSummary(incident))}</td>
                    <td>${escapeHtml(assignment)}</td>
                    <td>${escapeHtml(fmtDate(activatedAt))}</td>
                    <td><a class="orbit-row-open" href="${detailBase}/${encodeURIComponent(id)}" aria-label="Open SOS incident">→</a></td>
                </tr>`;
            }).join('');

            root.querySelector('[data-page-summary]').textContent = `Page ${meta.current}${meta.last ? ` of ${meta.last}` : ''} · ${meta.total} total`;
            root.querySelector('[data-prev]').disabled = !meta.hasPrev;
            root.querySelector('[data-next]').disabled = !meta.hasNext;
            state(root, 'ready');
            updateElapsed(root);
        } catch (error) {
            if (error.name === 'AbortError') return;
            if (!background) state(root, 'error', errorText(error));
            else toast(`SOS background refresh failed. ${errorText(error)}`, 'error');
        } finally {
            loading = false;
        }
    };

    const scheduleRefresh = () => {
        window.clearInterval(refreshTimer);
        if (scopeInput.value !== 'active') return;
        refreshTimer = window.setInterval(() => {
            if (document.visibilityState === 'visible') load({background: true});
        }, ACTIVE_REFRESH_MS);
    };

    const changed = debounce(() => {
        page = 1;
        load();
    });

    form.addEventListener('input', changed);
    form.addEventListener('change', changed);
    root.querySelector('[data-prev]').addEventListener('click', () => { if (meta?.hasPrev) { page -= 1; load(); } });
    root.querySelector('[data-next]').addEventListener('click', () => { if (meta?.hasNext) { page += 1; load(); } });
    root.querySelectorAll('[data-retry],[data-reload]').forEach((button) => button.addEventListener('click', () => load()));

    root.querySelectorAll('[data-sos-scope]').forEach((button) => {
        button.addEventListener('click', () => {
            const scope = button.dataset.sosScope;
            scopeInput.value = scope;
            root.querySelectorAll('[data-sos-scope]').forEach((item) => {
                const active = item === button;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            page = 1;
            scheduleRefresh();
            load();
        });
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && scopeInput.value === 'active') load({background: true});
    });
    window.setInterval(() => updateElapsed(root), 1000);

    scheduleRefresh();
    load();
}

function safeText(value) {
    return value === true ? 'Yes' : value === false ? 'No' : valueOrDash(value);
}

function compactKv(items) {
    return items.map(([label, value]) => `<div><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(safeText(value))}</dd></div>`).join('');
}

function renderStage(incident) {
    const stage = stageOf(incident);
    return `<div class="orbit-stage-track" aria-label="Escalation stage ${stage}">${[0, 1, 2, 3].map((step) => `<span class="${step <= stage ? 'is-reached' : ''}"><i>${step}</i><small>${['Activated', 'Secondary', 'SMS fallback', 'Emergency info'][step]}</small></span>`).join('')}</div>`;
}

function safeEscalations(incident) {
    const rows = Array.isArray(incident?.escalations) ? incident.escalations : Array.isArray(incident?.timeline?.escalations) ? incident.timeline.escalations : [];
    if (!rows.length) return '<p class="orbit-muted">No escalation events recorded.</p>';
    return rows.map((row) => `<div><span class="orbit-timeline__dot" aria-hidden="true"></span><div><strong>Stage ${escapeHtml(row?.stage ?? '—')} · ${escapeHtml(row?.action ?? row?.kind ?? 'Escalation')}</strong><p>${escapeHtml(row?.status ?? '')}</p><small>${escapeHtml(fmtDate(row?.occurred_at ?? row?.created_at))}</small></div></div>`).join('');
}

function safeResponders(incident) {
    const responders = Array.isArray(incident?.responders) ? incident.responders : Array.isArray(incident?.responder_statuses) ? incident.responder_statuses : [];
    if (!responders.length) return '<p class="orbit-muted">No responder records are available.</p>';

    return `<table><thead><tr><th>Responder</th><th>Status</th><th>Acknowledged</th><th>Engaged</th><th>Delivery</th></tr></thead><tbody>${responders.map((row) => {
        const user = row?.user ?? row?.responder ?? {};
        const name = user?.name ?? user?.display_name ?? row?.name ?? (row?.user_id ? `User ${row.user_id}` : 'Responder');
        const delivery = row?.delivery_status ?? row?.notification_status ?? row?.push_status ?? '—';
        return `<tr><td><strong>${escapeHtml(name)}</strong><small>${row?.user_id ? escapeHtml(maskIdentifier(row.user_id)) : ''}</small></td><td>${badge(row?.status ?? 'unknown')}</td><td>${escapeHtml(fmtDate(row?.responded_at ?? row?.acknowledged_at))}</td><td>${escapeHtml(fmtDate(row?.engaged_at))}</td><td>${escapeHtml(safeText(delivery))}</td></tr>`;
    }).join('')}</tbody></table>`;
}

function safeNotes(incident) {
    const notes = Array.isArray(incident?.notes) ? incident.notes : Array.isArray(incident?.internal_notes) ? incident.internal_notes : [];
    if (!notes.length) return '<p class="orbit-muted">No internal notes.</p>';
    return `<div class="orbit-note-list">${notes.map((note) => {
        const actor = note?.admin ?? note?.author ?? {};
        const author = actor?.name ?? actor?.display_name ?? (note?.admin_id ? `Admin ${maskIdentifier(note.admin_id)}` : 'Administrator');
        return `<div><p>${escapeHtml(note?.body ?? note?.note ?? note?.content ?? '')}</p><small>${escapeHtml(author)} · ${escapeHtml(fmtDate(note?.created_at ?? note?.occurred_at))}</small></div>`;
    }).join('')}</div>`;
}

function deliveryHealth(incident) {
    const delivery = incident?.delivery ?? incident?.delivery_health ?? incident?.notifications ?? {};
    const push = delivery?.push ?? incident?.push ?? {};
    const sms = delivery?.sms ?? delivery?.fallback ?? incident?.sms_fallback ?? {};
    return [
        ['Push', push?.status ?? incident?.push_status],
        ['Push attempts', push?.attempts ?? incident?.push_attempts],
        ['Push provider', push?.provider_masked ?? push?.provider ?? incident?.push_provider],
        ['Fallback / SMS', sms?.status ?? incident?.sms_fallback_status ?? fallbackLabel(incident)],
        ['Fallback attempts', sms?.attempts ?? incident?.fallback_attempts],
        ['Last provider update', push?.updated_at ?? sms?.updated_at ?? delivery?.updated_at],
    ];
}

function signalHealth(incident) {
    const network = incident?.network_health ?? incident?.health?.network ?? {};
    const location = incident?.location_health ?? incident?.health?.location ?? {};
    const recording = incident?.recording_health ?? incident?.health?.recording ?? {};
    return [
        ['Network', network?.status ?? incident?.network_status],
        ['Last location update', location?.last_update_at ?? location?.updated_at ?? incident?.last_location_at],
        ['Location stream', location?.status ?? incident?.location_update_status],
        ['Recording upload', recording?.status ?? incident?.recording_upload_status ?? (incident?.has_recording ? 'available' : null)],
        ['Recording updated', recording?.updated_at ?? incident?.recording_updated_at],
        ['Retry attempts', network?.retry_attempts ?? recording?.retry_attempts ?? incident?.retry_attempts],
    ];
}

function classificationHtml(incident) {
    const flags = flagsOf(incident);
    return [
        ['False alarm', flags.falseAlarm, 'muted'],
        ['Technical failure', flags.technicalFailure, 'warning'],
        ['Abuse flag', flags.abuseFlag, 'danger'],
        ['Internal escalation', flags.internalEscalation, 'critical'],
    ].map(([label, active, tone]) => `<div class="orbit-flag-card ${active ? `is-active orbit-flag-card--${tone}` : ''}"><span aria-hidden="true">${active ? '✓' : '–'}</span><strong>${escapeHtml(label)}</strong><small>${active ? 'Applied' : 'Not applied'}</small></div>`).join('');
}

async function reauthenticate() {
    const values = await askForm({
        title: 'Reauthenticate administrator',
        message: 'This high-risk SOS operation requires your current administrator password. It is sent only to Orbit’s existing reauthentication endpoint and is not stored by the console.',
        eyebrow: 'High-risk action',
        confirm: 'Reauthenticate',
        fields: [
            {name: 'password', label: 'Administrator password', type: 'password', autocomplete: 'current-password', maxLength: 255},
        ],
    });
    if (!values) return false;
    await adminApi('/api/admin/v1/auth/reauthenticate', {method: 'POST', body: {password: values.password}});
    return true;
}

async function requestWithReauthentication(kind, id, body, options = {}) {
    try {
        return await requestRoute(kind, id, body, options);
    } catch (error) {
        if (!(error instanceof OrbitAdminApiError) || error.status !== 428) throw error;
        const ok = await reauthenticate();
        if (!ok) throw new OrbitAdminApiError('Reauthentication was cancelled.', {code: 'reauthentication_cancelled'});
        return requestRoute(kind, id, body, options);
    }
}

function booleanChoice(value) {
    if (value === 'true') return true;
    if (value === 'false') return false;
    return undefined;
}

function exportSnapshot(id, payload) {
    const data = unwrap(payload);
    const source = data?.snapshot ?? data?.export?.snapshot ?? data?.payload ?? null;
    if (!source || typeof source !== 'object') return false;
    const blob = new Blob([JSON.stringify(source, null, 2)], {type: 'application/json'});
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `orbit-sos-${String(id).replace(/[^A-Za-z0-9._-]+/g, '-')}-export.json`;
    document.body.append(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    return true;
}

export function initSosShow(root) {
    const id = root.dataset.sosId;
    let incident = {};
    let loading = false;
    let refreshTimer = null;
    let sensitiveRevealTimer = null;

    const reveal = root.querySelector('[data-sensitive-reveal]');
    const accessHistory = root.querySelector('[data-access-history]');
    const clearButton = root.querySelector('[data-action="clear-sensitive"]');

    applyActionPermissions(root);

    const clearSensitiveReveal = () => {
        window.clearTimeout(sensitiveRevealTimer);
        sensitiveRevealTimer = null;
        if (reveal) {
            reveal.replaceChildren();
            reveal.hidden = true;
        }
        if (accessHistory) {
            accessHistory.replaceChildren();
            accessHistory.hidden = true;
        }
        if (clearButton) clearButton.hidden = true;
    };

    const armSensitiveExpiry = () => {
        window.clearTimeout(sensitiveRevealTimer);
        sensitiveRevealTimer = window.setTimeout(clearSensitiveReveal, SENSITIVE_REVEAL_MS);
        clearButton.hidden = false;
    };

    const render = (current) => {
        incident = current;
        const originator = originatorOf(current);
        const circle = circleOf(current);
        const control = controlOf(current);
        const assigned = assignedAdminOf(current);
        const activated = activationTime(current);
        const status = statusOf(current);
        const operational = operationalStatusOf(current);
        const userName = originator?.name ?? originator?.display_name ?? current?.user_name ?? (originator?.id ?? current?.user_id ? `User ${originator?.id ?? current?.user_id}` : 'User');
        const circleName = circle?.name ?? current?.circle_name ?? (circle?.id ?? current?.circle_id ? `Circle ${maskIdentifier(circle?.id ?? current?.circle_id)}` : 'Circle');
        const assignment = assigned?.name ?? assigned?.display_name ?? (assignedAdminId(current) ? `Admin ${maskIdentifier(assignedAdminId(current))}` : 'Unassigned');

        root.querySelector('[data-sos-title]').textContent = `SOS · ${maskIdentifier(incidentId(current), 8, 5)}`;
        root.querySelector('[data-sos-heading]').textContent = `${userName} · ${circleName}`;
        root.querySelector('[data-sos-id-label]').textContent = incidentId(current);
        root.querySelector('[data-sos-subtitle]').textContent = `${elapsedLabel(activated)} since activation · ${assignment}`;
        root.querySelector('[data-sos-status]').innerHTML = badge(status);
        root.querySelector('[data-sos-operational-status]').innerHTML = badge(operational);
        root.querySelector('[data-sos-overview]').innerHTML = kv([
            ['Originator', userName],
            ['User ID', originator?.id ?? current?.user_id],
            ['Circle', circleName],
            ['Circle ID', circle?.id ?? current?.circle_id],
            ['Activated', fmtDate(activated)],
            ['Elapsed', elapsedLabel(activated)],
            ['Consumer status', status],
            ['Operational status', operational],
            ['Assigned operator', assignment],
            ['Escalation stage', `Stage ${stageOf(current)}`],
            ['Resolved', fmtDate(current?.resolved_at)],
            ['Resolution', control?.resolution ?? current?.operational_resolution ?? current?.resolution_reason],
        ]);
        root.querySelector('[data-sos-stage]').innerHTML = renderStage(current);
        root.querySelector('[data-sos-escalations]').innerHTML = safeEscalations(current);
        root.querySelector('[data-sos-delivery]').innerHTML = compactKv(deliveryHealth(current));
        root.querySelector('[data-sos-responders]').innerHTML = safeResponders(current);
        root.querySelector('[data-sos-signal-health]').innerHTML = compactKv(signalHealth(current));
        root.querySelector('[data-sos-classification]').innerHTML = classificationHtml(current);
        root.querySelector('[data-sos-notes]').innerHTML = safeNotes(current);
        root.querySelector('[data-action="close"]').hidden = String(operational).toLowerCase() === 'closed';
    };

    const load = async ({background = false} = {}) => {
        if (loading && background) return;
        loading = true;
        if (!background) {
            root.querySelector('[data-loading]').hidden = false;
            root.querySelector('[data-error]').hidden = true;
            root.querySelector('[data-sos-content]').hidden = true;
        }

        try {
            const payload = await requestRoute('detail', id);
            render(sosDetail(payload));
            root.querySelector('[data-loading]').hidden = true;
            root.querySelector('[data-sos-content]').hidden = false;
        } catch (error) {
            if (!background) {
                root.querySelector('[data-loading]').hidden = true;
                root.querySelector('[data-error]').hidden = false;
                root.querySelector('[data-error-message]').textContent = errorText(error);
            } else {
                toast(`SOS background refresh failed. ${errorText(error)}`, 'error');
            }
        } finally {
            loading = false;
        }
    };

    const mutate = async (button, kind, body, success) => {
        setBusy(button, true);
        try {
            await requestWithReauthentication(kind, id, body);
            toast(success, 'success');
            await load({background: true});
        } catch (error) {
            if (error?.code !== 'reauthentication_cancelled') toast(errorText(error), 'error');
        } finally {
            setBusy(button, false);
        }
    };

    root.querySelector('[data-retry]').addEventListener('click', () => load());
    clearButton.addEventListener('click', clearSensitiveReveal);

    root.querySelector('[data-action="add-note"]').addEventListener('click', async (event) => {
        const button = event.currentTarget;
        const values = await askForm({title: 'Add internal SOS note', message: 'This note is private to authorized administrators and is never added to the consumer SOS record.', confirm: 'Add note', fields: [{name: 'body', label: 'Internal note', type: 'textarea', rows: 4, maxLength: 1000}]});
        if (!values) return;
        await mutate(button, 'notes', {body: values.body, note: values.body, content: values.body}, 'Internal SOS note added.');
    });

    root.querySelector('[data-action="assign"]').addEventListener('click', async (event) => {
        const button = event.currentTarget;
        const values = await askForm({title: 'Assign Safety Operator', message: 'Enter an active administrator ID. The backend will verify that the administrator is operationally eligible and has SOS management permission.', confirm: 'Assign', fields: [{name: 'admin_id', label: 'Administrator ID', type: 'text', value: assignedAdminId(incident) ?? '', maxLength: 64}, {name: 'reason', label: 'Assignment reason', type: 'textarea', rows: 3, maxLength: 500}]});
        if (!values) return;
        await mutate(button, 'assignment', {assigned_admin_id: values.admin_id, assignee_admin_id: values.admin_id, admin_id: values.admin_id, reason: values.reason}, 'SOS incident assignment updated.');
    });

    root.querySelector('[data-action="classify"]').addEventListener('click', async (event) => {
        const button = event.currentTarget;
        const flags = flagsOf(incident);
        const options = [{value: '', label: 'Keep current'}, {value: 'true', label: 'Yes'}, {value: 'false', label: 'No'}];
        const values = await askForm({title: 'Classify SOS incident', message: 'Classifications are internal operational signals. Every change is reason-audited by the backend.', confirm: 'Save classification', fields: [
            {name: 'false_alarm', label: `False alarm · currently ${flags.falseAlarm ? 'Yes' : 'No'}`, type: 'select', required: false, options},
            {name: 'technical_failure', label: `Technical failure · currently ${flags.technicalFailure ? 'Yes' : 'No'}`, type: 'select', required: false, options},
            {name: 'abuse_flag', label: `Abuse flag · currently ${flags.abuseFlag ? 'Yes' : 'No'}`, type: 'select', required: false, options},
            {name: 'internal_escalation', label: `Internal escalation · currently ${flags.internalEscalation ? 'Yes' : 'No'}`, type: 'select', required: false, options},
            {name: 'reason', label: 'Reason', type: 'textarea', rows: 3, maxLength: 500},
        ]});
        if (!values) return;
        const body = {reason: values.reason};
        for (const key of ['false_alarm', 'technical_failure', 'abuse_flag', 'internal_escalation']) {
            const choice = booleanChoice(values[key]);
            if (choice !== undefined) body[key] = choice;
        }
        if (body.internal_escalation !== undefined) body.internally_escalated = body.internal_escalation;
        await mutate(button, 'classification', body, 'SOS classification updated.');
    });

    root.querySelector('[data-action="close"]').addEventListener('click', async (event) => {
        const button = event.currentTarget;
        const values = await askForm({title: 'Close SOS operationally', message: 'Operational closure does not silently resolve the consumer SOS event. Record the operational resolution and audit reason.', confirm: 'Close incident', danger: true, fields: [{name: 'resolution', label: 'Operational resolution', type: 'textarea', rows: 4, maxLength: 1000}, {name: 'reason', label: 'Closure reason', type: 'textarea', rows: 3, maxLength: 500}]});
        if (!values) return;
        await mutate(button, 'closure', closurePayload(values), 'SOS incident closed operationally.');
    });

    root.querySelector('[data-action="export"]').addEventListener('click', async (event) => {
        const button = event.currentTarget;
        const values = await askForm({title: 'Generate SOS export', message: 'The backend produces a privacy-preserving incident snapshot. This operation is permissioned, reason-coded and may require recent reauthentication.', confirm: 'Generate export', fields: [{name: 'reason', label: 'Export reason', type: 'textarea', rows: 3, maxLength: 500}]});
        if (!values) return;
        setBusy(button, true);
        try {
            const payload = await requestWithReauthentication('export', id, {reason: values.reason, format: 'json'});
            const data = unwrap(payload);
            const exported = exportSnapshot(id, payload);
            const status = root.querySelector('[data-export-status]');
            status.hidden = false;
            status.textContent = exported ? 'Authorized privacy-safe snapshot downloaded.' : `Export ${data?.id ?? data?.export?.id ?? ''} ${data?.status ?? data?.export?.status ?? 'created'}.`;
            toast(exported ? 'SOS export generated and downloaded.' : 'SOS export generated.', 'success');
        } catch (error) {
            if (error?.code !== 'reauthentication_cancelled') toast(errorText(error), 'error');
        } finally {
            setBusy(button, false);
        }
    });

    root.querySelector('[data-action="reveal-location"]').addEventListener('click', async (event) => {
        const button = event.currentTarget;
        const values = await askForm({title: 'Reveal precise SOS location', message: 'Exceptional precise-location access is separately permissioned, reason-coded and immutably audited. Revealed values are automatically cleared from this page after 60 seconds.', eyebrow: 'Sensitive access', confirm: 'Reveal for 60 seconds', danger: true, fields: [{name: 'reason', label: 'Safety purpose / reason', type: 'textarea', rows: 3, maxLength: 500}]});
        if (!values) return;
        setBusy(button, true);
        try {
            const payload = await requestWithReauthentication('location', id, {reason: values.reason, purpose: 'safety_incident_review', resource: 'location', access_type: 'location'});
            const data = sosDetail(payload);
            const location = data?.location ?? data?.precise_location ?? data;
            const latitude = location?.latitude ?? location?.lat;
            const longitude = location?.longitude ?? location?.lng ?? location?.lon;
            reveal.innerHTML = `<div class="orbit-sensitive-reveal__head"><div><strong>Precise location revealed</strong><small>Automatically clears in 60 seconds</small></div><span>Audited</span></div>${kv([['Latitude', latitude], ['Longitude', longitude], ['Accuracy', location?.accuracy_m !== undefined ? `${location.accuracy_m} m` : null], ['Recorded', fmtDate(location?.recorded_at ?? location?.updated_at ?? location?.last_location_at)]])}`;
            reveal.hidden = false;
            accessHistory.hidden = true;
            armSensitiveExpiry();
        } catch (error) {
            if (error?.code !== 'reauthentication_cancelled') toast(errorText(error), 'error');
        } finally {
            setBusy(button, false);
        }
    });

    root.querySelector('[data-action="reveal-recording"]').addEventListener('click', async (event) => {
        const button = event.currentTarget;
        const values = await askForm({title: 'Reveal encrypted recording reference', message: 'Orbit never provides decryption material here. Only the backend-authorized opaque encrypted reference may be revealed and it will be cleared from this page after 60 seconds.', eyebrow: 'Sensitive access', confirm: 'Reveal for 60 seconds', danger: true, fields: [{name: 'reason', label: 'Safety purpose / reason', type: 'textarea', rows: 3, maxLength: 500}]});
        if (!values) return;
        setBusy(button, true);
        try {
            const payload = await requestWithReauthentication('recording', id, {reason: values.reason, purpose: 'safety_incident_review', resource: 'recording', access_type: 'recording'});
            const data = sosDetail(payload);
            const recording = data?.recording ?? data?.encrypted_recording ?? data;
            const reference = recording?.recording_ref ?? recording?.reference ?? recording?.ref ?? null;
            reveal.innerHTML = `<div class="orbit-sensitive-reveal__head"><div><strong>Encrypted recording reference revealed</strong><small>No plaintext audio or decryption material is exposed</small></div><span>Audited</span></div>${kv([['Opaque reference', reference], ['Expires', fmtDate(recording?.recording_expires_at ?? recording?.expires_at)], ['Encryption', 'Ciphertext reference only'], ['Decryption keys', 'Never exposed']])}`;
            reveal.hidden = false;
            accessHistory.hidden = true;
            armSensitiveExpiry();
        } catch (error) {
            if (error?.code !== 'reauthentication_cancelled') toast(errorText(error), 'error');
        } finally {
            setBusy(button, false);
        }
    });

    root.querySelector('[data-action="access-history"]').addEventListener('click', async (event) => {
        const button = event.currentTarget;
        setBusy(button, true);
        try {
            const payload = await requestRoute('accessHistory', id);
            const candidates = [payload?.data, payload?.data?.data, payload?.data?.items, payload?.items];
            const rows = candidates.find(Array.isArray) ?? [];
            accessHistory.innerHTML = rows.length ? `<div class="orbit-access-history__head"><strong>Sensitive access history</strong><small>Immutable backend audit records</small></div><div class="orbit-subtable"><table><thead><tr><th>Administrator</th><th>Access</th><th>Purpose / reason</th><th>Time</th><th>Request ID</th></tr></thead><tbody>${rows.map((row) => {
                const actor = row?.admin ?? row?.actor ?? {};
                const name = actor?.name ?? actor?.display_name ?? (row?.admin_id ? `Admin ${maskIdentifier(row.admin_id)}` : 'Administrator');
                return `<tr><td>${escapeHtml(name)}</td><td>${escapeHtml(row?.access_type ?? row?.resource ?? row?.kind ?? 'sensitive access')}</td><td>${escapeHtml(row?.reason ?? row?.purpose ?? '—')}</td><td>${escapeHtml(fmtDate(row?.accessed_at ?? row?.created_at ?? row?.occurred_at))}</td><td>${escapeHtml(maskIdentifier(row?.request_id ?? ''))}</td></tr>`;
            }).join('')}</tbody></table></div>` : '<p class="orbit-muted">No sensitive access records were returned.</p>';
            accessHistory.hidden = false;
            reveal.hidden = true;
            armSensitiveExpiry();
        } catch (error) {
            toast(errorText(error), 'error');
        } finally {
            setBusy(button, false);
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState !== 'visible') {
            clearSensitiveReveal();
            return;
        }
        load({background: true});
    });
    window.addEventListener('pagehide', clearSensitiveReveal, {once: true});
    refreshTimer = window.setInterval(() => {
        if (document.visibilityState === 'visible') load({background: true});
    }, DETAIL_REFRESH_MS);
    window.addEventListener('pagehide', () => window.clearInterval(refreshTimer), {once: true});

    load();
}
