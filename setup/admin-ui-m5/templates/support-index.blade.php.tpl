@extends('__ORBIT_LAYOUT__')

@section('__ORBIT_SECTION__')
<section class="orbit-page orbit-m5-page" data-orbit-view="support-index">
    <nav class="orbit-breadcrumbs" aria-label="Breadcrumb"><strong>Support</strong></nav>

    <div class="orbit-page__heading">
        <div>
            <p class="orbit-eyebrow">Customer operations</p>
            <h1>Support</h1>
            <p>Manage customer cases, SLA risk, assignments and audited support actions from one privacy-safe queue.</p>
        </div>
        <div class="orbit-actionbar">
            <button class="orbit-button orbit-button--quiet" type="button" data-m5-refresh>Refresh</button>
            <button class="orbit-button" type="button" data-m5-create-ticket hidden>New support case</button>
        </div>
    </div>

    <div class="orbit-m5-summary-grid" aria-label="Support queue summary">
        <article class="orbit-m5-summary"><span>Visible queue</span><strong data-m5-total>--</strong><small>Server-filtered records</small></article>
        <article class="orbit-m5-summary"><span>SLA breached</span><strong data-m5-sla-count>--</strong><small>Current page signal</small></article>
        <article class="orbit-m5-summary"><span>Unassigned</span><strong data-m5-unassigned-count>--</strong><small>Current page signal</small></article>
    </div>

    <form class="orbit-m5-filters" data-m5-ticket-filters>
        <label class="orbit-m5-search">Search<input name="search" type="search" autocomplete="off" placeholder="Ticket, user, category or subject"></label>
        <label>Status<input name="status" type="text" placeholder="open, resolved..."></label>
        <label>Priority<input name="priority" type="text" placeholder="high, normal..."></label>
        <label class="orbit-m5-check"><input name="unassigned" value="1" type="checkbox"><span>Unassigned only</span></label>
        <label class="orbit-m5-check"><input name="sla_breached" value="1" type="checkbox"><span>SLA breached</span></label>
        <label>Rows<select name="per_page"><option value="20">20</option><option value="50">50</option><option value="100">100</option></select></label>
        <button class="orbit-button orbit-button--quiet" type="submit">Apply</button>
    </form>

    <div class="orbit-m5-skeleton" data-m5-loading aria-live="polite" aria-label="Loading support queue"><span></span><span></span><span></span><span></span></div>

    <div class="orbit-state orbit-state--error" data-m5-error hidden>
        <span class="orbit-state__icon" aria-hidden="true">!</span>
        <strong>Support queue could not be loaded.</strong>
        <p data-m5-error-message>The server did not return the support queue.</p>
        <button class="orbit-button orbit-button--quiet" type="button" data-m5-retry>Try again</button>
    </div>

    <div data-m5-content hidden>
        <div class="orbit-m5-toolbar">
            <div><strong>Support tickets</strong><p class="orbit-m5-muted">Search, filters and pagination remain server-side.</p></div>
            <span data-m5-count>0 total</span>
        </div>

        <div class="orbit-m5-table-wrap" data-m5-table-wrap>
            <table class="orbit-m5-table">
                <thead><tr><th>Ticket</th><th>User</th><th>Category</th><th>Priority</th><th>Status</th><th>Assignee</th><th>SLA</th><th>Updated</th></tr></thead>
                <tbody data-m5-ticket-rows></tbody>
            </table>
        </div>

        <div class="orbit-m5-empty" data-m5-empty hidden><strong>No support tickets match this view.</strong><p>Adjust the filters or refresh the queue.</p></div>
        <div class="orbit-m5-pagination" data-m5-pagination aria-label="Support queue pagination"></div>
    </div>
</section>
@endsection
