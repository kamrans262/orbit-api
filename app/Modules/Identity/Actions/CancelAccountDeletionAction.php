<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\AccountDeletionRequest;
use App\Models\User;
use App\Modules\Identity\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CancelAccountDeletionAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $user, ?Request $request = null): ?AccountDeletionRequest
    {
        $deletion = AccountDeletionRequest::query()
            ->where('user_id', $user->getKey())
            ->whereIn('status', ['pending', 'blocked'])
            ->latest('requested_at')
            ->first();

        if (! $deletion) {
            return null;
        }

        $deletion->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'scheduled_for' => null,
            'blocking_reason' => null,
        ])->save();

        DB::table('users')->where('id', $user->getKey())->update([
            'account_deletion_scheduled_for' => null,
            'updated_at' => now(),
        ]);

        $this->audit->write(
            'identity.account_deletion.cancelled',
            (int) $user->getKey(),
            targetType: 'account_deletion',
            targetId: $deletion->id,
            request: $request,
        );

        return $deletion;
    }
}
