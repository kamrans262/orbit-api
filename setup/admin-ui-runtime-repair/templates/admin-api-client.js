import { adminAuthContract } from './admin-auth.generated.js';

export class AdminApiError extends Error {
    constructor(message, { status = null, requestId = null, payload = null } = {}) {
        super(message);
        this.name = 'AdminApiError';
        this.status = status;
        this.requestId = requestId;
        this.payload = payload;
    }
}

function storageFor(name) {
    if (name === 'localStorage') return window.localStorage;
    if (name === 'sessionStorage') return window.sessionStorage;
    return null;
}

function tokenFromValue(value, depth = 0) {
    if (typeof value !== 'string' || !value.trim() || depth > 4) return null;

    const trimmed = value.trim();
    if (/^Bearer\s+/i.test(trimmed)) return trimmed.replace(/^Bearer\s+/i, '').trim() || null;

    if ((trimmed.startsWith('{') && trimmed.endsWith('}')) || (trimmed.startsWith('[') && trimmed.endsWith(']'))) {
        try {
            return tokenFromObject(JSON.parse(trimmed), depth + 1);
        } catch (_) {
            return null;
        }
    }

    // Laravel Sanctum plain-text tokens are deliberately opaque. The canonical
    // admin session adapter only supplies values from statically discovered auth keys.
    return trimmed.length > 20 ? trimmed : null;
}

function tokenFromObject(value, depth = 0) {
    if (!value || typeof value !== 'object' || depth > 4) return null;

    const preferredKeys = [
        'access_token',
        'accessToken',
        'admin_access_token',
        'adminAccessToken',
        'token',
        'bearer_token',
        'bearerToken',
    ];

    for (const key of preferredKeys) {
        if (Object.prototype.hasOwnProperty.call(value, key)) {
            const token = typeof value[key] === 'string'
                ? tokenFromValue(value[key], depth + 1)
                : tokenFromObject(value[key], depth + 1);
            if (token) return token;
        }
    }

    for (const [key, nested] of Object.entries(value)) {
        if (!/(admin|auth|session|token|credential)/i.test(key)) continue;
        const token = typeof nested === 'string'
            ? tokenFromValue(nested, depth + 1)
            : tokenFromObject(nested, depth + 1);
        if (token) return token;
    }

    return null;
}

function resolveAdminAccessTokens() {
    const tokens = [];
    const seen = new Set();

    for (const candidate of adminAuthContract.storageCandidates || []) {
        try {
            const storage = storageFor(candidate.storage);
            if (!storage) continue;
            const token = tokenFromValue(storage.getItem(candidate.key));
            if (!token || seen.has(token)) continue;
            seen.add(token);
            tokens.push(token);
        } catch (_) {
            // Hardened browser contexts can disable Web Storage. The caller will
            // receive the backend's normal authentication error rather than crash.
        }
    }

    return tokens;
}

function routeUrl(route, params = {}) {
    if (!route?.uri) throw new AdminApiError('This administrator operation is not registered by the backend.');

    let uri = route.uri.startsWith('/') ? route.uri : `/${route.uri}`;
    for (const [key, value] of Object.entries(params)) {
        uri = uri.replace(`{${key}}`, encodeURIComponent(String(value)));
    }

    if (/\{[^}]+\}/.test(uri)) {
        throw new AdminApiError('A required administrator route parameter is missing.');
    }

    return uri;
}

function buildUrl(route, params, query) {
    const url = new URL(routeUrl(route, params), window.location.origin);
    for (const [key, value] of Object.entries(query || {})) {
        if (value === '' || value === null || value === undefined || value === false) continue;
        url.searchParams.set(key, String(value));
    }
    return url;
}

function baseHeaders() {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrf) headers['X-CSRF-TOKEN'] = csrf;
    return headers;
}

async function execute(route, request, token = null) {
    const headers = baseHeaders();
    if (token) headers.Authorization = `Bearer ${token}`;
    if (request.body !== undefined) headers['Content-Type'] = 'application/json';

    const response = await fetch(buildUrl(route, request.params, request.query), {
        method: route.method || 'GET',
        credentials: 'same-origin',
        headers,
        body: request.body === undefined ? undefined : JSON.stringify(request.body),
        signal: request.signal,
    });

    const requestId = response.headers.get('X-Request-ID') || response.headers.get('X-Request-Id');
    let payload = {};
    try {
        payload = await response.json();
    } catch (_) {
        // Empty 204 responses are valid for some administrator operations.
    }

    if (!response.ok) {
        const validation = payload?.errors ? Object.values(payload.errors).flat().join(' ') : '';
        const message = payload?.message
            || payload?.error?.message
            || payload?.error
            || validation
            || `Request failed (${response.status}).`;
        const suffix = requestId ? ` Request ID: ${requestId}` : '';
        throw new AdminApiError(`${message}${suffix}`, { status: response.status, requestId, payload });
    }

    return payload;
}

export async function adminApiRequest(route, {
    params = {},
    query = {},
    body = undefined,
    signal = undefined,
} = {}) {
    const request = { params, query, body, signal };
    const tokens = resolveAdminAccessTokens();

    if (tokens.length === 0) {
        return execute(route, request);
    }

    for (const token of tokens) {
        try {
            return await execute(route, request, token);
        } catch (error) {
            if (!(error instanceof AdminApiError) || error.status !== 401) throw error;
        }
    }

    // The canonical Foundation shell may authenticate through its same-origin
    // browser session rather than a bearer credential. If all statically proven
    // bearer candidates are stale or are session metadata rather than tokens,
    // make one final cookie-backed request before surfacing the authentication error.
    return execute(route, request);

}
