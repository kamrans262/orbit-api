import { api } from './api.js';
import { session } from './storage.js';

export async function login(email, password) {
    const data = await api('/api/admin/v1/auth/login', {
        method: 'POST',
        auth: false,
        body: { email, password },
    });

    session.set('challenge_token', data.challenge_token);
    session.set('challenge_expires', data.expires_at || '');
    session.set('email', email);
    return data;
}

export async function verifyMfa(code) {
    const challenge = session.get('challenge_token');
    if (!challenge) throw new Error('Your sign-in challenge is no longer available.');

    const data = await api('/api/admin/v1/auth/mfa/verify', {
        method: 'POST',
        auth: false,
        body: { challenge_token: challenge, code },
    });

    session.set('access_token', data.access_token);
    session.set('session', JSON.stringify({
        id: data.session_id,
        expires_at: data.expires_at,
        idle_expires_at: data.idle_expires_at,
    }));
    session.remove('challenge_token');
    session.remove('challenge_expires');
    return data;
}

export async function me() {
    return api('/api/admin/v1/auth/me');
}

export async function logout() {
    try { await api('/api/admin/v1/auth/logout', { method: 'POST' }); } finally { session.clearAuth(); }
}

export async function ensureAuthenticated() {
    if (!session.get('access_token')) {
        window.location.replace('/admin/login');
        return null;
    }

    try {
        return await me();
    } catch {
        session.clearAuth();
        window.location.replace('/admin/login');
        return null;
    }
}

export async function redirectIfAuthenticated() {
    if (!session.get('access_token')) return;
    try {
        await me();
        window.location.replace('/admin');
    } catch {
        session.clearAuth();
    }
}

export function hasChallenge() {
    const token = session.get('challenge_token');
    const expires = session.get('challenge_expires');
    if (!token) return false;
    if (expires && Date.parse(expires) <= Date.now()) {
        session.remove('challenge_token');
        return false;
    }
    return true;
}
