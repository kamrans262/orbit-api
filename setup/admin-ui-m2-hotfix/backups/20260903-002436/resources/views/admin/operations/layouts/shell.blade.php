<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-orbit-admin-ui>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Orbit Admin') · Orbit</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="orbit-admin-shell-body">
<div class="orbit-shell">
    <aside class="orbit-shell__sidebar" aria-label="Admin navigation">
        <a class="orbit-brand" href="/admin" aria-label="Orbit Admin home">
            <span class="orbit-brand__mark" aria-hidden="true">O</span>
            <span><strong>Orbit</strong><small>Admin Console</small></span>
        </a>
        <nav class="orbit-nav">
            <a href="/admin">Dashboard</a>
            <a href="{{ route('admin.ui.operations.users.index') }}" @class(['is-active' => request()->routeIs('admin.ui.operations.users.*')])>Users</a>
            <a href="{{ route('admin.ui.operations.circles.index') }}" @class(['is-active' => request()->routeIs('admin.ui.operations.circles.*')])>Circles</a>
            <span class="orbit-nav__coming">Safety / SOS</span>
            <span class="orbit-nav__coming">Moderation & Reports</span>
        </nav>
        <div class="orbit-shell__privacy">Operational metadata only.<br>Encrypted content stays encrypted.</div>
    </aside>
    <main class="orbit-shell__main">
        <header class="orbit-shell__topbar">
            <button class="orbit-mobile-nav" type="button" data-orbit-sidebar-toggle aria-label="Toggle navigation">☰</button>
            <div class="orbit-topbar__context">Core Operations <span>Milestone 2</span></div>
            <div class="orbit-env-badge">{{ strtoupper(app()->environment()) }}</div>
        </header>
        <div class="orbit-shell__content">@yield('content')</div>
    </main>
</div>
<div class="orbit-toast-region" aria-live="polite" aria-atomic="true" data-orbit-toasts></div>
</body>
</html>
