@extends('admin.layouts.app')

@section('title', 'Users')

@section('content')
<section class="orbit-page" data-orbit-view="users-index" data-detail-base="{{ url('/admin/operations/users') }}">
    <nav class="orbit-breadcrumbs" aria-label="Breadcrumb"><span>Core operations</span><span aria-hidden="true">/</span><strong>Users</strong></nav>

    <div class="orbit-page__heading">
        <div><p class="orbit-eyebrow">Consumer operations</p><h1>Users</h1><p>Search and inspect accounts using privacy-safe operational metadata.</p></div>
        <button class="orbit-button orbit-button--quiet" type="button" data-reload>Refresh</button>
    </div>

    <form class="orbit-filterbar" data-user-filters role="search" aria-label="Filter users">
        <label class="orbit-search-field"><span class="sr-only">Search users</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z"/></svg><input name="search" type="search" autocomplete="off" placeholder="Search ID, name, email…"></label>
        <label class="orbit-select-field"><span>Status</span><select name="status"><option value="">All statuses</option><option value="active">Active</option><option value="suspended">Suspended</option></select></label>
        <label class="orbit-select-field"><span>Rows</span><select name="per_page"><option>25</option><option>50</option><option>100</option></select></label>
    </form>

    <div class="orbit-data-card">
        <div class="orbit-table-skeleton" data-loading aria-label="Loading users" aria-live="polite">
            @for ($i = 0; $i < 6; $i++)<div><span></span><span></span><span></span><span></span></div>@endfor
        </div>
        <div class="orbit-state orbit-state--error" data-error hidden><span class="orbit-state__icon" aria-hidden="true">!</span><strong>Users could not be loaded.</strong><p data-error-message></p><button class="orbit-button orbit-button--quiet" data-retry type="button">Try again</button></div>
        <div class="orbit-state" data-empty hidden><span class="orbit-state__icon" aria-hidden="true">⌕</span><strong>No users match these filters.</strong><p>Change the search or filters and try again.</p></div>
        <div class="orbit-table-wrap" data-table-wrap hidden>
            <table class="orbit-table"><thead><tr><th>User</th><th>Status</th><th>Country</th><th>Risk</th><th>Last activity</th><th><span class="sr-only">Open</span></th></tr></thead><tbody data-users-body></tbody></table>
            <div class="orbit-pagination"><p data-page-summary aria-live="polite"></p><div><button class="orbit-button orbit-button--quiet" type="button" data-prev>Previous</button><button class="orbit-button orbit-button--quiet" type="button" data-next>Next</button></div></div>
        </div>
    </div>
</section>
@endsection
