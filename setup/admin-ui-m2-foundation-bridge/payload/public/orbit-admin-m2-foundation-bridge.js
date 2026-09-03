(() => {
    'use strict';

    const VERSION = '2026-09-03.1';
    const TOKEN_KEY = 'orbit_admin_access_token';
    const DESTINATIONS = {
        users: '/admin/operations/users',
        circles: '/admin/operations/circles',
    };

    window.__ORBIT_M2_BRIDGE_VERSION__ = VERSION;

    function saveBearer(value) {
        if (typeof value !== 'string') return;

        const match = value.trim().match(/^Bearer\s+(.+)$/i);
        const token = match?.[1]?.trim();
        if (!token || token.length < 20) return;

        try {
            sessionStorage.setItem(TOKEN_KEY, token);
        } catch (_) {
            // Restricted browser storage should not break the Foundation UI.
        }
    }

    function authorizationFromHeaders(headers) {
        if (!headers) return null;

        try {
            if (headers instanceof Headers) return headers.get('Authorization');
        } catch (_) {
            // Continue with the fallback forms below.
        }

        if (Array.isArray(headers)) {
            const pair = headers.find(([key]) => String(key).toLowerCase() === 'authorization');
            return pair ? String(pair[1]) : null;
        }

        if (typeof headers === 'object') {
            const key = Object.keys(headers).find((name) => name.toLowerCase() === 'authorization');
            return key ? String(headers[key]) : null;
        }

        return null;
    }

    function installFetchCapture() {
        if (typeof window.fetch !== 'function' || window.fetch.__orbitM2Wrapped) return;

        const original = window.fetch.bind(window);
        const wrapped = function orbitM2Fetch(input, init) {
            try {
                const fromInit = authorizationFromHeaders(init?.headers);
                const fromRequest = input instanceof Request
                    ? authorizationFromHeaders(input.headers)
                    : null;
                saveBearer(fromInit || fromRequest);
            } catch (_) {
                // Token capture is best-effort; never block the request.
            }

            return original(input, init);
        };

        wrapped.__orbitM2Wrapped = true;
        window.fetch = wrapped;
    }

    function installXhrCapture() {
        const proto = window.XMLHttpRequest?.prototype;
        if (!proto || proto.setRequestHeader.__orbitM2Wrapped) return;

        const original = proto.setRequestHeader;
        const wrapped = function orbitM2SetRequestHeader(name, value) {
            if (String(name).toLowerCase() === 'authorization') saveBearer(String(value));
            return original.call(this, name, value);
        };

        wrapped.__orbitM2Wrapped = true;
        proto.setRequestHeader = wrapped;
    }

    function normalizedText(element) {
        return (element?.textContent || '')
            .replace(/\bnext\b/gi, ' ')
            .replace(/[^a-z\s]/gi, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase();
    }

    function isNavigationArea(element) {
        return Boolean(element?.closest?.(
            'aside, nav, [class*="sidebar" i], [class*="navigation" i], [class*="nav-" i], [class*="nav_" i], [data-sidebar], [data-navigation]'
        ));
    }

    function destinationForTarget(target) {
        if (!(target instanceof Element)) return null;

        let current = target;
        for (let depth = 0; current && depth < 7; depth += 1, current = current.parentElement) {
            if (!isNavigationArea(current)) continue;

            const text = normalizedText(current);
            if (text === 'users' || text.startsWith('users ')) return DESTINATIONS.users;
            if (text === 'circles' || text.startsWith('circles ')) return DESTINATIONS.circles;
        }

        return null;
    }

    function interceptFoundationNavigation(event) {
        if (location.pathname !== '/admin' && location.pathname !== '/admin/') return;

        const destination = destinationForTarget(event.target);
        if (!destination) return;

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        location.assign(destination);
    }

    function upgradeFoundationNavigation() {
        if (location.pathname !== '/admin' && location.pathname !== '/admin/') return;

        document.querySelectorAll('aside a, aside button, aside [role="button"], nav a, nav button, nav [role="button"]').forEach((item) => {
            const text = normalizedText(item);
            const destination = text === 'users' || text.startsWith('users ')
                ? DESTINATIONS.users
                : text === 'circles' || text.startsWith('circles ')
                    ? DESTINATIONS.circles
                    : null;

            if (!destination) return;

            item.dataset.orbitM2Destination = destination;
            if (item instanceof HTMLAnchorElement) item.href = destination;
            item.style.cursor = 'pointer';

            item.querySelectorAll('*').forEach((child) => {
                if (normalizedText(child) === '') return;
                if ((child.textContent || '').trim().toLowerCase() === 'next') child.hidden = true;
            });
        });
    }

    installFetchCapture();
    installXhrCapture();

    // Window capture runs before document/element click handlers, including the
    // Milestone-1 placeholder toast handler.
    window.addEventListener('click', interceptFoundationNavigation, true);

    const boot = () => {
        upgradeFoundationNavigation();

        const observer = new MutationObserver(upgradeFoundationNavigation);
        observer.observe(document.documentElement, {childList: true, subtree: true});
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, {once: true});
    } else {
        boot();
    }
})();
