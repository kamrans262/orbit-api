@extends('__ORBIT_LAYOUT__')

@section('__ORBIT_SECTION__')
<section class="orbit-page orbit-m5-page" data-orbit-view="support-show" data-ticket-id="{{ $ticketId }}">
    <nav class="orbit-breadcrumbs" aria-label="Breadcrumb"><a href="{{ route('admin.console.operations.support.index') }}">Support</a><span aria-hidden="true">/</span><strong>Ticket</strong></nav>
    <a class="orbit-backlink" href="{{ route('admin.console.operations.support.index') }}">Back to Support</a>

    <div class="orbit-m5-skeleton" data-m5-loading aria-live="polite" aria-label="Loading support ticket"><span></span><span></span><span></span><span></span></div>

    <div class="orbit-state orbit-state--error" data-m5-error hidden>
        <span class="orbit-state__icon" aria-hidden="true">!</span>
        <strong>Support ticket could not be loaded.</strong>
        <p data-m5-error-message>The server did not return this support ticket.</p>
        <button class="orbit-button orbit-button--quiet" type="button" data-m5-retry>Try again</button>
    </div>

    <div data-m5-content hidden>
        <div class="orbit-page__heading orbit-page__heading--detail">
            <div>
                <p class="orbit-eyebrow">Support case</p>
                <h1 data-m5-ticket-heading>Ticket</h1>
                <p data-m5-ticket-subtitle>Customer support operational view</p>
            </div>
            <div class="orbit-actionbar" data-m5-actions>
                <span data-m5-status></span>
                <span data-m5-priority></span>
                <button class="orbit-button orbit-button--quiet" type="button" data-m5-action="assign" hidden>Assign</button>
                <button class="orbit-button orbit-button--quiet" type="button" data-m5-action="reply" hidden>Reply</button>
                <button class="orbit-button orbit-button--quiet" type="button" data-m5-action="note" hidden>Add internal note</button>
                <button class="orbit-button orbit-button--quiet" type="button" data-m5-action="link" hidden>Link record</button>
                <button class="orbit-button orbit-button--quiet" type="button" data-m5-action="update" hidden>Change status / priority</button>
                <button class="orbit-button orbit-button--danger" type="button" data-m5-action="escalate" hidden>Escalate</button>
                <button class="orbit-button orbit-button--quiet" type="button" data-m5-action="resolve" hidden>Resolve</button>
                <button class="orbit-button orbit-button--quiet" type="button" data-m5-refresh>Refresh</button>
            </div>
        </div>

        <div class="orbit-m5-detail-grid">
            <article class="orbit-panel orbit-m5-span-2">
                <div class="orbit-panel__header"><div><p class="orbit-eyebrow">Case overview</p><h2>Operational context</h2><p>Only server-authorized support metadata is rendered.</p></div><span class="orbit-m5-privacy-chip">Privacy-safe</span></div>
                <dl class="orbit-kv-grid" data-m5-overview></dl>
            </article>

            <article class="orbit-panel orbit-m5-span-2">
                <div class="orbit-panel__header"><div><h2>Conversation</h2><p>Customer-visible replies and case communication history.</p></div></div>
                <div class="orbit-m5-conversation" data-m5-conversation></div>
            </article>

            <article class="orbit-panel">
                <div class="orbit-panel__header"><div><p class="orbit-eyebrow">Internal only</p><h2>Internal notes</h2><p>Internal support notes are never consumer-visible unless intentionally converted into an external communication.</p></div><span class="orbit-m5-internal-chip">Private</span></div>
                <div class="orbit-m5-stack" data-m5-notes></div>
            </article>

            <article class="orbit-panel">
                <div class="orbit-panel__header"><div><h2>Related records</h2><p>Audited links to authorized account, moderation, billing or privacy records.</p></div></div>
                <div class="orbit-m5-stack" data-m5-links></div>
            </article>

            <article class="orbit-panel">
                <div class="orbit-panel__header"><div><h2>Attachments</h2><p>Metadata only. Sensitive payloads are not embedded into the admin page source.</p></div></div>
                <div class="orbit-m5-stack" data-m5-attachments></div>
            </article>

            <article class="orbit-panel">
                <div class="orbit-panel__header"><div><h2>User contact history</h2><p>Safe communication metadata across support, warnings, subscriptions, enforcement, appeals and privacy workflows where authorized.</p></div></div>
                <div class="orbit-m5-stack" data-m5-contact-history></div>
            </article>

            <article class="orbit-panel orbit-m5-span-2">
                <div class="orbit-panel__header"><div><h2>Case timeline</h2><p>Status, assignment and SLA events from the support domain.</p></div></div>
                <div class="orbit-m5-timeline" data-m5-timeline></div>
            </article>
        </div>
    </div>
</section>
@endsection
