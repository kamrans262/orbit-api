@extends('admin.layouts.auth')

@section('title', 'Sign in')
@section('page-script', asset('admin-ui/js/pages/login.js'))

@section('content')
    <div class="auth-card__header">
        <span class="auth-icon"><x-admin.icon name="lock" size="23" /></span>
        <div>
            <span class="eyebrow">Secure administrator access</span>
            <h2>Welcome back</h2>
            <p>Sign in with your Orbit administrator credentials.</p>
        </div>
    </div>

    <form id="admin-login-form" class="form-stack" novalidate>
        <div class="form-field">
            <label for="admin-email">Email address</label>
            <div class="input-shell">
                <span class="input-shell__icon"><x-admin.icon name="mail" size="18" /></span>
                <input id="admin-email" name="email" type="email" autocomplete="username" inputmode="email" placeholder="admin@orbit.example" required>
            </div>
            <p class="field-error" data-error-for="email"></p>
        </div>

        <div class="form-field">
            <div class="label-row">
                <label for="admin-password">Password</label>
                <span class="label-hint">MFA follows</span>
            </div>
            <div class="input-shell">
                <span class="input-shell__icon"><x-admin.icon name="lock" size="18" /></span>
                <input id="admin-password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>
                <button class="input-action" type="button" data-password-toggle aria-label="Show password"><x-admin.icon name="eye" size="18" /></button>
            </div>
            <p class="field-error" data-error-for="password"></p>
        </div>

        <div id="login-alert" class="inline-alert inline-alert--danger" hidden></div>

        <x-admin.button type="submit" class="ui-button--block" loadingText="Verifying credentials…">
            <span>Continue securely</span>
            <x-admin.icon name="arrow-right" size="18" />
        </x-admin.button>
    </form>

    <div class="auth-assurance">
        <span class="status-dot status-dot--success"></span>
        Credentials are verified by the isolated Orbit administrator API.
    </div>
@endsection
