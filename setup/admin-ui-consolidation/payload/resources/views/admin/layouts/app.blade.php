<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-orbit-theme="system">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', 'Dashboard') · Orbit Administration</title>
    @vite(['resources/css/admin-console.css', 'resources/js/admin-console/index.js'])
</head>
<body>
    <div class="orbit-auth-gate" data-auth-gate role="status" aria-live="polite">
        <div class="orbit-auth-gate__card">
            <span class="orbit-auth-gate__spinner" aria-hidden="true"></span>
            <div>
                <strong data-auth-gate-title>Verifying administrator session</strong>
                <p data-auth-gate-message>Secure access is validated by the Orbit administrator API.</p>
            </div>
            <div class="orbit-auth-gate__actions" data-auth-gate-actions hidden>
                <button class="orbit-button orbit-button--primary" type="button" data-auth-gate-retry>Retry</button>
                <a class="orbit-button orbit-button--quiet" href="{{ url('/admin/login') }}" data-auth-gate-sign-in>Go to sign in</a>
            </div>
        </div>
    </div>

    <div class="orbit-app-shell" data-orbit-shell data-orbit-canonical-shell="v1" data-orbit-auth-owner="foundation" hidden>
        @include('admin.partials.sidebar')

        <div class="orbit-app-main">
            @include('admin.partials.topbar')

            <main class="orbit-main-content" id="main-content" tabindex="-1">
                @yield('content')
            </main>
        </div>

        <div class="orbit-mobile-scrim" data-sidebar-scrim hidden></div>
        <div class="orbit-toast-region" data-orbit-toasts aria-live="polite" aria-atomic="true"></div>
    </div>

    <dialog class="orbit-search-dialog" data-global-search-dialog aria-labelledby="orbit-search-title">
        <div class="orbit-search-command">
            <div class="orbit-search-command__header">
                <div class="orbit-search-command__input-wrap">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z"/></svg>
                    <label class="sr-only" for="orbit-global-search-input">Search Orbit administration</label>
                    <input id="orbit-global-search-input" data-global-search-input type="search" autocomplete="off" placeholder="Search users, Circles, devices, SOS, reports…">
                    <kbd>Esc</kbd>
                </div>
            </div>
            <div class="orbit-search-command__body" data-global-search-body>
                <div class="orbit-search-hint">
                    <span class="orbit-search-hint__icon">⌘</span>
                    <div><strong>Search Orbit</strong><p>Enter at least 2 characters. Results are permission-filtered by the server.</p></div>
                </div>
            </div>
            <div class="orbit-search-command__footer">
                <span><kbd>↑</kbd><kbd>↓</kbd> Navigate</span>
                <span><kbd>Enter</kbd> Open</span>
                <span>Privacy-safe results</span>
            </div>
        </div>
    </dialog>
</body>
</html>
