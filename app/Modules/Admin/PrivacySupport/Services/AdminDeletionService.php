<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Services;

use App\Models\AccountDeletionRequest;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\User;
use App\Modules\Admin\PrivacySupport\Exceptions\PrivacySupportDomainException;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Identity\Actions\CancelAccountDeletionAction;
use App\Modules\Identity\Actions\FinalizeAccountDeletionAction;
use App\Modules\Notifications\Actions\RouteNotificationAction;
use App\Modules\Notifications\Enums\NotificationPriority;
use Illuminate\Http\Request;

final readonly class AdminDeletionService
{
    public function __construct(
        private AdminAuditLogger $audit,
        private ContactHistoryService $contacts,
    ) {}

    public function finalize(
        AccountDeletionRequest $deletion,
        AdminUser $admin,
        AdminSession $session,
        string $reason,
        Request $request,
    ): string {
        if (! in_array($deletion->status, ['pending', 'blocked'], true)) {
            throw new PrivacySupportDomainException('DELETION_NOT_ACTIVE', 409, 'The deletion request is not active.');
        }
        if ($deletion->scheduled_for === null || $deletion->scheduled_for->isFuture()) {
            throw new PrivacySupportDomainException('DELETION_NOT_DUE', 409, 'The deletion grace period has not ended.');
        }

        $result = app(FinalizeAccountDeletionAction::class)->handle($deletion);

        $this->audit->write(
            'admin.privacy.deletion.finalize_requested', $admin, $session, 'account_deletion', $deletion->id,
            reason: $reason, after: ['result' => $result], request: $request,
        );

        if ($result === 'completed') {
            $this->contacts->record(
                (int) $deletion->user_id, 'privacy.deletion.completed', 'system', 'outbound',
                'Account deletion completed', 'Your Orbit account deletion was completed.',
                'account_deletion', $deletion->id, $admin,
            );
        }

        return $result;
    }

    public function cancel(
        AccountDeletionRequest $deletion,
        AdminUser $admin,
        AdminSession $session,
        string $reason,
        Request $request,
    ): AccountDeletionRequest {
        if (! in_array($deletion->status, ['pending', 'blocked'], true)) {
            throw new PrivacySupportDomainException('DELETION_NOT_ACTIVE', 409, 'The deletion request is not active.');
        }

        $user = User::query()->find($deletion->user_id);
        if ($user === null) {
            throw new PrivacySupportDomainException('DELETION_USER_NOT_FOUND', 404, 'Deletion user not found.');
        }

        $cancelled = app(CancelAccountDeletionAction::class)->handle($user, $request);
        if ($cancelled === null || $cancelled->id !== $deletion->id) {
            throw new PrivacySupportDomainException('DELETION_CANCEL_FAILED', 409, 'The selected deletion request could not be cancelled.');
        }

        $this->audit->write(
            'admin.privacy.deletion.cancelled', $admin, $session, 'account_deletion', $deletion->id,
            reason: $reason, after: ['status' => 'cancelled'], request: $request,
        );

        $this->contacts->record(
            (int) $deletion->user_id, 'privacy.deletion.cancelled', 'system', 'outbound',
            'Account deletion cancelled', 'Your Orbit account deletion request was cancelled after verification.',
            'account_deletion', $deletion->id, $admin,
        );

        if (class_exists(RouteNotificationAction::class)) {
            app(RouteNotificationAction::class)->handle(
                (int) $deletion->user_id,
                'privacy.deletion_cancelled',
                'privacy-deletion-cancelled:'.$deletion->id,
                ['resource_id' => $deletion->id, 'actor_user_id' => (int) $deletion->user_id, 'deep_link' => '/profile/privacy'],
                NotificationPriority::High,
            );
        }

        return $cancelled->refresh();
    }
}
