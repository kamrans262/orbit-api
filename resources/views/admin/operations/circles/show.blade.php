@extends('admin.layouts.app')

@section('title', 'Circle Operations')

@section('content')
<section class="orbit-page" data-orbit-view="circle-show" data-circle-id="{{ request()->route('circleId') }}">
    <nav class="orbit-breadcrumbs" aria-label="Breadcrumb"><a href="{{ route('admin.console.operations.circles.index') }}">Circles</a><span aria-hidden="true">/</span><strong>Circle detail</strong></nav>
    <a class="orbit-backlink" href="{{ route('admin.console.operations.circles.index') }}">← Back to Circles</a>

    <div class="orbit-detail-skeleton" data-loading aria-label="Loading Circle" aria-live="polite"><span></span><span></span><span></span></div>
    <div class="orbit-state orbit-state--error" data-error hidden><span class="orbit-state__icon" aria-hidden="true">!</span><strong>Circle could not be loaded.</strong><p data-error-message></p><button class="orbit-button orbit-button--quiet" data-retry type="button">Try again</button></div>

    <div data-circle-content hidden>
        <div class="orbit-page__heading orbit-page__heading--detail">
            <div><p class="orbit-eyebrow">Circle operations</p><h1 data-circle-name>Circle</h1><p class="orbit-mono" data-circle-id-label></p></div>
            <div class="orbit-actionbar"><span data-circle-status></span><button class="orbit-button orbit-button--quiet" data-action="freeze" type="button">Freeze</button><button class="orbit-button orbit-button--quiet" data-action="archive" type="button">Archive</button><button class="orbit-button orbit-button--success" data-action="restore" type="button">Restore</button></div>
        </div>

        <div class="orbit-detail-grid">
            <article class="orbit-panel orbit-panel--wide"><div class="orbit-panel__header"><div><p class="orbit-eyebrow">Metadata</p><h2>Circle overview</h2></div><span>Operational only</span></div><dl class="orbit-kv-grid" data-circle-overview></dl></article>
            <article class="orbit-panel"><div class="orbit-panel__header"><h2>Controls</h2></div><div data-circle-controls class="orbit-control-list"></div></article>
            <article class="orbit-panel orbit-panel--wide"><div class="orbit-panel__header"><div><h2>Members</h2><p>Enforcement removal is unavailable for the Circle owner.</p></div></div><div class="orbit-subtable" data-members></div></article>
            <article class="orbit-panel"><div class="orbit-panel__header"><h2>Internal notes</h2></div><div data-notes></div><button class="orbit-button orbit-button--quiet orbit-button--full" type="button" data-action="add-note">Add note</button></article>
            <article class="orbit-panel"><div class="orbit-panel__header"><h2>Tags</h2></div><div class="orbit-tags" data-tags></div><button class="orbit-button orbit-button--quiet orbit-button--full" type="button" data-action="add-tag">Add tag</button></article>
        </div>
    </div>
</section>
@endsection
