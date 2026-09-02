@extends('admin.layouts.auth')

@section('title', 'Verify MFA')
@section('page-script', asset('admin-ui/js/pages/mfa.js'))

@section('content')
    <div class="auth-card__header">
        <span class="auth-icon auth-icon--accent"><x-admin.icon name="keypad" size="24" /></span>
        <div>
            <span class="eyebrow">Second factor</span>
            <h2>Verify it’s you</h2>
            <p>Enter your authenticator code or an unused recovery code.</p>
        </div>
    </div>

    <div class="mfa-context">
        <span class="avatar avatar--small" data-auth-email-avatar>A</span>
        <div>
            <strong data-auth-email>Administrator</strong>
            <small>Administrator sign-in challenge</small>
        </div>
        <span class="status-badge status-badge--secure"><x-admin.icon name="shield" size="14" /> Protected</span>
    </div>

    <form id="admin-mfa-form" class="form-stack" novalidate>
        <div class="form-field">
            <label for="mfa-code">Verification code</label>
            <div class="input-shell input-shell--code">
                <span class="input-shell__icon"><x-admin.icon name="keypad" size="19" /></span>
                <input id="mfa-code" name="code" type="text" autocomplete="one-time-code" inputmode="numeric" maxlength="32" placeholder="000 000" required autofocus>
            </div>
            <p class="field-help">Authenticator codes are usually six digits. Recovery codes are also accepted.</p>
            <p class="field-error" data-error-for="code"></p>
        </div>

        <div id="mfa-alert" class="inline-alert inline-alert--danger" hidden></div>

        <x-admin.button type="submit" class="ui-button--block" loadingText="Opening secure workspace…">
            <span>Verify and continue</span>
            <x-admin.icon name="arrow-right" size="18" />
        </x-admin.button>

        <button type="button" class="text-button" data-back-to-login>Use a different account</button>
    </form>
@endsection
