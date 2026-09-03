const M2_DESTINATIONS = {
    users: '/admin/operations/users',
    circles: '/admin/operations/circles',
};

function normalizedNavText(element) {
    return (element?.textContent || '')
        .replace(/\bnext\b/gi, '')
        .replace(/[^a-z\s]/gi, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .toLowerCase();
}

function destinationFor(element) {
    const text = normalizedNavText(element);
    if (text === 'users') return M2_DESTINATIONS.users;
    if (text === 'circles') return M2_DESTINATIONS.circles;
    return null;
}

function patchFoundationNavigation() {
    if (location.pathname !== '/admin' && location.pathname !== '/admin/') return;

    document.querySelectorAll('a,button,[role="button"]').forEach((element) => {
        const destination = destinationFor(element);
        if (!destination) return;

        element.dataset.orbitM2Destination = destination;
        element.classList.add('orbit-m2-nav-ready');
        element.querySelectorAll('*').forEach((child) => {
            if ((child.textContent || '').trim().toLowerCase() === 'next') child.hidden = true;
        });
    });
}

function captureFoundationNavigation(event) {
    if (location.pathname !== '/admin' && location.pathname !== '/admin/') return;

    const clickable = event.target instanceof Element
        ? event.target.closest('[data-orbit-m2-destination],a,button,[role="button"]')
        : null;
    if (!clickable) return;

    const destination = clickable.dataset.orbitM2Destination || destinationFor(clickable);
    if (!destination) return;

    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();
    window.location.assign(destination);
}

function focusFoundationSearchFromReturn() {
    if (location.pathname !== '/admin' && location.pathname !== '/admin/') return;
    if (new URLSearchParams(location.search).get('focus') !== 'search') return;

    const search = document.querySelector('input[type="search"],input[placeholder*="Search users"]');
    if (!search) return;

    search.focus({preventScroll: false});
    const clean = `${location.pathname}${location.hash}`;
    history.replaceState(history.state, '', clean);
}

export function initFoundationIntegration() {
    patchFoundationNavigation();
    focusFoundationSearchFromReturn();

    document.addEventListener('click', captureFoundationNavigation, true);

    const observer = new MutationObserver(() => patchFoundationNavigation());
    observer.observe(document.body, {childList: true, subtree: true});
}
