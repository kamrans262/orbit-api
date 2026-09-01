<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Models\AdminInvitation;
use App\Models\AdminMfaChallenge;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Modules\Admin\Enums\AdminMfaChallengePurpose;
use App\Modules\Admin\Enums\AdminStatus;
use App\Modules\Admin\Exceptions\AdminOperationException;
use App\Modules\Admin\Mail\AdminInvitationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class AdminInvitationService
{
    public function __construct(
        private readonly AdminTokenService $tokens,
        private readonly AdminTotpService $totp,
        private readonly AdminAuditLogger $audit,
    ) {}

    /** @param list<string> $roleSlugs */
    public function invite(string $email, ?string $name, array $roleSlugs, ?AdminUser $actor, ?Request $request, ?string $reason, ?\DateTimeInterface $accessExpiresAt = null): array
    {
        $email = strtolower(trim($email));
        $result = DB::transaction(function () use ($email, $name, $roleSlugs, $actor, $accessExpiresAt): array {
            $existing = AdminUser::query()->where('email', $email)->lockForUpdate()->first();
            if ($existing !== null && $existing->status === AdminStatus::Active) {
                throw new AdminOperationException('ADMIN_ALREADY_ACTIVE', 'An active administrator already uses this email address.', 409);
            }
            if ($existing !== null && $existing->status === AdminStatus::Disabled) {
                throw new AdminOperationException('ADMIN_DISABLED_REQUIRES_REACTIVATION', 'A disabled administrator must be reactivated instead of re-invited.', 409);
            }

            $admin = $existing ?? new AdminUser;
            $admin->forceFill([
                'email' => $email,
                'name' => $name ?: $admin->name,
                'status' => AdminStatus::Invited,
                'access_expires_at' => $accessExpiresAt,
                'created_by_admin_id' => $admin->exists ? $admin->created_by_admin_id : $actor?->id,
            ])->save();

            AdminInvitation::query()
                ->where('admin_user_id', $admin->id)
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->update(['expires_at' => now()]);

            $rawToken = $this->tokens->generate();
            $invitation = AdminInvitation::query()->create([
                'admin_user_id' => $admin->id,
                'invited_by_admin_id' => $actor?->id,
                'token_hash' => $this->tokens->hash($rawToken),
                'expires_at' => now()->addHours(max(1, (int) config('orbit_admin.invitation_hours', 24))),
            ]);

            $roleIds = AdminRole::query()->whereIn('slug', $roleSlugs !== [] ? $roleSlugs : ['read-only'])->pluck('id')->all();
            if ($roleIds === []) {
                throw new AdminOperationException('ADMIN_ROLE_NOT_FOUND', 'No valid administrator role was supplied.', 422);
            }
            $admin->roles()->sync($roleIds);

            return compact('admin', 'invitation', 'rawToken');
        });

        $activationUrl = rtrim((string) config('orbit_admin.console_url'), '/').'/activate?token='.urlencode($result['rawToken']);
        Mail::to($email)->send(new AdminInvitationMail($activationUrl, $result['invitation']->expires_at->toIso8601String()));

        $this->audit->write(
            'admin.invitation.created',
            $actor,
            targetType: 'admin_user',
            targetId: $result['admin']->id,
            reason: $reason,
            after: [
                'email_hash' => hash('sha256', $email),
                'role_slugs' => $roleSlugs,
                'access_expires_at' => $accessExpiresAt?->format(DATE_ATOM),
                'invitation_expires_at' => $result['invitation']->expires_at->toIso8601String(),
            ],
            request: $request,
        );

        return $result;
    }

    /** @return array{admin:AdminUser, setup_token:string, provisioning_uri:string, expires_at:string} */
    public function accept(string $rawInvitationToken, string $name, string $password, Request $request): array
    {
        return DB::transaction(function () use ($rawInvitationToken, $name, $password, $request): array {
            $invitation = AdminInvitation::query()
                ->where('token_hash', $this->tokens->hash($rawInvitationToken))
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($invitation === null) {
                throw new AdminOperationException('ADMIN_INVITATION_INVALID', 'The administrator invitation is invalid or expired.', 422);
            }

            $admin = AdminUser::query()->findOrFail($invitation->admin_user_id);
            if ($admin->status === AdminStatus::Disabled) {
                throw new AdminOperationException('ADMIN_ACCOUNT_DISABLED', 'This administrator account is disabled.', 403);
            }

            $secret = $this->totp->generateSecret();
            $admin->forceFill([
                'name' => $name,
                'password' => $password,
                'status' => AdminStatus::MfaSetup,
                'totp_secret' => $secret,
                'mfa_confirmed_at' => null,
            ])->save();
            $invitation->forceFill(['accepted_at' => now()])->save();

            $setupToken = $this->tokens->generate();
            $expiresAt = now()->addMinutes(max(5, (int) config('orbit_admin.mfa_setup_minutes', 20)));
            AdminMfaChallenge::query()->create([
                'admin_user_id' => $admin->id,
                'purpose' => AdminMfaChallengePurpose::Setup->value,
                'token_hash' => $this->tokens->hash($setupToken),
                'expires_at' => $expiresAt,
            ]);

            $this->audit->write(
                'admin.invitation.accepted',
                targetType: 'admin_user',
                targetId: $admin->id,
                after: ['status' => AdminStatus::MfaSetup->value],
                request: $request,
            );

            return [
                'admin' => $admin,
                'setup_token' => $setupToken,
                'provisioning_uri' => $this->totp->provisioningUri($secret, $admin->email),
                'expires_at' => $expiresAt->toIso8601String(),
            ];
        });
    }
}
