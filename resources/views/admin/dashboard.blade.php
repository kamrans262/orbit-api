@extends('admin.layouts.app')

@section('title', 'Dashboard')
@section('page', 'dashboard')

@section('content')
    <section class="page-heading page-heading--dashboard">
        <div>
            <span class="eyebrow">Operations overview</span>
            <h1>Good <span data-daypart>day</span>, <span data-greeting-name>Administrator</span>.</h1>
            <p>Here’s the latest Orbit business, safety and platform health snapshot.</p>
        </div>
        <div class="page-heading__meta">
            <span class="live-pill"><span class="live-pill__dot"></span> Live operational data</span>
            <span class="updated-at" data-dashboard-updated>Loading snapshot…</span>
        </div>
    </section>

    <section class="stats-grid" aria-label="Key platform metrics">
        @foreach([
            ['key' => 'users.total', 'label' => 'Total users', 'icon' => 'users', 'tone' => 'violet'],
            ['key' => 'users.online', 'label' => 'Online now', 'icon' => 'activity', 'tone' => 'green'],
            ['key' => 'circles.active', 'label' => 'Active Circles', 'icon' => 'circles', 'tone' => 'blue'],
            ['key' => 'safety.active_sos', 'label' => 'Active SOS', 'icon' => 'sos', 'tone' => 'red'],
            ['key' => 'backlog.moderation', 'label' => 'Moderation queue', 'icon' => 'shield', 'tone' => 'amber'],
            ['key' => 'backlog.support', 'label' => 'Support backlog', 'icon' => 'support', 'tone' => 'cyan'],
            ['key' => 'subscriptions.mrr_minor', 'label' => 'MRR (minor units)', 'icon' => 'billing', 'tone' => 'purple', 'money' => true],
            ['key' => 'subscriptions.active_subscriptions', 'label' => 'Subscriptions', 'icon' => 'chart', 'tone' => 'teal'],
        ] as $metric)
            <article class="stat-card surface-card" data-stat-card data-stat-key="{{ $metric['key'] }}" data-money="{{ !empty($metric['money']) ? '1' : '0' }}">
                <div class="stat-card__top">
                    <span class="stat-icon stat-icon--{{ $metric['tone'] }}"><x-admin.icon :name="$metric['icon']" /></span>
                    <span class="metric-kicker" data-stat-delta>&nbsp;</span>
                </div>
                <strong class="stat-value"><span class="skeleton skeleton--text skeleton--value" data-stat-value></span></strong>
                <span class="stat-label">{{ $metric['label'] }}</span>
                <div class="stat-accent" aria-hidden="true"></div>
            </article>
        @endforeach
    </section>

    <section class="dashboard-grid dashboard-grid--primary">
        <article class="surface-card dashboard-card engagement-card">
            <div class="card-heading">
                <div><span class="eyebrow">Engagement</span><h2>Today at a glance</h2></div>
                <span class="status-badge status-badge--neutral">Today</span>
            </div>
            <div class="engagement-grid">
                <div class="engagement-metric"><span>Messages routed</span><strong data-engagement="messages_routed"><span class="skeleton skeleton--text"></span></strong><div class="mini-bar"><i data-engagement-bar="messages_routed"></i></div></div>
                <div class="engagement-metric"><span>Moments created</span><strong data-engagement="moments_created"><span class="skeleton skeleton--text"></span></strong><div class="mini-bar"><i data-engagement-bar="moments_created"></i></div></div>
                <div class="engagement-metric"><span>Pings sent</span><strong data-engagement="pings_sent"><span class="skeleton skeleton--text"></span></strong><div class="mini-bar"><i data-engagement-bar="pings_sent"></i></div></div>
            </div>
            <div class="activity-ratio">
                <div class="activity-ratio__ring" data-activity-ring><span data-activity-ratio>—</span></div>
                <div><strong>DAU / MAU</strong><p>A lightweight activity pulse from safe account telemetry.</p></div>
            </div>
        </article>

        <article class="surface-card dashboard-card safety-card">
            <div class="card-heading">
                <div><span class="eyebrow">Safety operations</span><h2>Current safety posture</h2></div>
                <span class="pulse-dot" title="Live"></span>
            </div>
            <div class="safety-hero">
                <span class="safety-hero__icon"><x-admin.icon name="sos" size="27" /></span>
                <div><strong data-sos-active><span class="skeleton skeleton--text skeleton--value"></span></strong><span>Active SOS incidents</span></div>
            </div>
            <div class="safety-list">
                <div><span>SOS activations today</span><strong data-sos-today>—</strong></div>
                <div><span>Moderation backlog</span><strong data-moderation-backlog>—</strong></div>
                <div><span>Support backlog</span><strong data-support-backlog>—</strong></div>
            </div>
            <button class="soft-action" type="button" data-planned-module="SOS Command Center" data-permission="sos.view">Open command center <x-admin.icon name="arrow-right" size="16" /></button>
        </article>
    </section>

    <section class="dashboard-grid dashboard-grid--secondary">
        <article class="surface-card dashboard-card health-card">
            <div class="card-heading">
                <div><span class="eyebrow">System health</span><h2>Platform services</h2></div>
                <span class="health-summary" data-health-summary><span class="status-dot status-dot--muted"></span> Checking</span>
            </div>
            <div class="health-grid" data-health-grid>
                @foreach(['Database', 'API', 'Queues', 'Notifications', 'Media', 'Realtime'] as $health)
                    <div class="health-item is-loading">
                        <span class="health-item__icon"><x-admin.icon name="server" size="18" /></span>
                        <div><strong>{{ $health }}</strong><small><span class="skeleton skeleton--text"></span></small></div>
                        <span class="health-indicator"></span>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="surface-card dashboard-card growth-card">
            <div class="card-heading">
                <div><span class="eyebrow">Growth</span><h2>User momentum</h2></div>
            </div>
            <div class="growth-numbers">
                <div><span>Today</span><strong data-growth="new_today">—</strong></div>
                <div><span>7 days</span><strong data-growth="new_week">—</strong></div>
                <div><span>30 days</span><strong data-growth="new_month">—</strong></div>
            </div>
            <div class="growth-visual" aria-hidden="true">
                <svg viewBox="0 0 320 100" preserveAspectRatio="none">
                    <defs><linearGradient id="growthFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="currentColor" stop-opacity=".22"/><stop offset="100%" stop-color="currentColor" stop-opacity="0"/></linearGradient></defs>
                    <path class="growth-area" d="M0 82 C35 78, 45 55, 78 61 S125 80, 155 52 S205 28, 235 39 S275 23, 320 16 L320 100 L0 100Z" fill="url(#growthFill)"/>
                    <path class="growth-line" d="M0 82 C35 78, 45 55, 78 61 S125 80, 155 52 S205 28, 235 39 S275 23, 320 16" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                </svg>
            </div>
            <div class="growth-foot"><span>Active devices</span><strong data-active-devices>—</strong></div>
        </article>
    </section>

    <section class="dashboard-grid dashboard-grid--bottom">
        <article class="surface-card dashboard-card integrations-card">
            <div class="card-heading">
                <div><span class="eyebrow">Integrations</span><h2>Provider health</h2></div>
            </div>
            <div class="integration-list" data-integration-list>
                @for($i = 0; $i < 4; $i++)
                    <div class="integration-row"><span class="integration-logo skeleton skeleton--circle"></span><div><span class="skeleton skeleton--text"></span><small class="skeleton skeleton--text skeleton--short"></small></div></div>
                @endfor
            </div>
        </article>

        <article class="surface-card dashboard-card session-card">
            <div class="card-heading"><div><span class="eyebrow">Your session</span><h2>Security context</h2></div><span class="status-badge status-badge--secure"><x-admin.icon name="shield" size="14" /> MFA</span></div>
            <div class="session-security-list">
                <div><span>Roles</span><strong data-session-roles>—</strong></div>
                <div><span>Permissions</span><strong data-session-permissions>—</strong></div>
                <div><span>Session expires</span><strong data-session-expiry-card>—</strong></div>
                <div><span>Environment</span><strong data-environment>—</strong></div>
            </div>
        </article>
    </section>
@endsection
