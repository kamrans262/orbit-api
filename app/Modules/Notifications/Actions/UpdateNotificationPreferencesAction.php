<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Modules\Notifications\Services\NotificationPreferencesService;

final readonly class UpdateNotificationPreferencesAction
{
    public function __construct(private NotificationPreferencesService $preferences) {}

    public function handle(User $user, array $data): NotificationPreference
    {
        $preference = $this->preferences->forUser((int) $user->getKey());
        $preference->fill($data);
        $preference->save();

        return $preference->refresh();
    }
}
