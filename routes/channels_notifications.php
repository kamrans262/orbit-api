<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('orbit.user.{userId}', fn (User $user, string $userId): bool => (string) $user->getKey() === $userId);
