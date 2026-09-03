import {adminAccessToken} from './auth-session.js';
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
    if (typeof value !== 'string' || !value.trim() || depth > 5) return null;
    const trimmed = value.trim();
    if (/^Bearer\s+/i.test(trimmed)) return trimmed.replace(/^Bearer\s+/i, '').trim() || null;

    if ((trimmed.startsWith('{') && trimmed.endsWith('}')) || (trimmed.startsWith('[') && trimmed.endsWith(']'))) {
        try {
            return tokenFromObject(JSON.parse(trimmed), depth + 1);
        } catch (_) {
            return null;
        }
    }

    // Sanctum plain-text tokens are opaque. Values are only accepted from storage
    // keys proven by the canonical Foundation/M3 auth call graph.
    return trimmed.length > 20 ? trimmed : null;
}

function tokenFromObject(value, depth = 0) {
    if (!value || typeof value !== 'object' || depth > 5) return null;
    const preferred = [
        'access_token', 'accessToken', 'admin_access_token', 'adminAccessToken',
        'token', 'bearer_token', 'bearerToken', 'credential', 'credentials',
    ];

    for (const key of preferred) {
        if (!Object.prototype.hasOwnProperty.call(value, key)) continue;
        const nested = value[key];
        const token = typeof nested === 'string' ? tokenFromValue(nested, depth + 1) : tokenFromObject(nested, depth + 1);
        if (token) return token;
    }

    for (const nested of Object.values(value)) {
        if (!nested || typeof nested !== 'object') continue;
        const token = tokenFromObject(nested, depth + 1);
        if (token) return token;
    }

    return null;
}

function resolveCanonicalTokens() {
    if (adminAuthContract.strategy !== 'canonical-web-storage') return [];
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
            // Hardened browser contexts can disable Web Storage. The normal server
            // authentication response is surfaced instead of crashing the console.
        }
    }
    return tokens;
}

function routeUrl(route, params = {}) {
    if (!route?.uri) throw new AdminApiError('This administrator operation is not registered by the backend.');
    let uri = route.uri.startsWith('/') ? route.uri : `/${route.uri}`;
    for (const [key, value] of Object.entries(params)) uri = uri.replace(`{${key}}`, encodeURIComponent(String(value)));
    if (/\{[^}]+\}/.test(uri)) throw new AdminApiError('A required administrator route parameter is missing.');
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
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrf) headers['X-CSRF-TOKEN'] = csrf;
    return headers;
}

async function execute(route, request, _legacyToken = null) {
    // M4/M5 must use the exact same administrator credential source as the
    // canonical Foundation shell. Any token inferred by legacy generated
    // storage metadata is intentionally ignored here.
    const token = adminAccessToken();
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
    try { payload = await response.json(); } catch (_) { /* 204/no-content is valid */ }

    if (!response.ok) {
        const validation = payload?.errors ? Object.values(payload.errors).flat().join(' ') : '';
        let message = payload?.message || payload?.error?.message || payload?.error || validation || `Request failed (${response.status}).`;
        if (response.status === 401 && /auth/i.test(String(message))) {
            message = 'Administrator session is no longer accepted by the protected API. Sign in again through the Orbit administrator login.';
        }
        const suffix = requestId ? ` Request ID: ${requestId}` : '';
        throw new AdminApiError(`${message}${suffix}`, { status: response.status, requestId, payload });
    }
    return payload;
}

export async function adminApiRequest(route, { params = {}, query = {}, body = undefined, signal = undefined } = {}) {
    const request = { params, query, body, signal };
    const tokens = resolveCanonicalTokens();

    if (adminAuthContract.strategy === 'canonical-cookie') return execute(route, request);
    if (tokens.length === 0) return execute(route, request);

    let lastUnauthorized = null;
    for (const token of tokens) {
        try {
            return await execute(route, request, token);
        } catch (error) {
            if (!(error instanceof AdminApiError) || error.status !== 401) throw error;
            lastUnauthorized = error;
        }
    }
    throw lastUnauthorized || new AdminApiError('Administrator authentication is required.', { status: 401 });
}
