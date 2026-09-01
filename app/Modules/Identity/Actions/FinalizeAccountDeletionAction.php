<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\AccountDeletionRequest;
use App\Models\IdentityRefreshToken;
use App\Models\IdentitySession;
use App\Models\User;
use App\Modules\Identity\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FinalizeAccountDeletionAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(AccountDeletionRequest $deletion): string
    {
        if (! in_array($deletion->status, ['pending', 'blocked'], true) || ! $deletion->scheduled_for?->isPast()) {
            return 'not_due';
        }

        $userId = (int) $deletion->user_id;

        if ($this->ownsAnyCircle($userId)) {
            $deletion->forceFill([
                'status' => 'blocked',
                'blocking_reason' => 'Transfer ownership of active Circles before account deletion can finish.',
            ])->save();

            return 'blocked_owner';
        }

        DB::transaction(function () use ($deletion, $userId): void {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $userId)
                ->delete();

            IdentityRefreshToken::query()
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->update(['status' => 'revoked', 'revoked_at' => now(), 'updated_at' => now()]);

            IdentitySession::query()
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->update([
                    'status' => 'revoked',
                    'revoked_at' => now(),
                    'revoke_reason' => 'account_deleted',
                    'access_token_id' => null,
                    'updated_at' => now(),
                ]);

            if (Schema::hasTable('devices')) {
                $updates = ['updated_at' => now()];
                if (Schema::hasColumn('devices', 'push_token')) {
                    $updates['push_token'] = null;
                }
                if (Schema::hasColumn('devices', 'revoked_at')) {
                    $updates['revoked_at'] = now();
                }
                DB::table('devices')->where('user_id', $userId)->update($updates);
            }

            $userUpdates = [
                'account_deleted_at' => now(),
                'account_deletion_scheduled_for' => null,
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('users', 'name')) {
                $userUpdates['name'] = 'Deleted User';
            }
            if (Schema::hasColumn('users', 'email')) {
                $userUpdates['email'] = 'deleted+'.hash('sha256', (string) $userId.microtime(true)).'@orbit.invalid';
            }
            if (Schema::hasColumn('users', 'global_ghost_mode')) {
                $userUpdates['global_ghost_mode'] = true;
            }

            DB::table('users')->where('id', $userId)->update($userUpdates);

            $deletion->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
                'blocking_reason' => null,
            ])->save();
        });

        $this->audit->write(
            'identity.account_deletion.completed',
            $userId,
            targetType: 'account_deletion',
            targetId: $deletion->id,
        );

        return 'completed';
    }

    private function ownsAnyCircle(int $userId): bool
    {
        if (! Schema::hasTable('circle_members') || ! Schema::hasColumn('circle_members', 'role')) {
            return false;
        }

        return DB::table('circle_members')
            ->where('user_id', $userId)
            ->where('role', 'owner')
            ->exists();
    }
}
