<header class="orbit-topbar">
    <button class="orbit-icon-button orbit-topbar__menu" type="button" data-sidebar-open aria-label="Open navigation">☰</button>

    <button class="orbit-global-search-trigger" type="button" data-global-search-open aria-label="Open global search">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z"/></svg>
        <span>Search Orbit administration</span>
        <kbd>Ctrl K</kbd>
    </button>

    <div class="orbit-topbar__actions">
        <span class="orbit-system-status" data-system-status><i></i><span>SECURE</span></span>
        <div class="orbit-admin-profile" data-admin-profile>
            <button class="orbit-admin-profile__button" type="button" data-profile-toggle aria-expanded="false">
                <span class="orbit-avatar" data-admin-avatar>A</span>
                <span class="orbit-admin-profile__identity"><strong data-admin-name>Administrator</strong><small data-admin-role>Verifying session</small></span>
                <span aria-hidden="true">⌄</span>
            </button>
            <div class="orbit-profile-menu" data-profile-menu hidden>
                <div class="orbit-profile-menu__meta">
                    <strong data-menu-admin-name>Administrator</strong>
                    <span data-menu-admin-email>Verifying secure session…</span>
                </div>
                <button type="button" data-profile-switch>Switch administrator</button>
                <button type="button" data-profile-sign-out>Sign out</button>
            </div>
        </div>
    </div>
</header>
