<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Models\AdminLoginEvent;
use App\Models\AdminMfaChallenge;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Modules\Admin\Enums\AdminMfaChallengePurpose;
use App\Modules\Admin\Enums\AdminStatus;
use App\Modules\Admin\Exceptions\AdminOperationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class AdminAuthenticationService
{
    public function __construct(
        private readonly AdminTokenService $tokens,
        private readonly AdminTotpService $totp,
        private readonly AdminRecoveryCodeService $recoveryCodes,
        private readonly AdminSessionService $sessions,
        private readonly AdminAuditLogger $audit,
    ) {}

    /** @return array{challenge_token:string, expires_at:string} */
    public function passwordLogin(string $email, string $password, Request $request): array
    {
        $email = strtolower(trim($email));
        $admin = AdminUser::query()->where('email', $email)->first();

        if ($admin !== null && $admin->locked_until?->isFuture()) {
            $this->recordLoginEvent($admin, $email, 'locked_login_attempt', false, true, 'ACCOUNT_LOCKED', $request);
            throw new AdminOperationException('ADMIN_ACCOUNT_LOCKED', 'This administrator account is temporarily locked.', 423);
        }

        if ($admin === null || $admin->password === null || ! Hash::check($password, $admin->password)) {
            if ($admin !== null) {
                $this->recordFailedPassword($admin, $request);
            } else {
                $this->recordLoginEvent(null, $email, 'password_failure', false, false, 'INVALID_CREDENTIALS', $request);
            }
            throw new AdminOperationException('ADMIN_INVALID_CREDENTIALS', 'The administrator credentials are invalid.', 401);
        }

        if ($admin->status !== AdminStatus::Active || $admin->mfa_confirmed_at === null || $admin->totp_secret === null) {
            throw new AdminOperationException('ADMIN_ACCOUNT_NOT_ACTIVE', 'This administrator account is not active.', 403);
        }

        if ($admin->access_expires_at?->isPast()) {
            throw new AdminOperationException('ADMIN_ACCESS_EXPIRED', 'This administrator temporary access has expired.', 403);
        }

        $admin->forceFill(['failed_login_count' => 0, 'locked_until' => null])->save();
        $challengeToken = $this->tokens->generate();
        $expiresAt = now()->addMinutes(max(2, (int) config('orbit_admin.mfa_challenge_minutes', 5)));
        AdminMfaChallenge::query()->create([
            'admin_user_id' => $admin->id,
            'purpose' => AdminMfaChallengePurpose::Login->value,
            'token_hash' => $this->tokens->hash($challengeToken),
            'expires_at' => $expiresAt,
        ]);

        $this->recordLoginEvent($admin, $email, 'password_verified', true, false, null, $request);

        return ['challenge_token' => $challengeToken, 'expires_at' => $expiresAt->toIso8601String()];
    }

    /** @return array{access_token:string, session:AdminSession, recovery_code_used:bool} */
    public function verifyLoginMfa(string $challengeToken, string $code, Request $request): array
    {
        $challenge = AdminMfaChallenge::query()
            ->where('token_hash', $this->tokens->hash($challengeToken))
            ->where('purpose', AdminMfaChallengePurpose::Login->value)
            ->whereNull('consumed_at')
            ->first();

        if ($challenge === null || $challenge->expires_at->isPast()) {
            throw new AdminOperationException('ADMIN_MFA_CHALLENGE_INVALID', 'The MFA challenge is invalid or expired.', 401);
        }
        if ($challenge->attempts >= max(3, (int) config('orbit_admin.mfa_max_attempts', 5))) {
            throw new AdminOperationException('ADMIN_MFA_CHALLENGE_LOCKED', 'The MFA challenge has exceeded the allowed attempts.', 423);
        }

        $admin = AdminUser::query()->findOrFail($challenge->admin_user_id);
        if (! $admin->isOperationallyActive() || $admin->totp_secret === null) {
            throw new AdminOperationException('ADMIN_ACCOUNT_NOT_ACTIVE', 'This administrator account is not active.', 403);
        }

        $totpValid = $this->totp->verify($admin->totp_secret, $code);
        $recoveryValid = ! $totpValid && $this->recoveryCodes->matches($admin, $code);
        if (! $totpValid && ! $recoveryValid) {
            AdminMfaChallenge::query()->whereKey($challenge->id)->increment('attempts');
            $this->recordLoginEvent($admin, $admin->email, 'mfa_failure', false, true, 'INVALID_MFA', $request);
            throw new AdminOperationException('ADMIN_MFA_INVALID', 'The MFA code is invalid.', 401);
        }

        return DB::transaction(function () use ($challenge, $admin, $code, $recoveryValid, $request): array {
            $locked = AdminMfaChallenge::query()->whereKey($challenge->id)->whereNull('consumed_at')->lockForUpdate()->first();
            if ($locked === null || $locked->expires_at->isPast()) {
                throw new AdminOperationException('ADMIN_MFA_CHALLENGE_INVALID', 'The MFA challenge is invalid or expired.', 401);
            }
            if ($recoveryValid && ! $this->recoveryCodes->consume($admin, $code)) {
                throw new AdminOperationException('ADMIN_MFA_INVALID', 'The recovery code has already been used.', 401);
            }

            $locked->forceFill(['consumed_at' => now()])->save();
            $issued = $this->sessions->issue($admin, $request);
            $ipHash = $this->audit->hashIp($request->ip());
            $suspicious = $this->isNewSuccessfulIp($admin, $ipHash);
            $admin->forceFill(['last_login_at' => now(), 'failed_login_count' => 0, 'locked_until' => null])->save();
            $this->recordLoginEvent($admin, $admin->email, 'login_success', true, $suspicious, null, $request, ['recovery_code_used' => $recoveryValid]);
            $this->audit->write(
                'admin.auth.login',
                $admin,
                $issued['session'],
                targetType: 'admin_user',
                targetId: $admin->id,
                metadata: ['suspicious_new_ip' => $suspicious, 'recovery_code_used' => $recoveryValid],
                request: $request,
            );

            return ['access_token' => $issued['token'], 'session' => $issued['session'], 'recovery_code_used' => $recoveryValid];
        });
    }

    /** @return list<string> */
    public function confirmMfaSetup(string $setupToken, string $code, Request $request): array
    {
        $challenge = AdminMfaChallenge::query()
            ->where('token_hash', $this->tokens->hash($setupToken))
            ->where('purpose', AdminMfaChallengePurpose::Setup->value)
            ->whereNull('consumed_at')
            ->first();

        if ($challenge === null || $challenge->expires_at->isPast()) {
            throw new AdminOperationException('ADMIN_MFA_SETUP_INVALID', 'The MFA setup challenge is invalid or expired.', 422);
        }
        if ($challenge->attempts >= max(3, (int) config('orbit_admin.mfa_max_attempts', 5))) {
            throw new AdminOperationException('ADMIN_MFA_SETUP_LOCKED', 'The MFA setup challenge has exceeded the allowed attempts.', 423);
        }

        $admin = AdminUser::query()->findOrFail($challenge->admin_user_id);
        if ($admin->status !== AdminStatus::MfaSetup || $admin->totp_secret === null) {
            throw new AdminOperationException('ADMIN_MFA_SETUP_UNAVAILABLE', 'MFA setup is not available for this administrator.', 409);
        }
        if (! $this->totp->verify($admin->totp_secret, $code)) {
            AdminMfaChallenge::query()->whereKey($challenge->id)->increment('attempts');
            throw new AdminOperationException('ADMIN_MFA_INVALID', 'The MFA code is invalid.', 422);
        }

        return DB::transaction(function () use ($challenge, $admin, $request): array {
            $locked = AdminMfaChallenge::query()->whereKey($challenge->id)->whereNull('consumed_at')->lockForUpdate()->first();
            if ($locked === null || $locked->expires_at->isPast()) {
                throw new AdminOperationException('ADMIN_MFA_SETUP_INVALID', 'The MFA setup challenge is invalid or expired.', 422);
            }

            $locked->forceFill(['consumed_at' => now()])->save();
            $admin->forceFill([
                'status' => AdminStatus::Active,
                'mfa_confirmed_at' => now(),
                'activated_at' => now(),
                'failed_login_count' => 0,
                'locked_until' => null,
            ])->save();
            $codes = $this->recoveryCodes->regenerate($admin);
            $this->audit->write(
                'admin.mfa.activated',
                $admin,
                targetType: 'admin_user',
                targetId: $admin->id,
                after: ['status' => AdminStatus::Active->value, 'mfa_enabled' => true],
                request: $request,
            );

            return $codes;
        });
    }

    public function reauthenticate(AdminUser $admin, AdminSession $session, string $password, string $code, Request $request): bool
    {
        if (! Hash::check($password, (string) $admin->password)) {
            throw new AdminOperationException('ADMIN_REAUTH_FAILED', 'Reauthentication failed.', 401);
        }

        $totpValid = $admin->totp_secret !== null && $this->totp->verify($admin->totp_secret, $code);
        $recoveryValid = ! $totpValid && $this->recoveryCodes->matches($admin, $code);
        if (! $totpValid && ! $recoveryValid) {
            throw new AdminOperationException('ADMIN_REAUTH_FAILED', 'Reauthentication failed.', 401);
        }
        if ($recoveryValid && ! $this->recoveryCodes->consume($admin, $code)) {
            throw new AdminOperationException('ADMIN_REAUTH_FAILED', 'Reauthentication failed.', 401);
        }

        $session->forceFill(['reauthenticated_at' => now()])->save();
        $this->audit->write(
            'admin.auth.reauthenticated',
            $admin,
            $session,
            targetType: 'admin_session',
            targetId: $session->id,
            metadata: ['recovery_code_used' => $recoveryValid],
            request: $request,
        );

        return $recoveryValid;
    }

    private function recordFailedPassword(AdminUser $admin, Request $request): void
    {
        DB::transaction(function () use ($admin, $request): void {
            $lockedAdmin = AdminUser::query()->whereKey($admin->id)->lockForUpdate()->firstOrFail();
            $count = $lockedAdmin->failed_login_count + 1;
            $limit = max(3, (int) config('orbit_admin.failed_login_limit', 5));
            $lockedUntil = $count >= $limit
                ? now()->addMinutes(max(5, (int) config('orbit_admin.lockout_minutes', 15)))
                : null;

            $lockedAdmin->forceFill([
                'failed_login_count' => $count,
                'locked_until' => $lockedUntil,
            ])->save();

            $this->recordLoginEvent(
                $lockedAdmin,
                $lockedAdmin->email,
                'password_failure',
                false,
                $lockedUntil !== null,
                $lockedUntil ? 'ACCOUNT_LOCKED' : 'INVALID_CREDENTIALS',
                $request,
            );
        });
    }

    private function isNewSuccessfulIp(AdminUser $admin, ?string $ipHash): bool
    {
        if ($ipHash === null) {
            return false;
        }

        $previous = AdminLoginEvent::query()
            ->where('admin_user_id', $admin->id)
            ->where('event_type', 'login_success')
            ->where('success', true)
            ->latest('occurred_at')
            ->value('ip_hash');

        return $previous !== null && ! hash_equals((string) $previous, $ipHash);
    }

    private function recordLoginEvent(?AdminUser $admin, string $email, string $type, bool $success, bool $suspicious, ?string $failureCode, Request $request, array $metadata = []): void
    {
        AdminLoginEvent::query()->create([
            'admin_user_id' => $admin?->id,
            'email_hash' => hash('sha256', strtolower(trim($email))),
            'event_type' => $type,
            'success' => $success,
            'suspicious' => $suspicious,
            'ip_hash' => $this->audit->hashIp($request->ip()),
            'user_agent_hash' => $this->audit->hashUserAgent($request->userAgent()),
            'failure_code' => $failureCode,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
