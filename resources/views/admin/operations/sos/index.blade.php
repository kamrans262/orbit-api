@extends('admin.layouts.app')

@section('title', 'Safety / SOS')

@section('content')
<section class="orbit-page orbit-safety-page" data-orbit-view="sos-index" data-detail-base="{{ url('/admin/operations/sos') }}">
    <nav class="orbit-breadcrumbs" aria-label="Breadcrumb"><span>Core operations</span><span aria-hidden="true">/</span><strong>Safety / SOS</strong></nav>

    <div class="orbit-safety-banner" role="status" aria-live="polite">
        <div class="orbit-safety-banner__mark" aria-hidden="true">SOS</div>
        <div><p class="orbit-eyebrow">Mission-critical</p><strong>Safety Command Center</strong><p>Active incidents refresh automatically while this page is visible. Sensitive location and recording data are never shown in this directory.</p></div>
        <div class="orbit-live-state" data-live-state><span aria-hidden="true"></span><strong>Auto refresh</strong><small>10s cadence</small></div>
    </div>

    <div class="orbit-page__heading">
        <div><p class="orbit-eyebrow">Safety operations</p><h1>SOS incidents</h1><p>Operate active incidents and review retained history using server-side filters and privacy-safe metadata.</p></div>
        <button class="orbit-button orbit-button--quiet" type="button" data-reload>Refresh</button>
    </div>

    <div class="orbit-segmented" role="group" aria-label="Incident view">
        <button class="is-active" type="button" data-sos-scope="active" aria-pressed="true">Active incidents</button>
        <button type="button" data-sos-scope="history" aria-pressed="false">Incident history</button>
    </div>

    <form class="orbit-filterbar orbit-filterbar--safety" data-sos-filters role="search" aria-label="Filter SOS incidents">
        <input type="hidden" name="scope" value="active" data-sos-scope-input>
        <label class="orbit-search-field orbit-safety-search"><span class="sr-only">Search incidents</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z"/></svg><input name="search" type="search" autocomplete="off" placeholder="Search incident, user, Circle…"></label>
        <label class="orbit-select-field"><span>Escalation</span><select name="escalation_stage"><option value="">Any stage</option><option value="0">Stage 0</option><option value="1">Stage 1</option><option value="2">Stage 2</option><option value="3">Stage 3</option></select></label>
        <label class="orbit-select-field"><span>Assignment</span><select name="unassigned"><option value="">All</option><option value="1">Unassigned</option><option value="0">Assigned</option></select></label>
        <label class="orbit-select-field"><span>Rows</span><select name="per_page"><option>25</option><option>50</option><option>100</option></select></label>

        <details class="orbit-advanced-filters">
            <summary>More filters</summary>
            <div>
                <label class="orbit-select-field"><span>Fallback</span><select name="fallback_used"><option value="">Any</option><option value="1">Used</option><option value="0">Not used</option></select></label>
                <label class="orbit-select-field"><span>False alarm</span><select name="false_alarm"><option value="">Any</option><option value="1">Yes</option><option value="0">No</option></select></label>
                <label class="orbit-select-field"><span>Abuse flag</span><select name="abuse_flag"><option value="">Any</option><option value="1">Flagged</option><option value="0">Not flagged</option></select></label>
                <label class="orbit-select-field"><span>Country</span><input name="country" maxlength="2" inputmode="text" autocomplete="off" placeholder="e.g. PK"></label>
            </div>
        </details>
    </form>

    <div class="orbit-safety-summary" data-sos-summary hidden aria-label="SOS summary"></div>

    <div class="orbit-data-card orbit-data-card--critical">
        <div class="orbit-table-skeleton" data-loading aria-label="Loading SOS incidents" aria-live="polite">
            @for ($i = 0; $i < 6; $i++)<div><span></span><span></span><span></span><span></span></div>@endfor
        </div>
        <div class="orbit-state orbit-state--error" data-error hidden><span class="orbit-state__icon" aria-hidden="true">!</span><strong>SOS incidents could not be loaded.</strong><p data-error-message></p><button class="orbit-button orbit-button--quiet" data-retry type="button">Try again</button></div>
        <div class="orbit-state" data-empty hidden><span class="orbit-state__icon" aria-hidden="true">✓</span><strong data-empty-title>No active incidents match these filters.</strong><p data-empty-copy>Change the filters or switch to incident history.</p></div>
        <div class="orbit-table-wrap" data-table-wrap hidden>
            <table class="orbit-table orbit-safety-table"><thead><tr><th>Incident</th><th>State</th><th>User / Circle</th><th>Escalation</th><th>Response</th><th>Assignment</th><th>Activated</th><th><span class="sr-only">Open</span></th></tr></thead><tbody data-sos-body></tbody></table>
            <div class="orbit-pagination"><p data-page-summary aria-live="polite"></p><div><button class="orbit-button orbit-button--quiet" type="button" data-prev>Previous</button><button class="orbit-button orbit-button--quiet" type="button" data-next>Next</button></div></div>
        </div>
    </div>
</section>
@endsection
