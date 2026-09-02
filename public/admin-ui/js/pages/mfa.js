import { ApiError } from '../core/api.js';
import { hasChallenge, verifyMfa } from '../core/auth.js';
import { session } from '../core/storage.js';
import { initTheme } from '../core/theme.js';
import { applyFieldErrors, clearFieldErrors, initials, setButtonLoading, toast } from '../core/ui.js';

initTheme();
if (!hasChallenge()) window.location.replace('/admin/login');

const email = session.get('email') || 'Administrator';
document.querySelector('[data-auth-email]')?.replaceChildren(document.createTextNode(email));
document.querySelector('[data-auth-email-avatar]')?.replaceChildren(document.createTextNode(initials(email)));

const form = document.getElementById('admin-mfa-form');
const alert = document.getElementById('mfa-alert');
const code = document.getElementById('mfa-code');

code?.addEventListener('input', () => {
    if (/^[\d\s-]+$/.test(code.value)) code.value = code.value.replace(/[^\d]/g, '').slice(0, 6).replace(/(\d{3})(?=\d)/, '$1 ');
});

document.querySelector('[data-back-to-login]')?.addEventListener('click', () => {
    session.remove('challenge_token');
    session.remove('challenge_expires');
    window.location.assign('/admin/login');
});

form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearFieldErrors(form);
    if (alert) alert.hidden = true;
    const submit = form.querySelector('[type="submit"]');
    const value = form.code.value.replace(/\s/g, '').trim();
    if (value.length < 6) {
        form.querySelector('[data-error-for="code"]').textContent = 'Enter your authenticator or recovery code.';
        return;
    }

    setButtonLoading(submit, true);
    try {
        await verifyMfa(value);
        toast('Verified', 'Opening your secure Orbit workspace…', 'success', 1000);
        window.location.replace('/admin');
    } catch (error) {
        if (error instanceof ApiError) applyFieldErrors(form, error.errors);
        if (alert) { alert.textContent = error.message || 'Verification failed.'; alert.hidden = false; }
        code?.select();
    } finally {
        setButtonLoading(submit, false);
    }
});
