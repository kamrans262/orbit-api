const TOKEN_KEYS = ['orbit_admin_access_token','orbit_admin_token','admin_access_token','admin_token'];

function adminToken() {
    const direct = window.__ORBIT_ADMIN_TOKEN__ || window.OrbitAdmin?.accessToken || window.orbitAdmin?.accessToken;
    if (typeof direct === 'string' && direct.length > 20) return direct;
    for (const store of [window.sessionStorage, window.localStorage]) {
        try {
            for (const key of TOKEN_KEYS) {
                const value = store.getItem(key);
                if (value && value.length > 20) return value;
            }
            for (let index = 0; index < store.length; index += 1) {
                const key = store.key(index) || '';
                if (/admin/i.test(key) && /token/i.test(key)) {
                    const value = store.getItem(key);
                    if (value && value.length > 20) return value;
                }
            }
        } catch (_) { /* storage can be unavailable */ }
    }
    return null;
}

export class OrbitApiError extends Error {
    constructor(message, status, code, requestId, validation = null) {
        super(message); this.name = 'OrbitApiError'; this.status = status; this.code = code; this.requestId = requestId; this.validation = validation;
    }
}

export async function orbitAdminApi(path, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');
    const token = adminToken();
    if (token) headers.set('Authorization', `Bearer ${token}`);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrf) headers.set('X-CSRF-TOKEN', csrf);
    let body = options.body;
    if (body && typeof body !== 'string' && !(body instanceof FormData)) {
        headers.set('Content-Type', 'application/json');
        body = JSON.stringify(body);
    }
    let response;
    try {
        response = await fetch(path, {credentials: 'same-origin', cache: 'no-store', ...options, headers, body});
    } catch (_) {
        throw new OrbitApiError('Network connection failed. Check your connection and retry.', 0, 'network_error', null);
    }
    const requestId = response.headers.get('X-Request-Id');
    const type = response.headers.get('content-type') || '';
    const payload = type.includes('application/json') ? await response.json().catch(() => ({})) : {};
    if (!response.ok) {
        const message = payload?.error?.message || payload?.message || (response.status === 401 ? 'Your administrator session is missing or expired.' : response.status === 403 ? 'Your administrator role does not allow this action.' : response.status === 428 ? 'Recent administrator reauthentication is required.' : 'The operation could not be completed.');
        const code = payload?.error?.code || payload?.code || (response.status === 428 ? 'reauth_required' : `http_${response.status}`);
        throw new OrbitApiError(message, response.status, code, requestId || payload?.request_id, payload?.errors || null);
    }
    return payload;
}

export function unwrap(payload) { return payload?.data ?? payload ?? {}; }
export function listRows(payload) {
    const data = payload?.data;
    if (Array.isArray(data)) return data;
    if (Array.isArray(data?.data)) return data.data;
    if (Array.isArray(data?.items)) return data.items;
    if (Array.isArray(payload?.items)) return payload.items;
    return [];
}
export function pagination(payload, rowsLength = 0) {
    const source = payload?.meta ?? payload?.data?.meta ?? payload?.data ?? {};
    const current = Number(source.current_page ?? source.page ?? 1);
    const last = Number(source.last_page ?? source.pages ?? current);
    const total = Number(source.total ?? rowsLength);
    const perPage = Number((source.per_page ?? source.perPage ?? rowsLength) || 25);
    return {current, last, total, perPage, hasNext: source.has_more === true || current < last, hasPrev: current > 1};
}
