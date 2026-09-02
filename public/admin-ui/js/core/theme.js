import { preferences } from './storage.js';

const media = window.matchMedia('(prefers-color-scheme: dark)');

export function currentTheme() {
    return preferences.get('theme', 'system');
}

export function applyTheme(theme = currentTheme()) {
    const resolved = theme === 'system' ? (media.matches ? 'dark' : 'light') : theme;
    document.documentElement.dataset.theme = resolved;
    document.documentElement.dataset.themePreference = theme;
    syncIcons(resolved);
}

export function toggleTheme() {
    const resolved = document.documentElement.dataset.theme || 'light';
    const next = resolved === 'dark' ? 'light' : 'dark';
    preferences.set('theme', next);
    applyTheme(next);
}

function syncIcons(theme) {
    document.querySelectorAll('[data-theme-icon="light"]').forEach((el) => { el.hidden = theme === 'dark'; });
    document.querySelectorAll('[data-theme-icon="dark"]').forEach((el) => { el.hidden = theme !== 'dark'; });
}

export function initTheme() {
    applyTheme();
    media.addEventListener?.('change', () => {
        if (currentTheme() === 'system') applyTheme('system');
    });
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => button.addEventListener('click', toggleTheme));
}
