import { session } from './storage.js';

export class ApiError extends Error {
    constructor(message, { status = 0, code = null, errors = null, requestId = null } = {}) {
        super(message || 'The request could not be completed.');
        this.name = 'ApiError';
        this.status = status;
        this.code = code;
        this.errors = errors;
        this.requestId = requestId;
    }
}

function requestId() {
    if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
    return `ui-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export async function api(path, options = {}) {
    const { auth = true, body, headers = {}, ...fetchOptions } = options;
    const token = session.get('access_token');
    const finalHeaders = {
        Accept: 'application/json',
        'X-Request-Id': requestId(),
        ...headers,
    };

    if (body !== undefined) finalHeaders['Content-Type'] = 'application/json';
    if (auth && token) finalHeaders.Authorization = `Bearer ${token}`;

    let response;
    try {
        response = await fetch(path, {
            method: 'GET',
            cache: 'no-store',
            credentials: 'same-origin',
            ...fetchOptions,
            headers: finalHeaders,
            body: body === undefined ? undefined : JSON.stringify(body),
        });
    } catch (error) {
        throw new ApiError('Orbit could not reach the administrator API. Check your connection and try again.', { code: 'NETWORK_ERROR' });
    }

    let payload = null;
    const type = response.headers.get('content-type') || '';
    if (type.includes('application/json')) {
        try { payload = await response.json(); } catch { payload = null; }
    }

    if (!response.ok) {
        const error = new ApiError(payload?.message || `Request failed with status ${response.status}.`, {
            status: response.status,
            code: payload?.code || null,
            errors: payload?.errors || null,
            requestId: payload?.request_id || response.headers.get('X-Request-Id'),
        });

        if (auth && response.status === 401) {
            session.clearAuth();
            window.dispatchEvent(new CustomEvent('orbit:auth-expired'));
        }

        throw error;
    }

    return payload?.data ?? payload;
}
