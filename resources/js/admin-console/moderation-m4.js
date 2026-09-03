import '../../css/admin-console-m4.css';
import { moderationConfig, moderationRoutes } from './moderation-routes.generated.js';

import { adminApiRequest } from './admin-api-client.js';
const root = document.querySelector('[data-orbit-view^="moderation-"]');
if (!root) {
    // M4 is loaded from the canonical admin entry but activates only on M4 pages.
} else {
    const $ = (selector, scope = root) => scope.querySelector(selector);
    const $$ = (selector, scope = root) => Array.from(scope.querySelectorAll(selector));
    const text = (value, fallback = '—') => value === null || value === undefined || value === '' ? fallback : String(value);
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
    const formatDate = (value) => {
        if (!value) return '—';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? String(value) : new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
    };
    const titleCase = (value) => text(value).replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

    async function api(route, options = {}) {
        if (!route?.uri) throw new Error('This moderation operation is not registered by the backend.');
        return adminApiRequest(route, options);
    }

    function setLoading(active) {
        const loading = $('[data-m4-loading]');
        const content = $('[data-m4-content]');
        const error = $('[data-m4-error]');
        if (loading) loading.hidden = !active;
        if (active && content) content.hidden = true;
        if (active && error) error.hidden = true;
    }
    function showError(error) {
        setLoading(false);
        const panel = $('[data-m4-error]');
        if (!panel) return;
        panel.hidden = false;
        const message = $('[data-m4-error-message]');
        if (message) message.textContent = error?.message || 'The operation failed.';
    }
    function showContent() {
        setLoading(false);
        const error = $('[data-m4-error]'); if (error) error.hidden = true;
        const content = $('[data-m4-content]'); if (content) content.hidden = false;
    }
    function toast(message) {
        const region = document.querySelector('[data-orbit-toasts]');
        if (!region) return;
        const node = document.createElement('div');
        node.className = 'orbit-m4-toast';
        node.textContent = message;
        region.append(node);
        window.setTimeout(() => node.remove(), 3500);
    }
    function badge(value, tone = '') {
        const span = document.createElement('span');
        span.className = `orbit-m4-badge ${tone ? `orbit-m4-badge--${tone}` : ''}`;
        span.textContent = titleCase(value);
        return span;
    }
    function valueTone(value) {
        const v = String(value || '').toLowerCase();
        if (['critical', 'high', 'escalated', 'suspended', 'second_review'].includes(v)) return 'danger';
        if (['new', 'pending', 'medium', 'under_review'].includes(v)) return 'warning';
        if (['closed', 'resolved', 'low', 'overturned'].includes(v)) return 'success';
        return '';
    }
    function renderKv(container, entries) {
        if (!container) return;
        container.replaceChildren();
        entries.forEach(([label, value]) => {
            const wrapper = document.createElement('div');
            const dt = document.createElement('dt'); dt.textContent = label;
            const dd = document.createElement('dd'); dd.textContent = text(value);
            wrapper.append(dt, dd); container.append(wrapper);
        });
    }
    function renderStack(container, items, emptyCopy, map) {
        if (!container) return;
        container.replaceChildren();
        if (!items.length) {
            const empty = document.createElement('p'); empty.className = 'orbit-m4-muted'; empty.textContent = emptyCopy; container.append(empty); return;
        }
        items.forEach((item) => container.append(map(item)));
    }
    function timelineItem(title, body, meta = '') {
        const item = document.createElement('div'); item.className = 'orbit-m4-timeline-item';
        const strong = document.createElement('strong'); strong.textContent = text(title);
        const p = document.createElement('p'); p.textContent = text(body, '');
        item.append(strong, p);
        if (meta) { const small = document.createElement('small'); small.textContent = meta; item.append(small); }
        return item;
    }
    function pagination(meta, onPage) {
        const box = $('[data-m4-pagination]'); if (!box) return;
        box.replaceChildren();
        const current = Number(first(meta, ['current_page', 'page'], 1));
        const last = Number(first(meta, ['last_page', 'pages'], current));
        if (last <= 1) return;
        const prev = document.createElement('button'); prev.type = 'button'; prev.className = 'orbit-button orbit-button--quiet'; prev.textContent = 'Previous'; prev.disabled = current <= 1; prev.addEventListener('click', () => onPage(current - 1));
        const label = document.createElement('span'); label.textContent = `Page ${current} of ${last}`;
        const next = document.createElement('button'); next.type = 'button'; next.className = 'orbit-button orbit-button--quiet'; next.textContent = 'Next'; next.disabled = current >= last; next.addEventListener('click', () => onPage(current + 1));
        box.append(prev, label, next);
    }

    function dialog({ title, description, fields, confirm = 'Confirm', danger = false, onSubmit }) {
        const modal = document.createElement('dialog'); modal.className = 'orbit-m4-dialog';
        const form = document.createElement('form'); form.method = 'dialog'; form.className = 'orbit-m4-dialog__card';
        const header = document.createElement('div'); header.className = 'orbit-m4-dialog__header';
        const heading = document.createElement('div'); const eyebrow = document.createElement('p'); eyebrow.className = 'orbit-eyebrow'; eyebrow.textContent = 'Moderation action'; const h2 = document.createElement('h2'); h2.textContent = title; heading.append(eyebrow, h2);
        const close = document.createElement('button'); close.type = 'button'; close.className = 'orbit-m4-dialog__close'; close.setAttribute('aria-label', 'Close'); close.textContent = '×'; close.addEventListener('click', () => modal.close()); header.append(heading, close);
        form.append(header);
        if (description) { const p = document.createElement('p'); p.className = 'orbit-m4-dialog__copy'; p.textContent = description; form.append(p); }
        const fieldset = document.createElement('div'); fieldset.className = 'orbit-m4-dialog__fields';
        fields.forEach((field) => {
            const label = document.createElement('label'); label.textContent = field.label;
            let input;
            if (field.type === 'select') {
                input = document.createElement('select'); (field.options || []).forEach((option) => { const o = document.createElement('option'); o.value = typeof option === 'string' ? option : option.value; o.textContent = typeof option === 'string' ? titleCase(option) : option.label; input.append(o); });
            } else if (field.type === 'textarea') { input = document.createElement('textarea'); input.rows = field.rows || 4; }
            else { input = document.createElement('input'); input.type = field.type || 'text'; }
            input.name = field.name; input.required = field.required !== false; if (field.placeholder) input.placeholder = field.placeholder; if (field.value !== undefined) input.value = field.value; label.append(input); fieldset.append(label);
        });
        form.append(fieldset);
        const feedback = document.createElement('p'); feedback.className = 'orbit-m4-dialog__feedback'; feedback.hidden = true; form.append(feedback);
        const footer = document.createElement('div'); footer.className = 'orbit-m4-dialog__footer';
        const cancel = document.createElement('button'); cancel.type = 'button'; cancel.className = 'orbit-button orbit-button--quiet'; cancel.textContent = 'Cancel'; cancel.addEventListener('click', () => modal.close());
        const submit = document.createElement('button'); submit.type = 'submit'; submit.className = `orbit-button ${danger ? 'orbit-button--danger' : ''}`; submit.textContent = confirm;
        footer.append(cancel, submit); form.append(footer); modal.append(form); document.body.append(modal);
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!form.reportValidity()) return;
            const data = Object.fromEntries(new FormData(form).entries());
            feedback.hidden = true; submit.disabled = true;
            try { await onSubmit(data); modal.close(); modal.remove(); }
            catch (error) { feedback.textContent = error.message; feedback.hidden = false; submit.disabled = false; }
        });
        modal.addEventListener('close', () => window.setTimeout(() => modal.remove(), 0), { once: true });
        modal.showModal();
        const firstInput = modal.querySelector('input,select,textarea'); if (firstInput) firstInput.focus();
    }

    async function reauthenticate(password) {
        if (!password || !moderationRoutes.reauthenticate) return;
        await api(moderationRoutes.reauthenticate, { body: { password } });
    }

    function bindCommon(load) {
        $('[data-m4-retry]')?.addEventListener('click', load);
        $('[data-m4-refresh]')?.addEventListener('click', load);
    }

    async function bootReports() {
        let page = 1;
        const form = $('[data-m4-report-filters]');
        const load = async () => {
            setLoading(true);
            try {
                const query = form ? Object.fromEntries(new FormData(form).entries()) : {}; query.page = page;
                const payload = await api(moderationRoutes.reportList, { query });
                const rows = first(payload, ['data.data', 'data', 'reports'], []); const meta = first(payload, ['data.meta', 'meta'], {});
                const tbody = $('[data-m4-report-rows]'); tbody.replaceChildren();
                const empty = $('[data-m4-empty]'); empty.hidden = rows.length !== 0;
                rows.forEach((report) => {
                    const tr = document.createElement('tr'); tr.tabIndex = 0; tr.className = 'orbit-m4-row';
                    const id = text(first(report, ['id', 'report_id'])); const target = first(report, ['target.label', 'target.name', 'target_id', 'target.id', 'subject_id']);
                    const values = [id, text(target), text(first(report, ['reason', 'category', 'report_reason'])), titleCase(first(report, ['priority'], 'normal')), titleCase(first(report, ['status', 'workflow_status'])), text(first(report, ['assigned_admin.name', 'assignee.name', 'assigned_admin_id', 'assignee_admin_id'])), formatDate(first(report, ['created_at', 'reported_at']))];
                    values.forEach((value, index) => { const td = document.createElement('td'); if (index === 3 || index === 4) td.append(badge(value, valueTone(value))); else td.textContent = value; tr.append(td); });
                    const open = () => { window.location.href = `${window.location.pathname.replace(/\/$/, '')}/reports/${encodeURIComponent(id)}`; }; tr.addEventListener('click', open); tr.addEventListener('keydown', (e) => { if (e.key === 'Enter') open(); }); tbody.append(tr);
                });
                $('[data-m4-count]').textContent = `${first(meta, ['total'], rows.length)} total`; pagination(meta, (next) => { page = next; load(); }); showContent();
            } catch (error) { showError(error); }
        };
        form?.addEventListener('submit', (e) => { e.preventDefault(); page = 1; load(); }); bindCommon(load); await load();
    }

    async function bootReport() {
        const reportId = root.dataset.reportId;
        const load = async () => {
            setLoading(true);
            try {
                const payload = await api(moderationRoutes.reportShow, { params: { reportId } }); const report = first(payload, ['data'], payload);
                $('[data-m4-title]').textContent = `Report ${text(first(report, ['id', 'report_id']))}`;
                const status = first(report, ['status', 'workflow_status'], 'unknown'); const statusBox = $('[data-m4-status]'); statusBox.replaceChildren(badge(status, valueTone(status)));
                renderKv($('[data-m4-overview]'), [['Report ID', first(report, ['id', 'report_id'])], ['Reporter', first(report, ['reporter.name', 'reporter.display_name', 'reporter_user_id'])], ['Target', first(report, ['target.label', 'target.name', 'target_id', 'target.id'])], ['Target type', first(report, ['target_type', 'target.type'])], ['Reason', first(report, ['reason', 'category'])], ['Priority', first(report, ['priority'])], ['Risk score', first(report, ['risk_score', 'risk.score'])], ['Assigned moderator', first(report, ['assigned_admin.name', 'assignee.name', 'assigned_admin_id'])], ['Received', formatDate(first(report, ['created_at', 'reported_at']))]]);
                const evidence = first(report, ['evidence', 'submitted_evidence', 'report_evidence'], null); const evidenceBox = $('[data-m4-evidence]'); evidenceBox.textContent = evidence ? (typeof evidence === 'string' ? evidence : JSON.stringify(evidence, null, 2)) : 'No reporter-submitted evidence is attached.';
                renderStack($('[data-m4-prior]'), list(report, ['prior_reports']), 'No prior reports.', (item) => timelineItem(`Report ${first(item, ['id'], '')}`, `${titleCase(first(item, ['status'], ''))} · ${text(first(item, ['reason', 'category']))}`, formatDate(first(item, ['created_at']))));
                const risk = first(report, ['risk', 'risk_profile'], null); $('[data-m4-risk]').textContent = risk ? `Score ${text(first(risk, ['score', 'risk_score']))} · ${titleCase(first(risk, ['level', 'risk_level']))}` : `Risk score: ${text(first(report, ['risk_score']))}`;
                renderStack($('[data-m4-notes]'), list(report, ['notes', 'internal_notes']), 'No internal notes.', (item) => timelineItem(first(item, ['author.name', 'admin.name'], 'Internal note'), first(item, ['note', 'body', 'content'], ''), formatDate(first(item, ['created_at']))));
                renderStack($('[data-m4-enforcements]'), list(report, ['enforcements', 'decisions']), 'No enforcement recorded.', (item) => timelineItem(titleCase(first(item, ['action', 'type'], 'Action')), first(item, ['reason', 'decision_reason', 'outcome'], ''), formatDate(first(item, ['created_at', 'applied_at']))));
                showContent();
            } catch (error) { showError(error); }
        };
        bindCommon(load);
        $('[data-m4-action="assign"]')?.addEventListener('click', () => dialog({ title: 'Assign report', description: 'Assign only to an active administrator with moderation review permission.', fields: [{ name: 'assigned_admin_id', label: 'Administrator ID', placeholder: 'Administrator ID' }, { name: 'reason', label: 'Reason / handoff note', type: 'textarea' }], confirm: 'Assign', onSubmit: async (data) => { await api(moderationRoutes.reportAssign, { params: { reportId }, body: data }); toast('Report assignment updated.'); await load(); } }));
        $('[data-m4-action="workflow"]')?.addEventListener('click', () => dialog({ title: 'Update workflow', description: 'Closed reports cannot be silently reopened. Every transition is reason-audited.', fields: [{ name: 'status', label: 'Status', type: 'select', options: ['triaged', 'assigned', 'under_review', 'actioned', 'escalated', 'closed'] }, { name: 'reason', label: 'Rationale', type: 'textarea' }], confirm: 'Update', onSubmit: async (data) => { await api(moderationRoutes.reportWorkflow, { params: { reportId }, body: data }); toast('Workflow updated.'); await load(); } }));
        $('[data-m4-action="note"]')?.addEventListener('click', () => dialog({ title: 'Add internal note', description: 'Internal moderation notes remain private and are not consumer-visible.', fields: [{ name: 'note', label: 'Note', type: 'textarea', rows: 5 }], confirm: 'Add note', onSubmit: async (data) => { await api(moderationRoutes.reportNote, { params: { reportId }, body: data }); toast('Internal note added.'); await load(); } }));
        $('[data-m4-action="enforce"]')?.addEventListener('click', () => dialog({ title: 'Apply enforcement', description: 'High-risk enforcement requires confirmation, a reason, recent reauthentication, permission checks, and an audit entry.', danger: true, fields: [{ name: 'action', label: 'Action', type: 'select', options: (moderationConfig.enforcementActions || ['suspend_user_temp']) }, { name: 'reason', label: 'Enforcement reason', type: 'textarea' }, { name: 'duration_minutes', label: 'Duration minutes (when applicable)', type: 'number', required: false, value: '60' }, { name: 'feature', label: 'Feature (when applicable)', required: false, placeholder: 'messages, moments, pings…' }, { name: '_password', label: 'Admin password for recent reauthentication', type: 'password' }], confirm: 'Confirm enforcement', onSubmit: async (data) => { const password = data._password; delete data._password; Object.keys(data).forEach((key) => data[key] === '' && delete data[key]); await reauthenticate(password); await api(moderationRoutes.reportEnforce, { params: { reportId }, body: data }); toast('Enforcement applied.'); await load(); } }));
        await load();
    }

    async function bootAppeals() {
        let page = 1; const form = $('[data-m4-appeal-filters]');
        const load = async () => { setLoading(true); try { const query = form ? Object.fromEntries(new FormData(form).entries()) : {}; query.page = page; const payload = await api(moderationRoutes.appealList, { query }); const rows = first(payload, ['data.data', 'data', 'appeals'], []); const meta = first(payload, ['data.meta', 'meta'], {}); const tbody = $('[data-m4-appeal-rows]'); tbody.replaceChildren(); $('[data-m4-empty]').hidden = rows.length !== 0; rows.forEach((appeal) => { const tr = document.createElement('tr'); tr.className = 'orbit-m4-row'; tr.tabIndex = 0; const id = text(first(appeal, ['id', 'appeal_id'])); [id, text(first(appeal, ['user.name', 'user.display_name', 'user_id'])), text(first(appeal, ['enforcement.action', 'enforcement_id'])), titleCase(first(appeal, ['status'])), text(first(appeal, ['assigned_admin.name', 'assigned_admin_id'])), formatDate(first(appeal, ['created_at', 'submitted_at']))].forEach((value, index) => { const td = document.createElement('td'); if (index === 3) td.append(badge(value, valueTone(value))); else td.textContent = value; tr.append(td); }); const open = () => { window.location.href = `${window.location.pathname.replace(/\/$/, '')}/${encodeURIComponent(id)}`; }; tr.addEventListener('click', open); tr.addEventListener('keydown', (e) => { if (e.key === 'Enter') open(); }); tbody.append(tr); }); $('[data-m4-count]').textContent = `${first(meta, ['total'], rows.length)} total`; pagination(meta, (next) => { page = next; load(); }); showContent(); } catch (error) { showError(error); } };
        form?.addEventListener('submit', (e) => { e.preventDefault(); page = 1; load(); }); bindCommon(load); await load();
    }

    async function bootAppeal() {
        const appealId = root.dataset.appealId;
        const load = async () => { setLoading(true); try { const payload = await api(moderationRoutes.appealShow, { params: { appealId } }); const appeal = first(payload, ['data'], payload); $('[data-m4-title]').textContent = `Appeal ${text(first(appeal, ['id', 'appeal_id']))}`; const status = first(appeal, ['status'], 'unknown'); $('[data-m4-status]').replaceChildren(badge(status, valueTone(status))); renderKv($('[data-m4-overview]'), [['Appeal ID', first(appeal, ['id'])], ['User', first(appeal, ['user.name', 'user_id'])], ['Status', status], ['Assignee', first(appeal, ['assigned_admin.name', 'assigned_admin_id'])], ['Submitted', formatDate(first(appeal, ['created_at', 'submitted_at']))]]); $('[data-m4-explanation]').textContent = text(first(appeal, ['explanation', 'user_explanation']), 'No explanation supplied.'); const enforcement = first(appeal, ['enforcement'], {}); $('[data-m4-enforcement]').textContent = `${titleCase(first(enforcement, ['action'], 'Enforcement'))} · ${text(first(enforcement, ['reason']))}`; renderStack($('[data-m4-reviews]'), list(appeal, ['reviews', 'review_history']), 'No review decision yet.', (item) => timelineItem(`${titleCase(first(item, ['outcome', 'status'], 'Review'))}`, first(item, ['decision_reason', 'reason'], ''), formatDate(first(item, ['created_at', 'reviewed_at'])))); showContent(); } catch (error) { showError(error); } };
        bindCommon(load);
        $('[data-m4-action="appeal-assign"]')?.addEventListener('click', () => dialog({ title: 'Assign appeal', description: 'Assignment requires appeal review permission.', fields: [{ name: 'assigned_admin_id', label: 'Reviewer administrator ID' }, { name: 'reason', label: 'Assignment reason', type: 'textarea' }], confirm: 'Assign', onSubmit: async (data) => { await api(moderationRoutes.appealAssign, { params: { appealId }, body: data }); toast('Appeal assigned.'); await load(); } }));
        const reviewDialog = (second = false) => dialog({ title: second ? 'Complete second review' : 'Review appeal', description: second ? 'The second reviewer must be different from the first reviewer when separation of duties is required.' : 'Record a reasoned appeal decision. Overturning enforcement may restore actual access.', danger: second, fields: [{ name: 'outcome', label: 'Outcome', type: 'select', options: ['upheld', 'overturned'] }, { name: 'decision_reason', label: 'Decision reason', type: 'textarea' }], confirm: second ? 'Confirm second review' : 'Record review', onSubmit: async (data) => { await api(second ? moderationRoutes.appealSecondReview : moderationRoutes.appealReview, { params: { appealId }, body: data }); toast(second ? 'Second review recorded.' : 'Appeal review recorded.'); await load(); } });
        $('[data-m4-action="appeal-review"]')?.addEventListener('click', () => reviewDialog(false)); $('[data-m4-action="appeal-second"]')?.addEventListener('click', () => reviewDialog(true)); await load();
    }

    async function bootRisk() {
        let page = 1; const form = $('[data-m4-risk-filters]');
        const load = async () => { setLoading(true); try { const query = form ? Object.fromEntries(new FormData(form).entries()) : {}; query.page = page; const payload = await api(moderationRoutes.riskList, { query }); const rows = first(payload, ['data.data', 'data', 'profiles'], []); const meta = first(payload, ['data.meta', 'meta'], {}); const tbody = $('[data-m4-risk-rows]'); tbody.replaceChildren(); $('[data-m4-empty]').hidden = rows.length !== 0; rows.forEach((profile) => { const tr = document.createElement('tr'); tr.className = 'orbit-m4-row'; tr.tabIndex = 0; const id = text(first(profile, ['id', 'profile_id'])); [id, text(first(profile, ['user.name', 'subject.name', 'user_id', 'subject_id'])), text(first(profile, ['score', 'risk_score'], 0)), titleCase(first(profile, ['level', 'risk_level'])), text(first(profile, ['signals_count', 'signal_count'], list(profile, ['signals']).length)), formatDate(first(profile, ['updated_at']))].forEach((value, index) => { const td = document.createElement('td'); if (index === 3) td.append(badge(value, valueTone(value))); else td.textContent = value; tr.append(td); }); const open = () => { window.location.href = `${window.location.pathname.replace(/\/$/, '')}/${encodeURIComponent(id)}`; }; tr.addEventListener('click', open); tr.addEventListener('keydown', (e) => { if (e.key === 'Enter') open(); }); tbody.append(tr); }); $('[data-m4-count]').textContent = `${first(meta, ['total'], rows.length)} total`; pagination(meta, (next) => { page = next; load(); }); showContent(); } catch (error) { showError(error); } };
        form?.addEventListener('submit', (e) => { e.preventDefault(); page = 1; load(); }); bindCommon(load); await load();
    }

    async function bootRiskProfile() {
        const profileId = root.dataset.profileId;
        const load = async () => { setLoading(true); try { const payload = await api(moderationRoutes.riskShow, { params: { profileId } }); const profile = first(payload, ['data'], payload); $('[data-m4-title]').textContent = `Risk profile ${text(first(profile, ['id', 'profile_id']))}`; const level = first(profile, ['level', 'risk_level'], 'unknown'); $('[data-m4-status]').replaceChildren(badge(level, valueTone(level))); const scoreBox = $('[data-m4-score]'); scoreBox.replaceChildren(); const score = document.createElement('strong'); score.className = 'orbit-m4-score'; score.textContent = text(first(profile, ['score', 'risk_score'], 0)); scoreBox.append(score, document.createTextNode(` ${titleCase(level)}`)); renderStack($('[data-m4-rules]'), list(profile, ['triggered_rules', 'rules']), 'No triggered rules.', (rule) => timelineItem(typeof rule === 'string' ? rule : first(rule, ['name', 'rule'], 'Rule'), typeof rule === 'string' ? '' : first(rule, ['description'], ''))); renderStack($('[data-m4-signals]'), list(profile, ['signals']), 'No active signals.', (signal) => { const item = timelineItem(titleCase(first(signal, ['type', 'signal_type'], 'Signal')), first(signal, ['reason', 'description'], ''), formatDate(first(signal, ['created_at', 'occurred_at']))); const id = first(signal, ['id']); if (id && !first(signal, ['resolved_at'])) { const button = document.createElement('button'); button.type = 'button'; button.className = 'orbit-button orbit-button--quiet orbit-m4-inline-action'; button.textContent = 'Resolve'; button.addEventListener('click', () => dialog({ title: 'Resolve risk signal', description: 'Resolution recalculates the current risk profile and is audited.', fields: [{ name: 'reason', label: 'Resolution reason', type: 'textarea' }], confirm: 'Resolve signal', onSubmit: async (data) => { await api(moderationRoutes.riskResolve, { params: { signalId: id, profileId }, body: data }); toast('Risk signal resolved.'); await load(); } })); item.append(button); } return item; }); const timeline = [...list(profile, ['timeline']), ...list(profile, ['prior_enforcement', 'enforcements'])]; renderStack($('[data-m4-timeline]'), timeline, 'No prior enforcement or risk events.', (item) => timelineItem(titleCase(first(item, ['type', 'action', 'event'], 'Event')), first(item, ['reason', 'description'], ''), formatDate(first(item, ['created_at', 'occurred_at', 'applied_at'])))); showContent(); } catch (error) { showError(error); } };
        bindCommon(load);
        $('[data-m4-action="risk-signal"]')?.addEventListener('click', () => dialog({ title: 'Add manual risk signal', description: 'Manual signals are sanitized, permissioned, and audited. Do not paste encrypted content or secrets.', fields: [{ name: 'type', label: 'Signal type', placeholder: 'manual_review' }, { name: 'severity', label: 'Severity', type: 'select', options: ['low', 'medium', 'high', 'critical'] }, { name: 'reason', label: 'Reason', type: 'textarea' }], confirm: 'Add signal', onSubmit: async (data) => { await api(moderationRoutes.riskCreate, { params: { profileId }, body: data }); toast('Risk signal added.'); await load(); } })); await load();
    }

    const boots = { 'moderation-index': bootReports, 'moderation-report-show': bootReport, 'moderation-appeals-index': bootAppeals, 'moderation-appeal-show': bootAppeal, 'moderation-risk-index': bootRisk, 'moderation-risk-show': bootRiskProfile };
    const boot = boots[root.dataset.orbitView];
    if (boot) boot();
}
