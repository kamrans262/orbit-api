import {initFoundationRefinements} from './foundation-refinements.js';
import {initFoundationIntegration} from './foundation-integration.js';
import {initUsersIndex, initUserShow} from './users.js';
import {initCirclesIndex, initCircleShow} from './circles.js';

function boot() {
    initFoundationRefinements();
    initFoundationIntegration();

    document.querySelectorAll('[data-orbit-view]').forEach((root) => {
        switch (root.dataset.orbitView) {
            case 'users-index':
                initUsersIndex(root);
                break;
            case 'user-show':
                initUserShow(root);
                break;
            case 'circles-index':
                initCirclesIndex(root);
                break;
            case 'circle-show':
                initCircleShow(root);
                break;
            default:
                break;
        }
    });

    document.querySelector('[data-orbit-sidebar-toggle]')?.addEventListener('click', () => {
        document.querySelector('.orbit-shell')?.classList.toggle('is-sidebar-open');
    });
}

document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', boot, {once: true})
    : boot();
