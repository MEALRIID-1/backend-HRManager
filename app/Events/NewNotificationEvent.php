<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Événement broadcasté lors de la création d'une nouvelle notification.
 * Utilise Laravel Reverb pour le temps réel.
 */
class NewNotificationEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Notification $notification;
    public array $data;

    public function __construct(Notification $notification)
    {
        $this->notification = $notification;
        $this->data = [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'icon' => $notification->icon,
            'color_class' => $notification->color_class,
            'action_url' => $notification->action_url,
            'created_at' => $notification->created_at->toIso8601String(),
            'data' => $notification->data,
        ];
    }

    /**
     * Canal privé de l'utilisateur.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('private-user.' . $this->notification->user_id),
        ];
    }

    /**
     * Nom de l'événement pour le frontend.
     */
    public function broadcastAs(): string
    {
        return 'new-notification';
    }

    /**
     * Données à broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'notification' => $this->data,
            'unread_count' => Notification::pourUtilisateur($this->notification->user_id)
                ->nonLues()
                ->count(),
        ];
    }

    /**
     * Déterminer si l'événement doit être broadcast.
     */
    public function broadcastWhen(): bool
    {
        // Ne broadcast que si la notification n'est pas encore lue
        return $this->notification->estNonLue();
    }
}
