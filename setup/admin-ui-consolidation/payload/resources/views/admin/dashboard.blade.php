@extends('admin.layouts.app')

@section('title', 'Dashboard')

@section('content')
<section class="orbit-page" data-orbit-view="dashboard">
    <nav class="orbit-breadcrumbs" aria-label="Breadcrumb"><span>Workspace</span><span aria-hidden="true">/</span><strong>Dashboard</strong></nav>

    <div class="orbit-page__heading">
        <div>
            <p class="orbit-eyebrow">Operations overview</p>
            <h1>Dashboard</h1>
            <p>Real platform signals for the current administrator scope. No synthetic metrics.</p>
        </div>
        <button class="orbit-button orbit-button--quiet" type="button" data-dashboard-reload>Refresh</button>
    </div>

    <div class="orbit-dashboard-state orbit-dashboard-state--loading" data-dashboard-loading aria-live="polite">
        <div class="orbit-metric-grid" aria-hidden="true">
            @for ($i = 0; $i < 8; $i++)
                <div class="orbit-metric-card orbit-skeleton-card"><span></span><strong></strong><small></small></div>
            @endfor
        </div>
    </div>

    <div class="orbit-state orbit-state--error" data-dashboard-error hidden>
        <span class="orbit-state__icon" aria-hidden="true">!</span>
        <strong>Dashboard data could not be loaded.</strong>
        <p data-dashboard-error-message>The server did not return the operational summary.</p>
        <button class="orbit-button orbit-button--quiet" type="button" data-dashboard-retry>Try again</button>
    </div>

    <div data-dashboard-content hidden>
        <div class="orbit-metric-grid" data-dashboard-metrics></div>

        <div class="orbit-dashboard-grid">
            <article class="orbit-panel orbit-panel--span-2">
                <div class="orbit-panel__header">
                    <div><p class="orbit-eyebrow">Engagement</p><h2>Product activity</h2></div>
                    <span>Server aggregates</span>
                </div>
                <div class="orbit-stat-list" data-dashboard-engagement></div>
            </article>

            <article class="orbit-panel">
                <div class="orbit-panel__header">
                    <div><p class="orbit-eyebrow">Safety</p><h2>Safety & trust</h2></div>
                    <span>Priority</span>
                </div>
                <div class="orbit-stat-list" data-dashboard-safety></div>
            </article>

            <article class="orbit-panel">
                <div class="orbit-panel__header">
                    <div><p class="orbit-eyebrow">Platform</p><h2>Operational health</h2></div>
                    <span>Live signals</span>
                </div>
                <div class="orbit-health-list" data-dashboard-health></div>
            </article>
        </div>
    </div>
</section>
@endsection
