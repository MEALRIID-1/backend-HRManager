<?php

namespace App\Notifications;

use App\Models\Conge;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification envoyée quand un congé est refusé.
 */
class CongeRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private Conge $conge;
    private User $rejecteur;
    private string $motif;

    public function __construct(Conge $conge, User $rejecteur, string $motif)
    {
        $this->conge = $conge;
        $this->rejecteur = $rejecteur;
        $this->motif = $motif;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Demande de congé refusée')
            ->greeting("Bonjour {$notifiable->name},")
            ->line('Votre demande de congé a été refusée.')
            ->line("Refusé par : {$this->rejecteur->name}")
            ->line("Motif : {$this->motif}")
            ->line("Type : {$this->conge->type}")
            ->line("Du {$this->conge->date_debut->format('d/m/Y')} au {$this->conge->date_fin->format('d/m/Y')}")
            ->action('Voir le détail', url("/leaves/{$this->conge->id}"))
            ->line('Contactez votre manager ou RH pour plus d\'informations.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'conge_id' => $this->conge->id,
            'type' => $this->conge->type,
            'date_debut' => $this->conge->date_debut->format('Y-m-d'),
            'date_fin' => $this->conge->date_fin->format('Y-m-d'),
            'nombre_jours' => $this->conge->nombre_jours,
            'rejecteur_id' => $this->rejecteur->id,
            'rejecteur_nom' => $this->rejecteur->name,
            'motif' => $this->motif,
            'message' => 'Votre demande de congé a été refusée',
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'conge_rejected',
            'title' => 'Congé refusé',
            'message' => "Refusé par {$this->rejecteur->name}",
            'data' => $this->toArray($notifiable),
            'created_at' => now()->toIso8601String(),
        ]);
    }

    public function broadcastOn(): array
    {
        return ['private-user.' . $this->conge->employe_id];
    }
}
