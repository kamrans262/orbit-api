import {adminAccessToken, clearAdminSession} from './auth-session.js';

export class OrbitAdminApiError extends Error {
    constructor(message, {status = 0, code = 'unknown_error', requestId = null, validation = null} = {}) {
        super(message);
        this.name = 'OrbitAdminApiError';
        this.status = status;
        this.code = code;
        this.requestId = requestId;
        this.validation = validation;
    }
}

function messageForStatus(status) {
    if (status === 401) return 'Your administrator session is missing or expired.';
    if (status === 403) return 'Your administrator role does not allow this operation.';
    if (status === 404) return 'The requested administrator resource was not found.';
    if (status === 409) return 'The operation conflicts with the current server state.';
    if (status === 422) return 'Some submitted values are invalid.';
    if (status === 428) return 'Recent administrator reauthentication is required.';
    if (status >= 500) return 'Orbit could not complete this operation. Retry or use the request ID for support.';
    return 'The operation could not be completed.';
}

function normalizeErrorPayload(payload, status, requestId) {
    const source = payload?.error ?? payload ?? {};
    return new OrbitAdminApiError(
        source.message ?? payload?.message ?? messageForStatus(status),
        {
            status,
            code: source.code ?? payload?.code ?? `http_${status}`,
            requestId: requestId ?? payload?.request_id ?? source.request_id ?? null,
            validation: payload?.errors ?? source.errors ?? null,
        },
    );
}

export async function adminApi(path, options = {}) {
    const {
        auth = true,
        timeoutMs = 15000,
        body: rawBody,
        headers: rawHeaders,
        signal: externalSignal,
        ...fetchOptions
    } = options;

    const headers = new Headers(rawHeaders ?? {});
    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');

    if (auth) {
        const token = adminAccessToken();
        // Foundation currently uses a bearer token in browser storage, but the
        // canonical client also permits same-origin cookie authentication. If no
        // readable token exists we still ask the protected backend endpoint; a
        // genuine unauthenticated request receives the authoritative 401 there.
        if (token) headers.set('Authorization', `Bearer ${token}`);
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrf) headers.set('X-CSRF-TOKEN', csrf);

    let body = rawBody;
    if (body !== undefined && body !== null && typeof body !== 'string' && !(body instanceof FormData) && !(body instanceof Blob)) {
        headers.set('Content-Type', 'application/json');
        body = JSON.stringify(body);
    }

    const timeoutController = new AbortController();
    const timer = window.setTimeout(() => timeoutController.abort('timeout'), timeoutMs);
    const abortFromExternal = () => timeoutController.abort(externalSignal?.reason ?? 'aborted');
    externalSignal?.addEventListener('abort', abortFromExternal, {once: true});

    try {
        const response = await fetch(path, {
            credentials: 'same-origin',
            cache: 'no-store',
            ...fetchOptions,
            headers,
            body,
            signal: timeoutController.signal,
        });

        const requestId = response.headers.get('X-Request-Id');
        const contentType = response.headers.get('content-type') ?? '';
        const payload = contentType.includes('application/json')
            ? await response.json().catch(() => ({}))
            : {};

        if (!response.ok) {
            if (response.status === 401 && auth) {
                clearAdminSession();
                window.dispatchEvent(new CustomEvent('orbit:admin-auth-required'));
            }
            throw normalizeErrorPayload(payload, response.status, requestId);
        }

        return payload;
    } catch (error) {
        if (error instanceof OrbitAdminApiError) throw error;

        if (timeoutController.signal.aborted) {
            if (externalSignal?.aborted) {
                const aborted = new Error('Request aborted');
                aborted.name = 'AbortError';
                throw aborted;
            }
            throw new OrbitAdminApiError('The request timed out. Retry when the connection is stable.', {code: 'timeout'});
        }

        throw new OrbitAdminApiError('Network connection failed. Check your connection and retry.', {code: 'network_error'});
    } finally {
        window.clearTimeout(timer);
        externalSignal?.removeEventListener('abort', abortFromExternal);
    }
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
    if (Array.isArray(payload?.results)) return payload.results;
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

export async function firstSuccessfulEndpoint(candidates, options = {}) {
    let lastNotFound = null;

    for (const path of candidates) {
        try {
            return {path, payload: await adminApi(path, options)};
        } catch (error) {
            if (error instanceof OrbitAdminApiError && error.status === 404) {
                lastNotFound = error;
                continue;
            }
            throw error;
        }
    }

    throw lastNotFound ?? new OrbitAdminApiError('No compatible administrator endpoint is available.', {status: 404, code: 'endpoint_unavailable'});
}
