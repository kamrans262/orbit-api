import './support-m5.js';
import './moderation-m4.js';
import {initCirclesIndex, initCircleShow} from './circles.js';
import {initDashboard} from './dashboard.js';
import {initShell} from './shell.js';
import {initSosIndex, initSosShow} from './sos.js';
import {initUsersIndex, initUserShow} from './users.js';

function initFeatureViews() {
    document.querySelectorAll('[data-orbit-view]').forEach((root) => {
        if (root.dataset.orbitInitialized === 'true') return;
        root.dataset.orbitInitialized = 'true';

        switch (root.dataset.orbitView) {
            case 'dashboard': initDashboard(root); break;
            case 'users-index': initUsersIndex(root); break;
            case 'user-show': initUserShow(root); break;
            case 'circles-index': initCirclesIndex(root); break;
            case 'circle-show': initCircleShow(root); break;
            case 'sos-index': initSosIndex(root); break;
            case 'sos-show': initSosShow(root); break;
            default: break;
        }
    });
}

function boot() {
    // The shell validates the Foundation administrator session first. Feature API calls are
    // only started after the validated orbit:admin-ready event, preventing an
    // expired stored token from permanently initializing a page into an error
    // state before the sign-in flow can recover it.
    window.addEventListener('orbit:admin-ready', initFeatureViews);
    initShell();
}

document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', boot, {once: true})
    : boot();
