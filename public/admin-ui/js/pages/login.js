import { ApiError } from '../core/api.js';
import { login, redirectIfAuthenticated } from '../core/auth.js';
import { initTheme } from '../core/theme.js';
import { applyFieldErrors, clearFieldErrors, setButtonLoading, toast } from '../core/ui.js';

initTheme();
redirectIfAuthenticated();

const form = document.getElementById('admin-login-form');
const alert = document.getElementById('login-alert');
const password = document.getElementById('admin-password');
const passwordToggle = document.querySelector('[data-password-toggle]');

passwordToggle?.addEventListener('click', () => {
    if (!password) return;
    password.type = password.type === 'password' ? 'text' : 'password';
    passwordToggle.setAttribute('aria-label', password.type === 'password' ? 'Show password' : 'Hide password');
});

form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearFieldErrors(form);
    if (alert) alert.hidden = true;
    const submit = form.querySelector('[type="submit"]');
    const email = form.email.value.trim();
    const secret = form.password.value;

    if (!email || !secret) {
        if (!email) form.querySelector('[data-error-for="email"]').textContent = 'Email is required.';
        if (!secret) form.querySelector('[data-error-for="password"]').textContent = 'Password is required.';
        return;
    }

    setButtonLoading(submit, true);
    try {
        await login(email, secret);
        window.location.assign('/admin/mfa');
    } catch (error) {
        if (error instanceof ApiError) applyFieldErrors(form, error.errors);
        if (alert) {
            alert.textContent = error.message || 'Sign-in failed.';
            alert.hidden = false;
        }
        toast('Sign-in unsuccessful', error.message || 'Check your credentials and try again.', 'danger');
    } finally {
        setButtonLoading(submit, false);
    }
});
