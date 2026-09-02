<!doctype html>
<html lang="en" data-theme="system">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>@yield('title', 'Administrator access') · Orbit</title>
    <link rel="stylesheet" href="{{ asset('admin-ui/css/admin.css') }}">
</head>
<body class="admin-auth">
    <div class="auth-backdrop" aria-hidden="true">
        <span class="auth-orb auth-orb--one"></span>
        <span class="auth-orb auth-orb--two"></span>
        <span class="auth-grid"></span>
    </div>

    <main class="auth-shell">
        <section class="auth-story" aria-label="Orbit administration">
            <x-admin.logo />
            <div class="auth-story__copy">
                <span class="eyebrow">Orbit operations console</span>
                <h1>Control the platform without compromising trust.</h1>
                <p>Safety, privacy, operations and growth tooling in one secure workspace.</p>
            </div>
            <div class="auth-trust-row" aria-label="Security properties">
                <span><x-admin.icon name="shield" size="17" /> Mandatory MFA</span>
                <span><x-admin.icon name="lock" size="17" /> Least privilege</span>
                <span><x-admin.icon name="activity" size="17" /> Full audit trail</span>
            </div>
        </section>

        <section class="auth-panel">
            <button class="icon-button auth-theme-toggle" type="button" data-theme-toggle aria-label="Toggle appearance">
                <span data-theme-icon="light"><x-admin.icon name="sun" /></span>
                <span data-theme-icon="dark"><x-admin.icon name="moon" /></span>
            </button>
            <div class="auth-card surface-card surface-card--elevated">
                @yield('content')
            </div>
            <p class="auth-footnote">Authorized Orbit administrators only. Access is logged and monitored.</p>
        </section>
    </main>

    <div id="toast-region" class="toast-region" aria-live="polite" aria-atomic="true"></div>
    <script type="module" src="@yield('page-script')"></script>
</body>
</html>
