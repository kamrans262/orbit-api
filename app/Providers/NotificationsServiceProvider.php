<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Notifications\Events\NotificationCreated;
use App\Modules\Notifications\Listeners\BroadcastNotificationCreated;
use App\Modules\Notifications\Listeners\ImportSosNotifications;
use App\Modules\Notifications\Listeners\RecordEncryptedMessageNotification;
use App\Modules\Notifications\Listeners\RecordMomentPublishedNotification;
use App\Modules\Notifications\Listeners\RecordPingNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class NotificationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(NotificationCreated::class, BroadcastNotificationCreated::class);

        $listeners = [
            'App\\Modules\\Moments\\Events\\MomentPublished' => RecordMomentPublishedNotification::class,
            'App\\Modules\\Ping\\Events\\PingSent' => RecordPingNotification::class,
            'App\\Modules\\Messaging\\Events\\EncryptedMessageAccepted' => RecordEncryptedMessageNotification::class,
            'App\\Modules\\Sos\\Events\\SosActivated' => ImportSosNotifications::class,
            'App\\Modules\\Sos\\Events\\SosEscalated' => ImportSosNotifications::class,
        ];

        foreach ($listeners as $eventClass => $listenerClass) {
            if (class_exists($eventClass)) {
                Event::listen($eventClass, $listenerClass);
            }
        }
    }
}
