<?php

namespace App\Notifications;

use App\Models\Conge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification envoyée quand un congé est approuvé (niveau final).
 */
class CongeApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private Conge $conge;

    public function __construct(Conge $conge)
    {
        $this->conge = $conge;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Congé approuvé')
            ->greeting("Bonjour {$notifiable->name},")
            ->line('Votre demande de congé a été approuvée.')
            ->line("Type : {$this->conge->type}")
            ->line("Du {$this->conge->date_debut->format('d/m/Y')} au {$this->conge->date_fin->format('d/m/Y')}")
            ->action('Voir le détail', url("/leaves/{$this->conge->id}"))
            ->line('Bon congé !');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'conge_id' => $this->conge->id,
            'type' => $this->conge->type,
            'date_debut' => $this->conge->date_debut->format('Y-m-d'),
            'date_fin' => $this->conge->date_fin->format('Y-m-d'),
            'nombre_jours' => $this->conge->nombre_jours,
            'message' => 'Votre demande de congé a été approuvée',
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'conge_approved',
            'title' => 'Congé approuvé',
            'message' => 'Votre demande de congé a été approuvée',
            'data' => $this->toArray($notifiable),
            'created_at' => now()->toIso8601String(),
        ]);
    }

    public function broadcastOn(): array
    {
        return ['private-user.' . $this->conge->employe_id];
    }
}
