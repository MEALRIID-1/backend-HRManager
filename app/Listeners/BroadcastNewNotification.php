<?php

namespace App\Listeners;

use App\Events\NewNotificationEvent;
use App\Models\Notification;

class BroadcastNewNotification
{
    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        // Si l'événement est une notification créée
        if ($event instanceof \Illuminate\Notifications\Events\NotificationSent) {
            // Vérifier si c'est une notification database
            if ($event->channel === 'database') {
                $notification = $event->notification;
                
                // Créer l'événement de broadcast
                broadcast(new NewNotificationEvent($notification))->toOthers();
            }
        }
    }

    /**
     * Broadcast une notification créée manuellement.
     */
    public static function broadcast(Notification $notification): void
    {
        broadcast(new NewNotificationEvent($notification))->toOthers();
    }
}
