import '../../css/admin-console-m5.css';
import { supportConfig, supportRoutes } from './support-routes.generated.js';

const root = document.querySelector('[data-orbit-view^="support-"]');

if (root) {
    const $ = (selector, scope = root) => scope.querySelector(selector);
    const text = (value, fallback = '--') => value === null || value === undefined || value === '' ? fallback : String(value);
    const first = (value, paths, fallback = null) => {
        for (const path of paths) {
            const result = path.split('.').reduce((current, key) => current && typeof current === 'object' ? current[key] : undefined, value);
            if (result !== undefined && result !== null && result !== '') return result;
        }
        return fallback;
    };
    const list = (value, paths) => {
        const found = first(value, paths, []);
        return Array.isArray(found) ? found : [];
    };
    const titleCase = (value) => text(value).replaceAll('_', ' ').replaceAll('-', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
    const formatDate = (value) => {
        if (!value) return '--';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? String(value) : new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
    };

    function resolveToken() {
        for (const candidate of supportConfig.storageCandidates || []) {
            try {
                const storage = candidate.storage === 'localStorage' ? window.localStorage : window.sessionStorage;
                const value = storage.getItem(candidate.key);
                if (value && value.length > 20) return value;
            } catch (_) {
                // Storage can be unavailable in hardened browser contexts.
            }
        }
        return null;
    }

    function routeUrl(route, params = {}) {
        if (!route?.uri) throw new Error('This support operation is not registered by the backend.');
        let uri = route.uri.startsWith('/') ? route.uri : `/${route.uri}`;
        Object.entries(params).forEach(([key, value]) => {
            uri = uri.replace(`{${key}}`, encodeURIComponent(String(value)));
        });
        if (/\{[^}]+\}/.test(uri)) throw new Error('A required support route parameter is missing.');
        return uri;
    }

    async function api(route, { params = {}, query = {}, body = undefined } = {}) {
        const url = new URL(routeUrl(route, params), window.location.origin);
        Object.entries(query).forEach(([key, value]) => {
            if (value !== '' && value !== null && value !== undefined && value !== false) url.searchParams.set(key, String(value));
        });
        const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        const token = resolveToken();
        if (token) headers.Authorization = `Bearer ${token}`;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        if (csrf) headers['X-CSRF-TOKEN'] = csrf;
        const options = { method: route.method || 'GET', credentials: 'same-origin', headers };
        if (body !== undefined) {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }
        const response = await fetch(url, options);
        const requestId = response.headers.get('X-Request-ID') || response.headers.get('X-Request-Id');
        let payload = {};
        try { payload = await response.json(); } catch (_) { /* an empty 204 response is valid */ }
        if (!response.ok) {
            const validation = payload?.errors ? Object.values(payload.errors).flat().join(' ') : '';
            const message = payload?.message || payload?.error?.message || payload?.error || validation || `Request failed (${response.status}).`;
            const suffix = requestId ? ` Request ID: ${requestId}` : '';
            const error = new Error(`${message}${suffix}`);
            error.status = response.status;
            throw error;
        }
        return payload;
    }

    function setLoading(active) {
        const loading = $('[data-m5-loading]');
        const content = $('[data-m5-content]');
        const error = $('[data-m5-error]');
        if (loading) loading.hidden = !active;
        if (active && content) content.hidden = true;
        if (active && error) error.hidden = true;
    }

    function showError(error) {
        setLoading(false);
        const panel = $('[data-m5-error]');
        if (!panel) return;
        panel.hidden = false;
        const message = $('[data-m5-error-message]');
        if (message) message.textContent = error?.message || 'The operation failed.';
    }

    function showContent() {
        setLoading(false);
        const error = $('[data-m5-error]');
        if (error) error.hidden = true;
        const content = $('[data-m5-content]');
        if (content) content.hidden = false;
    }

    function toast(message) {
        const region = document.querySelector('[data-orbit-toasts]');
        if (!region) return;
        const node = document.createElement('div');
        node.className = 'orbit-m5-toast';
        node.textContent = message;
        region.append(node);
        window.setTimeout(() => node.remove(), 3600);
    }

    function valueTone(value) {
        const normalized = String(value || '').toLowerCase();
        if (['critical', 'urgent', 'high', 'escalated', 'overdue', 'breached'].includes(normalized)) return 'danger';
        if (['new', 'open', 'pending', 'normal', 'medium', 'waiting'].includes(normalized)) return 'warning';
        if (['resolved', 'closed', 'complete', 'completed', 'low'].includes(normalized)) return 'success';
        return 'info';
    }

    function badge(value, tone = '') {
        const span = document.createElement('span');
        span.className = `orbit-m5-badge orbit-m5-badge--${tone || valueTone(value)}`;
        span.textContent = titleCase(value);
        return span;
    }

    function renderKv(container, entries) {
        if (!container) return;
        container.replaceChildren();
        entries.forEach(([label, value]) => {
            const wrapper = document.createElement('div');
            const dt = document.createElement('dt');
            const dd = document.createElement('dd');
            dt.textContent = label;
            dd.textContent = text(value);
            wrapper.append(dt, dd);
            container.append(wrapper);
        });
    }

    function renderStack(container, items, emptyCopy, map) {
        if (!container) return;
        container.replaceChildren();
        if (!items.length) {
            const empty = document.createElement('p');
            empty.className = 'orbit-m5-muted';
            empty.textContent = emptyCopy;
            container.append(empty);
            return;
        }
        items.forEach((item) => container.append(map(item)));
    }

    function card(title, body = '', meta = '', direction = '') {
        const node = document.createElement('div');
        node.className = 'orbit-m5-card';
        if (direction) node.dataset.direction = direction;
        const head = document.createElement('div');
        head.className = 'orbit-m5-card__head';
        const strong = document.createElement('strong');
        strong.textContent = text(title);
        head.append(strong);
        node.append(head);
        if (body !== '') {
            const p = document.createElement('p');
            p.textContent = text(body, '');
            node.append(p);
        }
        if (meta) {
            const small = document.createElement('small');
            small.textContent = meta;
            node.append(small);
        }
        return node;
    }

    function timelineItem(title, body, meta = '') {
        const node = document.createElement('div');
        node.className = 'orbit-m5-timeline-item';
        const strong = document.createElement('strong');
        strong.textContent = text(title);
        const p = document.createElement('p');
        p.textContent = text(body, '');
        node.append(strong, p);
        if (meta) {
            const small = document.createElement('small');
            small.textContent = meta;
            node.append(small);
        }
        return node;
    }

    function pagination(meta, onPage) {
        const box = $('[data-m5-pagination]');
        if (!box) return;
        box.replaceChildren();
        const current = Number(first(meta, ['current_page', 'page'], 1));
        const last = Number(first(meta, ['last_page', 'pages'], current));
        if (last <= 1) return;
        const prev = document.createElement('button');
        prev.type = 'button';
        prev.className = 'orbit-button orbit-button--quiet';
        prev.textContent = 'Previous';
        prev.disabled = current <= 1;
        prev.addEventListener('click', () => onPage(current - 1));
        const label = document.createElement('span');
        label.textContent = `Page ${current} of ${last}`;
        const next = document.createElement('button');
        next.type = 'button';
        next.className = 'orbit-button orbit-button--quiet';
        next.textContent = 'Next';
        next.disabled = current >= last;
        next.addEventListener('click', () => onPage(current + 1));
        box.append(prev, label, next);
    }

    function humanLabel(name) {
        const special = {
            user_id: 'User ID', admin_user_id: 'Administrator ID', assignee_admin_id: 'Administrator ID', assigned_admin_id: 'Administrator ID',
            resource_id: 'Related record ID', resource_type: 'Related record type', internal_note: 'Internal note', sla_due_at: 'SLA due at',
        };
        return special[name] || titleCase(name);
    }

    function fieldKind(name) {
        const lowered = name.toLowerCase();
        if (/(message|body|content|note|reason|description|resolution|reply)/.test(lowered)) return 'textarea';
        if (/(password)/.test(lowered)) return 'password';
        if (/(email)/.test(lowered)) return 'email';
        if (/(due_at|scheduled_at|expires_at|date|_at$)/.test(lowered)) return 'datetime-local';
        if (/(count|minutes|hours|days|amount|score)/.test(lowered)) return 'number';
        return 'text';
    }

    function actionFields(key) {
        return Array.isArray(supportConfig.actionFields?.[key]) ? supportConfig.actionFields[key] : [];
    }

    function descriptorFor(field) {
        const name = typeof field === 'string' ? field : field.name;
        const required = typeof field === 'object' && field.required === true;
        const descriptor = { name, label: humanLabel(name), type: fieldKind(name), required };
        if (/assignee|admin.*id|assigned_admin/.test(name)) descriptor.placeholder = 'Administrator ID';
        if (name === 'user_id') descriptor.placeholder = 'User ID';
        if (name === 'resource_type') descriptor.placeholder = 'report, subscription, privacy_request...';
        if (name === 'resource_id') descriptor.placeholder = 'Related record ID';
        if (name === 'status') descriptor.placeholder = 'Backend-supported status';
        if (name === 'priority') descriptor.placeholder = 'Backend-supported priority';
        return descriptor;
    }

    function dialog({ title, description, fields, confirm = 'Confirm', danger = false, onSubmit }) {
        const modal = document.createElement('dialog');
        modal.className = 'orbit-m5-dialog';
        const form = document.createElement('form');
        form.method = 'dialog';
        form.className = 'orbit-m5-dialog__card';
        const header = document.createElement('div');
        header.className = 'orbit-m5-dialog__header';
        const heading = document.createElement('div');
        const eyebrow = document.createElement('p');
        eyebrow.className = 'orbit-eyebrow';
        eyebrow.textContent = 'Support action';
        const h2 = document.createElement('h2');
        h2.textContent = title;
        heading.append(eyebrow, h2);
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'orbit-m5-dialog__close';
        close.setAttribute('aria-label', 'Close');
        close.textContent = 'x';
        close.addEventListener('click', () => modal.close());
        header.append(heading, close);
        form.append(header);
        if (description) {
            const copy = document.createElement('p');
            copy.className = 'orbit-m5-dialog__copy';
            copy.textContent = description;
            form.append(copy);
        }
        const fieldset = document.createElement('div');
        fieldset.className = 'orbit-m5-dialog__fields';
        fields.forEach((field) => {
            const label = document.createElement('label');
            label.textContent = field.label;
            const input = field.type === 'textarea' ? document.createElement('textarea') : document.createElement('input');
            if (input instanceof HTMLInputElement) input.type = field.type || 'text';
            input.name = field.name;
            input.required = field.required === true;
            if (field.placeholder) input.placeholder = field.placeholder;
            if (field.value !== undefined && field.value !== null) input.value = String(field.value);
            label.append(input);
            fieldset.append(label);
        });
        form.append(fieldset);
        const feedback = document.createElement('div');
        feedback.className = 'orbit-m5-dialog__feedback';
        feedback.hidden = true;
        form.append(feedback);
        const footer = document.createElement('div');
        footer.className = 'orbit-m5-dialog__footer';
        const cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'orbit-button orbit-button--quiet';
        cancel.textContent = 'Cancel';
        cancel.addEventListener('click', () => modal.close());
        const submit = document.createElement('button');
        submit.type = 'button';
        submit.className = danger ? 'orbit-button orbit-button--danger' : 'orbit-button';
        submit.textContent = confirm;
        submit.addEventListener('click', async () => {
            if (!form.reportValidity()) return;
            submit.disabled = true;
            feedback.hidden = true;
            const values = Object.fromEntries(new FormData(form).entries());
            Object.keys(values).forEach((key) => values[key] === '' && delete values[key]);
            try {
                await onSubmit(values);
                modal.close();
            } catch (error) {
                feedback.textContent = error?.message || 'The support action failed.';
                feedback.hidden = false;
            } finally {
                submit.disabled = false;
            }
        });
        footer.append(cancel, submit);
        form.append(footer);
        modal.append(form);
        document.body.append(modal);
        modal.addEventListener('close', () => modal.remove(), { once: true });
        modal.showModal();
        const focusable = modal.querySelector('input, textarea, select');
        focusable?.focus();
        return modal;
    }

    function bindCommon(reload) {
        $('[data-m5-refresh]')?.addEventListener('click', reload);
        $('[data-m5-retry]')?.addEventListener('click', reload);
    }

    function ticketId(ticket) {
        return text(first(ticket, ['id', 'ticket_id', 'uuid']));
    }

    function ticketUser(ticket) {
        return text(first(ticket, ['user.name', 'user.display_name', 'user.email_masked', 'user_id']));
    }

    async function bootIndex() {
        let page = 1;
        let debounce = null;
        const form = $('[data-m5-ticket-filters]');
        const create = $('[data-m5-create-ticket]');

        const openCreate = () => {
            const fields = actionFields('ticketCreate').map(descriptorFor);
            if (!fields.length || !supportRoutes.ticketCreate) return;
            dialog({
                title: 'Open support case',
                description: 'Create an administrator-initiated support case using only fields accepted by the installed backend contract.',
                fields,
                confirm: 'Create case',
                onSubmit: async (body) => {
                    const payload = await api(supportRoutes.ticketCreate, { body });
                    toast('Support case created.');
                    const created = first(payload, ['data', 'ticket'], null);
                    const id = created ? ticketId(created) : null;
                    if (id && id !== '--') window.location.href = `${window.location.pathname.replace(/\/$/, '')}/${encodeURIComponent(id)}`;
                    else await load();
                },
            });
        };

        if (create && supportRoutes.ticketCreate && actionFields('ticketCreate').length) {
            create.hidden = false;
            create.addEventListener('click', openCreate);
        }

        const load = async () => {
            setLoading(true);
            try {
                const query = form ? Object.fromEntries(new FormData(form).entries()) : {};
                query.page = page;
                const payload = await api(supportRoutes.ticketList, { query });
                const rows = first(payload, ['data.items', 'data.data', 'items', 'data'], []);
                const items = Array.isArray(rows) ? rows : [];
                const meta = first(payload, ['data.meta', 'data.pagination', 'meta', 'pagination'], {});
                const tbody = $('[data-m5-ticket-rows]');
                tbody.replaceChildren();
                let slaBreached = 0;
                let unassigned = 0;
                items.forEach((ticket) => {
                    const tr = document.createElement('tr');
                    tr.className = 'orbit-m5-row';
                    tr.tabIndex = 0;
                    const id = ticketId(ticket);
                    const status = first(ticket, ['status', 'workflow_status'], 'unknown');
                    const priority = first(ticket, ['priority', 'severity'], 'normal');
                    const assignee = first(ticket, ['assigned_admin.name', 'assignee.name', 'assigned_admin_id', 'assignee_admin_id'], null);
                    const due = first(ticket, ['sla_due_at', 'sla.deadline', 'due_at'], null);
                    const breached = Boolean(first(ticket, ['sla_breached', 'is_sla_breached', 'sla.breached'], false));
                    if (breached) slaBreached++;
                    if (!assignee) unassigned++;
                    const cells = [
                        { value: id, secondary: first(ticket, ['subject', 'title'], '') },
                        { value: ticketUser(ticket) },
                        { value: titleCase(first(ticket, ['category', 'type'], '--')) },
                        { node: badge(priority) },
                        { node: badge(status) },
                        { value: text(assignee, 'Unassigned') },
                        { value: breached ? 'Breached' : formatDate(due) },
                        { value: formatDate(first(ticket, ['updated_at', 'last_activity_at', 'created_at'])) },
                    ];
                    cells.forEach((cell, index) => {
                        const td = document.createElement('td');
                        if (cell.node) td.append(cell.node);
                        else {
                            const primary = document.createElement(index === 0 ? 'strong' : 'span');
                            if (index === 0) primary.className = 'orbit-m5-ticket-id';
                            primary.textContent = cell.value;
                            td.append(primary);
                            if (cell.secondary) {
                                const secondary = document.createElement('small');
                                secondary.className = 'orbit-m5-secondary';
                                secondary.textContent = text(cell.secondary, '');
                                td.append(secondary);
                            }
                        }
                        tr.append(td);
                    });
                    const open = () => { window.location.href = `${window.location.pathname.replace(/\/$/, '')}/${encodeURIComponent(id)}`; };
                    tr.addEventListener('click', open);
                    tr.addEventListener('keydown', (event) => { if (event.key === 'Enter') open(); });
                    tbody.append(tr);
                });
                const total = Number(first(meta, ['total'], first(payload, ['data.total', 'total'], items.length)));
                $('[data-m5-count]').textContent = `${total} total`;
                $('[data-m5-total]').textContent = String(total);
                $('[data-m5-sla-count]').textContent = String(slaBreached);
                $('[data-m5-unassigned-count]').textContent = String(unassigned);
                $('[data-m5-empty]').hidden = items.length !== 0;
                $('[data-m5-table-wrap]').hidden = items.length === 0;
                pagination(meta, (next) => { page = next; load(); });
                showContent();
            } catch (error) {
                showError(error);
            }
        };

        form?.addEventListener('submit', (event) => { event.preventDefault(); page = 1; load(); });
        form?.querySelector('input[name="search"]')?.addEventListener('input', () => {
            window.clearTimeout(debounce);
            debounce = window.setTimeout(() => { page = 1; load(); }, 350);
        });
        bindCommon(load);
        await load();
    }

    function showAction(name, routeKey, handler) {
        const button = $(`[data-m5-action="${name}"]`);
        if (!button || !supportRoutes[routeKey] || actionFields(routeKey).length === 0) return;
        button.hidden = false;
        button.addEventListener('click', handler);
    }

    async function bootShow() {
        const id = root.dataset.ticketId;
        let currentTicket = null;

        const load = async () => {
            setLoading(true);
            try {
                const payload = await api(supportRoutes.ticketShow, { params: { ticketId: id } });
                const detail = first(payload, ['data'], payload);
                const ticket = first(detail, ['ticket'], first(payload, ['ticket'], detail));
                const detailList = (paths) => { const nested = list(ticket, paths); return nested.length ? nested : list(detail, paths); };
                currentTicket = ticket;
                const status = first(ticket, ['status', 'workflow_status'], 'unknown');
                const priority = first(ticket, ['priority', 'severity'], 'normal');
                $('[data-m5-ticket-heading]').textContent = `Ticket ${ticketId(ticket)}`;
                $('[data-m5-ticket-subtitle]').textContent = text(first(ticket, ['subject', 'title', 'category'], 'Customer support case'));
                $('[data-m5-status]').replaceChildren(badge(status));
                $('[data-m5-priority]').replaceChildren(badge(priority));
                renderKv($('[data-m5-overview]'), [
                    ['Ticket ID', ticketId(ticket)],
                    ['User', ticketUser(ticket)],
                    ['User ID', first(ticket, ['user.id', 'user_id'])],
                    ['Category', titleCase(first(ticket, ['category', 'type']))],
                    ['Priority', titleCase(priority)],
                    ['Status', titleCase(status)],
                    ['Assignee', first(ticket, ['assigned_admin.name', 'assignee.name', 'assigned_admin_id', 'assignee_admin_id'], 'Unassigned')],
                    ['SLA due', formatDate(first(ticket, ['sla_due_at', 'sla.deadline', 'due_at']))],
                    ['Created', formatDate(first(ticket, ['created_at', 'opened_at']))],
                    ['Updated', formatDate(first(ticket, ['updated_at', 'last_activity_at']))],
                ]);

                const conversation = detailList(['conversation', 'messages', 'replies', 'communications']);
                renderStack($('[data-m5-conversation]'), conversation, 'No conversation entries are available.', (item) => {
                    const actor = first(item, ['actor.name', 'author.name', 'admin.name', 'user.name', 'actor_type'], 'Message');
                    const body = first(item, ['message', 'body', 'content', 'text'], '');
                    const kind = String(first(item, ['direction', 'visibility', 'type'], '')).toLowerCase();
                    const direction = kind.includes('consumer') || kind.includes('inbound') ? 'consumer' : 'external';
                    return card(actor, body, formatDate(first(item, ['created_at', 'sent_at', 'occurred_at'])), direction);
                });

                const notes = detailList(['internal_notes', 'notes', 'case_notes']);
                renderStack($('[data-m5-notes]'), notes, 'No internal notes.', (item) => card(
                    first(item, ['admin.name', 'author.name', 'created_by.name', 'admin_user_id'], 'Internal note'),
                    first(item, ['note', 'body', 'content', 'text'], ''),
                    formatDate(first(item, ['created_at', 'occurred_at'])),
                ));

                const links = detailList(['resource_links', 'links', 'related_resources', 'resources']);
                renderStack($('[data-m5-links]'), links, 'No related records linked.', (item) => card(
                    titleCase(first(item, ['resource_type', 'type', 'kind'], 'Related record')),
                    text(first(item, ['resource_id', 'target_id', 'id'])),
                    formatDate(first(item, ['created_at', 'linked_at'])),
                ));

                const attachments = detailList(['attachments', 'files']);
                renderStack($('[data-m5-attachments]'), attachments, 'No attachment metadata is available.', (item) => card(
                    first(item, ['name', 'filename', 'type'], 'Attachment'),
                    first(item, ['content_type', 'mime_type', 'kind'], 'Metadata only'),
                    text(first(item, ['size_bytes', 'size'], '')),
                ));

                const contacts = detailList(['contact_history', 'contacts', 'user_contact_history']);
                renderStack($('[data-m5-contact-history]'), contacts, 'Contact history is not embedded in this ticket response.', (item) => card(
                    titleCase(first(item, ['type', 'kind', 'channel', 'action'], 'Contact')),
                    first(item, ['summary', 'subject', 'status', 'outcome'], ''),
                    formatDate(first(item, ['created_at', 'occurred_at', 'sent_at'])),
                ));

                const timeline = detailList(['timeline', 'events', 'history', 'status_history']);
                renderStack($('[data-m5-timeline]'), timeline, 'No case timeline events are available.', (item) => timelineItem(
                    titleCase(first(item, ['action', 'event', 'type', 'status'], 'Case event')),
                    first(item, ['summary', 'reason', 'description', 'note'], ''),
                    formatDate(first(item, ['created_at', 'occurred_at', 'at'])),
                ));
                showContent();
            } catch (error) {
                showError(error);
            }
        };

        const runAction = (routeKey, title, description, confirm, danger = false) => {
            const fields = actionFields(routeKey).map(descriptorFor);
            if (!supportRoutes[routeKey]) return;
            dialog({
                title,
                description,
                fields,
                confirm,
                danger,
                onSubmit: async (body) => {
                    await api(supportRoutes[routeKey], { params: { ticketId: id }, body });
                    toast(`${title} completed.`);
                    await load();
                },
            });
        };

        showAction('assign', 'ticketAssign', () => runAction('ticketAssign', 'Assign support case', 'Assignment eligibility remains enforced by the backend.', 'Assign'));
        showAction('reply', 'ticketReply', () => runAction('ticketReply', 'Reply to customer', 'This creates customer-visible communication. Internal notes are handled separately.', 'Send reply'));
        showAction('note', 'ticketNote', () => runAction('ticketNote', 'Add internal note', 'This note remains internal and is never consumer-visible unless intentionally converted to external communication.', 'Add note'));
        showAction('link', 'ticketLink', () => runAction('ticketLink', 'Link related record', 'Link an authorized support, billing, moderation, privacy or account record. The backend remains authoritative.', 'Link record'));
        showAction('update', 'ticketUpdate', () => runAction('ticketUpdate', 'Change support workflow', `Current status: ${text(first(currentTicket, ['status']))}. Current priority: ${text(first(currentTicket, ['priority']))}.`, 'Save changes'));
        showAction('escalate', 'ticketEscalate', () => runAction('ticketEscalate', 'Escalate support case', 'Escalation is audited and remains subject to backend authorization.', 'Escalate', true));
        showAction('resolve', 'ticketResolve', () => runAction('ticketResolve', 'Resolve support case', 'Resolution is final where enforced by the backend and requires any installed rationale fields.', 'Resolve'));
        bindCommon(load);
        await load();
    }

    if (root.dataset.orbitView === 'support-index') bootIndex();
    if (root.dataset.orbitView === 'support-show') bootShow();
}
