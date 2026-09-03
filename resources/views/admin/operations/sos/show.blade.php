@extends('admin.layouts.app')

@section('title', 'SOS Incident')

@section('content')
<section class="orbit-page orbit-safety-page" data-orbit-view="sos-show" data-sos-id="{{ request()->route('sosId') }}">
    <nav class="orbit-breadcrumbs" aria-label="Breadcrumb"><a href="{{ route('admin.console.operations.sos.index') }}">Safety / SOS</a><span aria-hidden="true">/</span><strong>Incident</strong></nav>
    <a class="orbit-backlink" href="{{ route('admin.console.operations.sos.index') }}">← Back to Safety Command Center</a>

    <div class="orbit-detail-skeleton" data-loading aria-label="Loading SOS incident" aria-live="polite"><span></span><span></span><span></span></div>
    <div class="orbit-state orbit-state--error" data-error hidden><span class="orbit-state__icon" aria-hidden="true">!</span><strong>SOS incident could not be loaded.</strong><p data-error-message></p><button class="orbit-button orbit-button--quiet" data-retry type="button">Try again</button></div>

    <div data-sos-content hidden>
        <div class="orbit-safety-banner orbit-safety-banner--detail" role="status">
            <div class="orbit-safety-banner__mark" aria-hidden="true">SOS</div>
            <div><p class="orbit-eyebrow">Mission-critical incident</p><strong data-sos-title>SOS incident</strong><p class="orbit-mono" data-sos-id-label></p></div>
            <div class="orbit-live-state" data-live-state><span aria-hidden="true"></span><strong>Auto refresh</strong><small>8s cadence</small></div>
        </div>

        <div class="orbit-page__heading orbit-page__heading--detail">
            <div><p class="orbit-eyebrow">Command center</p><h1 data-sos-heading>Incident</h1><p data-sos-subtitle>Privacy-safe operational view</p></div>
            <div class="orbit-actionbar"><span data-sos-status></span><span data-sos-operational-status></span><button class="orbit-button orbit-button--quiet" data-action="assign" type="button">Assign</button><button class="orbit-button orbit-button--quiet" data-action="classify" type="button">Classify</button><button class="orbit-button orbit-button--quiet" data-action="add-note" type="button">Add note</button><button class="orbit-button orbit-button--danger" data-action="close" type="button">Close operationally</button></div>
        </div>

        <div class="orbit-detail-grid orbit-safety-detail-grid">
            <article class="orbit-panel orbit-panel--wide"><div class="orbit-panel__header"><div><p class="orbit-eyebrow">Incident</p><h2>Operational overview</h2></div><span>Privacy-safe</span></div><dl class="orbit-kv-grid" data-sos-overview></dl></article>

            <article class="orbit-panel"><div class="orbit-panel__header"><div><h2>Escalation</h2><p>Server-authoritative progression and fallback state.</p></div></div><div class="orbit-safety-stage" data-sos-stage></div><div class="orbit-timeline" data-sos-escalations></div></article>
            <article class="orbit-panel"><div class="orbit-panel__header"><div><h2>Delivery health</h2><p>Provider-safe metadata only.</p></div></div><dl class="orbit-compact-kv" data-sos-delivery></dl></article>

            <article class="orbit-panel orbit-panel--wide"><div class="orbit-panel__header"><div><h2>Responder timeline</h2><p>Acknowledgement and engagement metadata. Precise responder locations are not rendered here.</p></div></div><div class="orbit-subtable" data-sos-responders></div></article>

            <article class="orbit-panel"><div class="orbit-panel__header"><div><h2>Signal health</h2><p>Network, location-update and recording-upload health without sensitive payloads.</p></div></div><dl class="orbit-compact-kv" data-sos-signal-health></dl></article>
            <article class="orbit-panel"><div class="orbit-panel__header"><div><h2>Classification</h2><p>Internal safety classifications are reason-audited.</p></div></div><div class="orbit-flag-grid" data-sos-classification></div></article>

            <article class="orbit-panel"><div class="orbit-panel__header"><div><h2>Internal notes</h2><p>Never consumer-visible.</p></div></div><div data-sos-notes></div></article>
            <article class="orbit-panel"><div class="orbit-panel__header"><div><h2>Incident export</h2><p>Exports are privacy-preserving, permissioned and may require recent reauthentication.</p></div></div><button class="orbit-button orbit-button--quiet orbit-button--full" data-action="export" type="button">Generate authorized export</button><div class="orbit-export-status" data-export-status hidden></div></article>

            <article class="orbit-panel orbit-panel--wide orbit-sensitive-panel"><div class="orbit-panel__header"><div><p class="orbit-eyebrow">Sensitive access</p><h2>Exceptional safety data</h2><p>Precise location and encrypted recording references remain masked by default. Reveal requires separate permission, a reason-coded purpose, recent reauthentication when required, and immutable audit history.</p></div><span class="orbit-sensitive-lock">Restricted</span></div>
                <div class="orbit-sensitive-actions"><button class="orbit-button orbit-button--quiet" data-action="reveal-location" type="button">Reveal precise location</button><button class="orbit-button orbit-button--quiet" data-action="reveal-recording" type="button">Reveal encrypted recording reference</button><button class="orbit-button orbit-button--quiet" data-action="access-history" type="button">View sensitive access history</button><button class="orbit-button orbit-button--quiet" data-action="clear-sensitive" type="button" hidden>Clear revealed data</button></div>
                <div class="orbit-sensitive-reveal" data-sensitive-reveal hidden aria-live="polite"></div>
                <div class="orbit-access-history" data-access-history hidden></div>
            </article>
        </div>
    </div>
</section>
@endsection
