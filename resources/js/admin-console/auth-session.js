import {FOUNDATION_DETECTED_TOKEN_KEYS} from './foundation-auth-keys.js';

const LEGACY_CONSOLE_SESSION_KEY = 'orbit.admin.session.v1';
const IDENTITY_KEY = 'orbit.admin.console.identity.v1';

const FOUNDATION_TOKEN_KEYS = [
    ...FOUNDATION_DETECTED_TOKEN_KEYS,
    'orbit_admin_access_token',
    'orbit_admin_token',
    'admin_access_token',
    'admin_token',
    'orbitAdminAccessToken',
    'orbitAdminToken',
    'orbit_admin_ui_access_token',
    'orbit_admin_ui_token',
    'orbit.admin.access_token',
    'orbit.admin.accessToken',
    'orbit.admin.token',
    'orbit.admin.auth',
    'orbit.admin.auth.v1',
    'orbit.admin.ui.session',
    'orbit.admin.ui.session.v1',
    'orbit_admin_session',
    'orbit-admin-session',
    LEGACY_CONSOLE_SESSION_KEY,
];

const TOKEN_FIELDS = new Set([
    'access_token',
    'accessToken',
    'admin_access_token',
    'adminAccessToken',
    'admin_token',
    'adminToken',
    'bearer_token',
    'bearerToken',
    'token',
]);

const ADMIN_MARKER_FIELDS = new Set([
    'admin',
    'administrator',
    'admin_user',
    'adminUser',
    'admin_id',
    'adminId',
]);

function usableToken(value) {
    return typeof value === 'string' && value.trim().length > 20 ? value.trim() : null;
}

function safeParse(value) {
    try {
        return value ? JSON.parse(value) : null;
    } catch (_) {
        return null;
    }
}

function tokenFromObject(value, depth = 0) {
    if (!value || typeof value !== 'object' || depth > 5) return null;

    for (const [key, child] of Object.entries(value)) {
        if (!TOKEN_FIELDS.has(key)) continue;
        const direct = usableToken(child);
        if (direct) return direct;
    }

    for (const child of Object.values(value)) {
        if (!child || typeof child !== 'object') continue;
        const nested = tokenFromObject(child, depth + 1);
        if (nested) return nested;
    }

    return null;
}

function tokenFromStoredValue(raw) {
    const direct = usableToken(raw);
    if (!direct) return null;

    const trimmed = direct.trim();
    if (!trimmed.startsWith('{') && !trimmed.startsWith('[')) return trimmed;

    return tokenFromObject(safeParse(trimmed));
}

function hasAdminMarker(value, depth = 0) {
    if (!value || typeof value !== 'object' || depth > 5) return false;

    for (const [key, child] of Object.entries(value)) {
        if (ADMIN_MARKER_FIELDS.has(key)) return true;
        if (typeof child === 'string' && /\b(?:super[-_ ]administrator|platform[-_ ]administrator|security[-_ ]administrator|safety[-_ ]operator|senior[-_ ]safety[-_ ]operator|moderator|support[-_ ]agent|finance[-_ ]manager|marketing[-_ ]manager|advertising[-_ ]manager|compliance[-_ ]officer|devops[-_ ]operator|read[-_ ]only)\b/i.test(child)) return true;
    }

    return Object.values(value).some((child) => child && typeof child === 'object' && hasAdminMarker(child, depth + 1));
}

function tokenFromStorage(store) {
    try {
        for (const key of FOUNDATION_TOKEN_KEYS) {
            const token = tokenFromStoredValue(store.getItem(key));
            if (token) return {accessToken: token, source: key};
        }

        // Foundation may store one nested session object under an Orbit key.
        // For unknown keys we require explicit administrator markers inside the
        // object before accepting any nested token, so a consumer Orbit session
        // cannot be promoted into administrator authentication by the browser.
        for (let index = 0; index < store.length; index += 1) {
            const key = store.key(index) ?? '';
            if (!/(orbit|admin)/i.test(key)) continue;

            const raw = store.getItem(key);
            if (!raw) continue;

            if (/admin/i.test(key) && /(auth|session|token|credential)/i.test(key)) {
                const direct = /token/i.test(key) ? tokenFromStoredValue(raw) : null;
                if (direct) return {accessToken: direct, source: key};
            }

            const parsed = safeParse(raw);
            if (!hasAdminMarker(parsed)) continue;

            const nested = tokenFromObject(parsed);
            if (nested) return {accessToken: nested, source: key};
        }
    } catch (_) {
        // Storage can be blocked by browser policy. The caller will treat that
        // exactly like a missing administrator session and return to /admin/login.
    }

    return null;
}

function tokenFromGlobals() {
    const candidates = [
        ['__ORBIT_ADMIN_TOKEN__', window.__ORBIT_ADMIN_TOKEN__],
        ['__ORBIT_ADMIN_ACCESS_TOKEN__', window.__ORBIT_ADMIN_ACCESS_TOKEN__],
        ['OrbitAdmin', window.OrbitAdmin],
        ['orbitAdmin', window.orbitAdmin],
    ];

    for (const [source, value] of candidates) {
        const direct = usableToken(value);
        if (direct) return {accessToken: direct, source};

        const nested = tokenFromObject(value);
        if (nested) return {accessToken: nested, source};
    }

    return null;
}

function cachedIdentity() {
    try {
        const value = safeParse(window.sessionStorage.getItem(IDENTITY_KEY));
        return value && typeof value === 'object' ? value : null;
    } catch (_) {
        return null;
    }
}

export function readAdminSession() {
    const resolved = tokenFromGlobals()
        ?? tokenFromStorage(window.sessionStorage)
        ?? tokenFromStorage(window.localStorage);

    if (!resolved) return null;

    return {
        ...resolved,
        admin: cachedIdentity(),
    };
}

export function updateAdminIdentity(admin) {
    try {
        window.sessionStorage.setItem(IDENTITY_KEY, JSON.stringify(admin ?? null));
    } catch (_) {
        // Identity caching is optional; authorization remains server-side.
    }

    window.dispatchEvent(new CustomEvent('orbit:admin-session-changed', {detail: {admin}}));
    return admin;
}

export function clearAdminSession() {
    const stores = [window.sessionStorage, window.localStorage];

    for (const store of stores) {
        try {
            FOUNDATION_TOKEN_KEYS.forEach((key) => store.removeItem(key));

            const removable = [];
            for (let index = 0; index < store.length; index += 1) {
                const key = store.key(index) ?? '';
                if (/admin/i.test(key) && /(auth|session|token|credential)/i.test(key)) removable.push(key);
            }
            removable.forEach((key) => store.removeItem(key));
        } catch (_) {
            // Best effort only. The server-side logout remains authoritative.
        }
    }

    try {
        window.sessionStorage.removeItem(IDENTITY_KEY);
    } catch (_) {
        // Ignore restricted storage.
    }

    window.dispatchEvent(new CustomEvent('orbit:admin-session-cleared'));
}

export function adminAccessToken() {
    return readAdminSession()?.accessToken ?? null;
}
