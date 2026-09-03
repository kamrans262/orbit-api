<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-orbit-admin-ui>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Orbit Admin') · Orbit</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="orbit-admin-shell-body orbit-admin-shell-body--foundation">
<div class="orbit-shell orbit-shell--foundation">
    <aside class="orbit-shell__sidebar" aria-label="Admin navigation">
        <a class="orbit-brand" href="/admin" aria-label="Orbit Administration dashboard">
            <span class="orbit-brand__planet" aria-hidden="true"><span></span></span>
            <span class="orbit-brand__copy"><strong>Orbit</strong><small>ADMINISTRATION</small></span>
            <span class="orbit-brand__menu" aria-hidden="true">☰</span>
        </a>

        <nav class="orbit-nav orbit-nav--foundation">
            <p class="orbit-nav__label">WORKSPACE</p>
            <a class="orbit-nav__item" href="/admin"><span class="orbit-nav__icon" aria-hidden="true">▦</span><span>Dashboard</span></a>

            <p class="orbit-nav__label">OPERATIONS</p>
            <a class="orbit-nav__item" href="{{ route('admin.ui.operations.users.index') }}" @class(['is-active' => request()->routeIs('admin.ui.operations.users.*')])><span class="orbit-nav__icon" aria-hidden="true">♙</span><span>Users</span></a>
            <a class="orbit-nav__item" href="{{ route('admin.ui.operations.circles.index') }}" @class(['is-active' => request()->routeIs('admin.ui.operations.circles.*')])><span class="orbit-nav__icon" aria-hidden="true">◉</span><span>Circles</span></a>
            <span class="orbit-nav__item orbit-nav__coming"><span class="orbit-nav__icon" aria-hidden="true">△</span><span>SOS Command</span></span>
            <span class="orbit-nav__item orbit-nav__coming"><span class="orbit-nav__icon" aria-hidden="true">◇</span><span>Moderation</span></span>
            <span class="orbit-nav__item orbit-nav__coming"><span class="orbit-nav__icon" aria-hidden="true">♧</span><span>Support</span></span>

            <p class="orbit-nav__label">PLATFORM</p>
            <span class="orbit-nav__item orbit-nav__coming"><span class="orbit-nav__icon" aria-hidden="true">▭</span><span>Billing</span></span>
            <span class="orbit-nav__item orbit-nav__coming"><span class="orbit-nav__icon" aria-hidden="true">◁</span><span>Communications</span></span>
            <span class="orbit-nav__item orbit-nav__coming"><span class="orbit-nav__icon" aria-hidden="true">⌁</span><span>Analytics</span></span>
            <span class="orbit-nav__item orbit-nav__coming"><span class="orbit-nav__icon" aria-hidden="true">☷</span><span>System</span></span>
        </nav>

        <div class="orbit-shell__attention"><span aria-hidden="true"></span><div><strong>Core Operations</strong><small>Milestone 2 active</small></div></div>
    </aside>

    <main class="orbit-shell__main">
        <header class="orbit-shell__topbar">
            <button class="orbit-mobile-nav" type="button" data-orbit-sidebar-toggle aria-label="Toggle navigation">☰</button>
            <a class="orbit-foundation-search-link" href="/admin?focus=search" aria-label="Open global search on the Admin Dashboard">
                <span aria-hidden="true">⌕</span>
                <span>Search users, Circles, incidents…</span>
                <kbd>Ctrl K</kbd>
            </a>
            <div class="orbit-topbar__tools" aria-label="Admin context">
                <span class="orbit-env-badge">{{ strtoupper(app()->environment()) }}</span>
                <a class="orbit-admin-context" href="/admin" title="Return to Admin Dashboard">
                    <span class="orbit-admin-context__avatar">A</span>
                    <span><strong>Administrator</strong><small>Orbit Admin</small></span>
                </a>
            </div>
        </header>
        <div class="orbit-shell__content">@yield('content')</div>
    </main>
</div>
<div class="orbit-toast-region" aria-live="polite" aria-atomic="true" data-orbit-toasts></div>
</body>
</html>
