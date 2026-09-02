const SESSION_PREFIX = 'orbit.admin.';
const PREF_PREFIX = 'orbit.admin.pref.';

export const session = {
    get(key) {
        try { return sessionStorage.getItem(SESSION_PREFIX + key); } catch { return null; }
    },
    set(key, value) {
        try { sessionStorage.setItem(SESSION_PREFIX + key, value); } catch { /* session-only fallback is intentionally absent */ }
    },
    remove(key) {
        try { sessionStorage.removeItem(SESSION_PREFIX + key); } catch { /* noop */ }
    },
    clearAuth() {
        ['access_token', 'session', 'challenge_token', 'challenge_expires', 'email'].forEach((key) => this.remove(key));
    },
};

export const preferences = {
    get(key, fallback = null) {
        try { return localStorage.getItem(PREF_PREFIX + key) ?? fallback; } catch { return fallback; }
    },
    set(key, value) {
        try { localStorage.setItem(PREF_PREFIX + key, value); } catch { /* preferences are best effort */ }
    },
};
