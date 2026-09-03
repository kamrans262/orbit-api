@extends('admin.layouts.app')

@section('title', 'User Operations')

@section('content')
<section class="orbit-page" data-orbit-view="user-show" data-user-id="{{ request()->route('userId') }}">
    <nav class="orbit-breadcrumbs" aria-label="Breadcrumb"><a href="{{ route('admin.console.operations.users.index') }}">Users</a><span aria-hidden="true">/</span><strong>User detail</strong></nav>
    <a class="orbit-backlink" href="{{ route('admin.console.operations.users.index') }}">← Back to users</a>

    <div class="orbit-detail-skeleton" data-loading aria-label="Loading user" aria-live="polite"><span></span><span></span><span></span></div>
    <div class="orbit-state orbit-state--error" data-error hidden><span class="orbit-state__icon" aria-hidden="true">!</span><strong>User could not be loaded.</strong><p data-error-message></p><button class="orbit-button orbit-button--quiet" data-retry type="button">Try again</button></div>

    <div data-user-content hidden>
        <div class="orbit-page__heading orbit-page__heading--detail">
            <div><p class="orbit-eyebrow">User operations</p><h1 data-user-name>User</h1><p class="orbit-mono" data-user-id-label></p></div>
            <div class="orbit-actionbar"><span data-user-status></span><button class="orbit-button orbit-button--quiet" data-action="logout-all" type="button">Force logout</button><button class="orbit-button orbit-button--danger" data-action="suspend" type="button">Suspend</button><button class="orbit-button orbit-button--success" data-action="activate" type="button">Reactivate</button></div>
        </div>

        <div class="orbit-detail-grid">
            <article class="orbit-panel orbit-panel--wide"><div class="orbit-panel__header"><div><p class="orbit-eyebrow">Identity</p><h2>Account overview</h2></div><span>Privacy-safe</span></div><dl class="orbit-kv-grid" data-user-overview></dl></article>
            <article class="orbit-panel"><div class="orbit-panel__header"><h2>Operational controls</h2></div><div data-user-controls class="orbit-control-list"></div></article>
            <article class="orbit-panel orbit-panel--wide"><div class="orbit-panel__header"><div><h2>Devices</h2><p>Safe metadata only. Cryptographic secrets are never rendered.</p></div></div><div class="orbit-subtable" data-devices></div></article>
            <article class="orbit-panel orbit-panel--wide"><div class="orbit-panel__header"><div><h2>Sessions</h2><p>Revoke selected consumer sessions without exposing access tokens.</p></div></div><div class="orbit-subtable" data-sessions></div></article>
            <article class="orbit-panel"><div class="orbit-panel__header"><h2>Internal notes</h2></div><div data-notes></div><button class="orbit-button orbit-button--quiet orbit-button--full" type="button" data-action="add-note">Add note</button></article>
            <article class="orbit-panel"><div class="orbit-panel__header"><h2>Tags</h2></div><div class="orbit-tags" data-tags></div><button class="orbit-button orbit-button--quiet orbit-button--full" type="button" data-action="add-tag">Add tag</button></article>
        </div>
    </div>
</section>
@endsection
