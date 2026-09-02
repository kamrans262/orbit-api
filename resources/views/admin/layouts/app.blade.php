<!doctype html>
<html lang="en" data-theme="system">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>@yield('title', 'Dashboard') · Orbit Administration</title>
    <link rel="stylesheet" href="{{ asset('admin-ui/css/admin.css') }}">
</head>
<body class="admin-app is-auth-pending" data-admin-page="@yield('page', 'dashboard')">
    <div class="route-progress" data-route-progress aria-hidden="true"></div>
    <div class="mobile-scrim" data-mobile-scrim></div>

    <div class="app-shell">
        <aside class="sidebar" data-sidebar>
            <div class="sidebar__top">
                <a href="{{ route('admin.ui.dashboard') }}" class="sidebar__brand"><x-admin.logo /></a>
                <button class="icon-button sidebar__collapse" type="button" data-sidebar-collapse aria-label="Collapse sidebar">
                    <x-admin.icon name="menu" />
                </button>
            </div>

            <nav class="sidebar__nav" aria-label="Administrator navigation">
                <p class="nav-label">Workspace</p>
                <a href="{{ route('admin.ui.dashboard') }}" class="nav-item is-active" data-nav-key="dashboard" data-permission="dashboard.view">
                    <span class="nav-item__icon"><x-admin.icon name="dashboard" /></span>
                    <span class="nav-item__label">Dashboard</span>
                </a>

                <p class="nav-label">Operations</p>
                <button class="nav-item nav-item--planned" type="button" data-planned-module="Users" data-permission="users.view">
                    <span class="nav-item__icon"><x-admin.icon name="users" /></span><span class="nav-item__label">Users</span><span class="nav-item__soon">Next</span>
                </button>
                <button class="nav-item nav-item--planned" type="button" data-planned-module="Circles" data-permission="circles.view">
                    <span class="nav-item__icon"><x-admin.icon name="circles" /></span><span class="nav-item__label">Circles</span>
                </button>
                <button class="nav-item nav-item--planned nav-item--danger" type="button" data-planned-module="SOS Command Center" data-permission="sos.view">
                    <span class="nav-item__icon"><x-admin.icon name="sos" /></span><span class="nav-item__label">SOS Command</span>
                </button>
                <button class="nav-item nav-item--planned" type="button" data-planned-module="Moderation" data-permission="reports.view">
                    <span class="nav-item__icon"><x-admin.icon name="shield" /></span><span class="nav-item__label">Moderation</span>
                </button>
                <button class="nav-item nav-item--planned" type="button" data-planned-module="Support" data-permission="support.view">
                    <span class="nav-item__icon"><x-admin.icon name="support" /></span><span class="nav-item__label">Support</span>
                </button>

                <p class="nav-label">Platform</p>
                <button class="nav-item nav-item--planned" type="button" data-planned-module="Billing" data-permission="subscriptions.view">
                    <span class="nav-item__icon"><x-admin.icon name="billing" /></span><span class="nav-item__label">Billing</span>
                </button>
                <button class="nav-item nav-item--planned" type="button" data-planned-module="Communications" data-permission="communications.view">
                    <span class="nav-item__icon"><x-admin.icon name="megaphone" /></span><span class="nav-item__label">Communications</span>
                </button>
                <button class="nav-item nav-item--planned" type="button" data-planned-module="Analytics" data-permission="analytics.view">
                    <span class="nav-item__icon"><x-admin.icon name="chart" /></span><span class="nav-item__label">Analytics</span>
                </button>
                <button class="nav-item nav-item--planned" type="button" data-planned-module="System Operations" data-permission="operations.view">
                    <span class="nav-item__icon"><x-admin.icon name="system" /></span><span class="nav-item__label">System</span>
                </button>
            </nav>

            <div class="sidebar__footer">
                <div class="sidebar-health" data-sidebar-health>
                    <span class="status-dot status-dot--muted" data-sidebar-health-dot></span>
                    <span class="sidebar-health__copy"><strong>Checking platform</strong><small>System status</small></span>
                </div>
            </div>
        </aside>

        <section class="workspace">
            <header class="topbar">
                <div class="topbar__left">
                    <button class="icon-button mobile-menu-button" type="button" data-mobile-menu aria-label="Open navigation"><x-admin.icon name="menu" /></button>
                    <button class="global-search-trigger" type="button" data-global-search-open>
                        <x-admin.icon name="search" size="18" />
                        <span>Search users, Circles, incidents…</span>
                        <kbd>Ctrl K</kbd>
                    </button>
                </div>
                <div class="topbar__right">
                    <button class="icon-button" type="button" data-dashboard-refresh aria-label="Refresh dashboard"><x-admin.icon name="refresh" /></button>
                    <button class="icon-button" type="button" data-theme-toggle aria-label="Toggle appearance">
                        <span data-theme-icon="light"><x-admin.icon name="sun" /></span>
                        <span data-theme-icon="dark"><x-admin.icon name="moon" /></span>
                    </button>
                    <button class="icon-button notification-button" type="button" aria-label="Notifications" data-static-info="Operational alerts will appear here in the System UI module.">
                        <x-admin.icon name="bell" />
                        <span class="notification-dot" aria-hidden="true"></span>
                    </button>
                    <div class="profile-menu-wrap">
                        <button class="profile-trigger" type="button" data-profile-trigger aria-expanded="false">
                            <span class="avatar" data-admin-avatar>A</span>
                            <span class="profile-trigger__copy"><strong data-admin-name>Administrator</strong><small data-admin-role>Secure session</small></span>
                            <x-admin.icon name="chevron-down" size="16" />
                        </button>
                        <div class="profile-menu surface-card" data-profile-menu hidden>
                            <div class="profile-menu__identity">
                                <strong data-admin-menu-name>Administrator</strong>
                                <small data-admin-email>Loading…</small>
                            </div>
                            <div class="profile-menu__meta"><span data-session-expiry>Secure session active</span></div>
                            <button class="menu-action menu-action--danger" type="button" data-admin-logout><x-admin.icon name="logout" size="17" /> Sign out</button>
                        </div>
                    </div>
                </div>
            </header>

            <main class="workspace__main">
                @yield('content')
            </main>
        </section>
    </div>

    <div class="command-palette" data-command-palette hidden>
        <button class="command-palette__backdrop" type="button" data-command-palette-close aria-label="Close search"></button>
        <section class="command-palette__panel surface-card" role="dialog" aria-modal="true" aria-label="Global search">
            <div class="command-search">
                <x-admin.icon name="search" size="20" />
                <input type="search" autocomplete="off" spellcheck="false" placeholder="Search Orbit administration…" data-command-input>
                <kbd>Esc</kbd>
            </div>
            <div class="command-body" data-command-body>
                <div class="command-empty">
                    <span class="command-empty__icon"><x-admin.icon name="search" size="24" /></span>
                    <strong>Search across Orbit</strong>
                    <p>Users, Circles, SOS incidents, reports, support, billing, audits and system incidents—permission filtered by the backend.</p>
                </div>
            </div>
        </section>
    </div>

    <div id="toast-region" class="toast-region" aria-live="polite" aria-atomic="true"></div>
    <script type="module" src="{{ asset('admin-ui/js/pages/dashboard.js') }}"></script>
</body>
</html>
