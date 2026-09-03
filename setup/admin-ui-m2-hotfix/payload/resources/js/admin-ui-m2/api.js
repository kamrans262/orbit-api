const TOKEN_KEYS = [
    'orbit_admin_access_token',
    'orbit_admin_token',
    'admin_access_token',
    'admin_token',
    'orbitAdminAccessToken',
    'orbitAdminToken',
];

const TOKEN_FIELD_NAMES = new Set([
    'access_token',
    'accessToken',
    'token',
    'admin_token',
    'adminToken',
    'bearer_token',
    'bearerToken',
]);

function usableToken(value) {
    return typeof value === 'string' && value.trim().length > 20 ? value.trim() : null;
}

function tokenFromObject(value, depth = 0) {
    if (!value || typeof value !== 'object' || depth > 5) return null;

    for (const [key, child] of Object.entries(value)) {
        if (TOKEN_FIELD_NAMES.has(key)) {
            const direct = usableToken(child);
            if (direct) return direct;
        }
    }

    for (const child of Object.values(value)) {
        if (child && typeof child === 'object') {
            const nested = tokenFromObject(child, depth + 1);
            if (nested) return nested;
        }
    }

    return null;
}

function tokenFromStoredValue(raw) {
    const direct = usableToken(raw);
    if (!direct) return null;

    if (raw.trim().startsWith('{') || raw.trim().startsWith('[')) {
        try {
            return tokenFromObject(JSON.parse(raw));
        } catch (_) {
            return null;
        }
    }

    return direct;
}

function tokenFromStorage(store) {
    try {
        for (const key of TOKEN_KEYS) {
            const value = tokenFromStoredValue(store.getItem(key));
            if (value) return value;
        }

        // M1 stores its administrator session as one object. Support both the
        // current object form and older admin-named storage keys without
        // requiring M2 to know the exact storage key.
        for (let index = 0; index < store.length; index += 1) {
            const key = store.key(index) || '';
            if (!/orbit|admin/i.test(key)) continue;

            const raw = store.getItem(key);
            if (!raw) continue;

            if (/token/i.test(key)) {
                const direct = tokenFromStoredValue(raw);
                if (direct) return direct;
            }

            try {
                const parsed = JSON.parse(raw);
                const nested = tokenFromObject(parsed);
                if (nested) return nested;
            } catch (_) {
                // Non-JSON values are intentionally ignored here unless the
                // storage key itself identifies them as a token above.
            }
        }
    } catch (_) {
        // Browser storage can be unavailable in restricted/private contexts.
    }

    return null;
}

function tokenFromCookie() {
    try {
        for (const item of document.cookie.split(';')) {
            const [rawKey, ...parts] = item.trim().split('=');
            if (!rawKey || !/orbit.*admin.*token|admin.*token/i.test(rawKey)) continue;
            const value = usableToken(decodeURIComponent(parts.join('=')));
            if (value) return value;
        }
    } catch (_) {
        // HttpOnly cookies are intentionally inaccessible; same-origin fetch
        // still carries them through credentials below.
    }

    return null;
}

function adminToken() {
    const globalCandidates = [
        window.__ORBIT_ADMIN_TOKEN__,
        window.__ORBIT_ADMIN_ACCESS_TOKEN__,
        window.OrbitAdmin?.accessToken,
        window.OrbitAdmin?.access_token,
        window.orbitAdmin?.accessToken,
        window.orbitAdmin?.access_token,
        window.OrbitAdmin?.session,
        window.orbitAdmin?.session,
    ];

    for (const candidate of globalCandidates) {
        const direct = usableToken(candidate);
        if (direct) return direct;
        const nested = tokenFromObject(candidate);
        if (nested) return nested;
    }

    return tokenFromStorage(window.sessionStorage)
        || tokenFromStorage(window.localStorage)
        || tokenFromCookie();
}

export class OrbitApiError extends Error {
    constructor(message, status, code, requestId, validation = null) {
        super(message);
        this.name = 'OrbitApiError';
        this.status = status;
        this.code = code;
        this.requestId = requestId;
        this.validation = validation;
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
        response = await fetch(path, {
            credentials: 'same-origin',
            cache: 'no-store',
            ...options,
            headers,
            body,
        });
    } catch (_) {
        throw new OrbitApiError('Network connection failed. Check your connection and retry.', 0, 'network_error', null);
    }

    const requestId = response.headers.get('X-Request-Id');
    const type = response.headers.get('content-type') || '';
    const payload = type.includes('application/json') ? await response.json().catch(() => ({})) : {};

    if (!response.ok) {
        const message = payload?.error?.message
            || payload?.message
            || (response.status === 401
                ? 'Your administrator session is missing or expired. Return to the Admin Dashboard and sign in again.'
                : response.status === 403
                    ? 'Your administrator role does not allow this action.'
                    : response.status === 428
                        ? 'Recent administrator reauthentication is required.'
                        : 'The operation could not be completed.');
        const code = payload?.error?.code
            || payload?.code
            || (response.status === 428 ? 'reauth_required' : `http_${response.status}`);

        throw new OrbitApiError(message, response.status, code, requestId || payload?.request_id, payload?.errors || null);
    }

    return payload;
}

export function unwrap(payload) {
    return payload?.data ?? payload ?? {};
}

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

    return {
        current,
        last,
        total,
        perPage,
        hasNext: source.has_more === true || current < last,
        hasPrev: current > 1,
    };
}
