<aside class="orbit-sidebar" data-sidebar aria-label="Administrator navigation">
    <div class="orbit-sidebar__brand">
        <a href="{{ route('admin.console.dashboard') }}" class="orbit-brand-link" aria-label="Orbit Administration home">
            <span class="orbit-brand-mark" aria-hidden="true">O</span>
            <span><strong>Orbit</strong><small>Administration</small></span>
        </a>
        <button class="orbit-icon-button orbit-sidebar__close" type="button" data-sidebar-close aria-label="Close navigation">×</button>
    </div>

    <nav class="orbit-sidebar__nav">
        <div class="orbit-nav-group">
            <p class="orbit-nav-label">Workspace</p>
            <a href="{{ route('admin.console.dashboard') }}" class="orbit-nav-item {{ request()->routeIs('admin.console.dashboard*') ? 'is-active' : '' }}">
                <span class="orbit-nav-icon" aria-hidden="true">⌂</span><span>Dashboard</span>
            </a>
        </div>

        <div class="orbit-nav-group">
            <p class="orbit-nav-label">Core operations</p>
            <a href="{{ route('admin.console.operations.users.index') }}" class="orbit-nav-item {{ request()->routeIs('admin.console.operations.users.*') ? 'is-active' : '' }}">
                <span class="orbit-nav-icon" aria-hidden="true">◎</span><span>Users</span>
            </a>
            <a href="{{ route('admin.console.operations.circles.index') }}" class="orbit-nav-item {{ request()->routeIs('admin.console.operations.circles.*') ? 'is-active' : '' }}">
                <span class="orbit-nav-icon" aria-hidden="true">◌</span><span>Circles</span>
            </a>
            <a href="{{ route('admin.console.operations.sos.index') }}" class="orbit-nav-item {{ request()->routeIs('admin.console.operations.sos.*') ? 'is-active' : '' }}">
                <span class="orbit-nav-icon" aria-hidden="true">✚</span><span>Safety / SOS</span><span class="orbit-nav-critical-dot" aria-hidden="true"></span>
            </a>
            <a href="{{ route('admin.console.operations.moderation.index') }}" class="orbit-nav-item {{ request()->routeIs('admin.console.operations.moderation.*') ? 'is-active' : '' }}" @if (request()->routeIs('admin.console.operations.moderation.*')) aria-current="page" @endif>
                <span class="orbit-nav-icon" aria-hidden="true">◇</span><span>Moderation &amp; Reports</span>
            </a>
            <a href="{{ route('admin.console.operations.support.index') }}" class="orbit-nav-item {{ request()->routeIs('admin.console.operations.support.*') ? 'is-active' : '' }}" @if (request()->routeIs('admin.console.operations.support.*')) aria-current="page" @endif>
                <span class="orbit-nav-icon" aria-hidden="true">?</span><span>Support</span>
            </a>
        </div>

        <div class="orbit-nav-group">
            <p class="orbit-nav-label">Platform</p>
            <span class="orbit-nav-item orbit-nav-item--disabled" aria-disabled="true"><span class="orbit-nav-icon" aria-hidden="true">$</span><span>Subscriptions & Payments</span></span>
            <span class="orbit-nav-item orbit-nav-item--disabled" aria-disabled="true"><span class="orbit-nav-icon" aria-hidden="true">▣</span><span>Advertising</span></span>
            <span class="orbit-nav-item orbit-nav-item--disabled" aria-disabled="true"><span class="orbit-nav-icon" aria-hidden="true">◈</span><span>Notifications & Announcements</span></span>
            <span class="orbit-nav-item orbit-nav-item--disabled" aria-disabled="true"><span class="orbit-nav-icon" aria-hidden="true">⌁</span><span>Analytics</span></span>
            <span class="orbit-nav-item orbit-nav-item--disabled" aria-disabled="true"><span class="orbit-nav-icon" aria-hidden="true">⚿</span><span>Privacy & Compliance</span></span>
            <span class="orbit-nav-item orbit-nav-item--disabled" aria-disabled="true"><span class="orbit-nav-icon" aria-hidden="true">◆</span><span>Security</span></span>
            <span class="orbit-nav-item orbit-nav-item--disabled" aria-disabled="true"><span class="orbit-nav-icon" aria-hidden="true">▤</span><span>Content</span></span>
            <span class="orbit-nav-item orbit-nav-item--disabled" aria-disabled="true"><span class="orbit-nav-icon" aria-hidden="true">⚙</span><span>Feature Flags & Configuration</span></span>
            <span class="orbit-nav-item orbit-nav-item--disabled" aria-disabled="true"><span class="orbit-nav-icon" aria-hidden="true">◫</span><span>System Operations</span></span>
            <span class="orbit-nav-item orbit-nav-item--disabled" aria-disabled="true"><span class="orbit-nav-icon" aria-hidden="true">≡</span><span>Audit Logs</span></span>
            <span class="orbit-nav-item orbit-nav-item--disabled" aria-disabled="true"><span class="orbit-nav-icon" aria-hidden="true">♙</span><span>Administrators</span></span>
        </div>

        <div class="orbit-sidebar__principle">
            <span class="orbit-sidebar__principle-dot" aria-hidden="true"></span>
            <div><strong>Privacy-first operations</strong><p>Sensitive data stays masked unless separately authorized.</p></div>
        </div>
    </nav>

    <div class="orbit-sidebar__footer">
        <div class="orbit-environment" title="Current Laravel environment">
            <span class="orbit-environment__dot"></span>
            <span>{{ strtoupper(app()->environment()) }}</span>
        </div>
        <button class="orbit-nav-item orbit-nav-item--button" type="button" data-theme-toggle>
            <span class="orbit-nav-icon" aria-hidden="true">◐</span><span data-theme-label>Theme: System</span>
        </button>
    </div>
</aside>
