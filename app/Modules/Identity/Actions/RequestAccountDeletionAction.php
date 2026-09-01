<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\AccountDeletionRequest;
use App\Models\User;
use App\Modules\Identity\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RequestAccountDeletionAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $user, ?string $reason = null, ?Request $request = null): AccountDeletionRequest
    {
        $existing = AccountDeletionRequest::query()
            ->where('user_id', $user->getKey())
            ->whereIn('status', ['pending', 'blocked'])
            ->latest('requested_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        $scheduledFor = now()->addDays(30);

        $deletion = AccountDeletionRequest::query()->create([
            'user_id' => $user->getKey(),
            'status' => 'pending',
            'reason' => $reason,
            'requested_at' => now(),
            'scheduled_for' => $scheduledFor,
        ]);

        DB::table('users')->where('id', $user->getKey())->update([
            'account_deletion_scheduled_for' => $scheduledFor,
            'updated_at' => now(),
        ]);

        $this->audit->write(
            'identity.account_deletion.requested',
            (int) $user->getKey(),
            targetType: 'account_deletion',
            targetId: $deletion->id,
            metadata: ['scheduled_for' => $scheduledFor->toIso8601String()],
            request: $request,
        );

        return $deletion;
    }
}
