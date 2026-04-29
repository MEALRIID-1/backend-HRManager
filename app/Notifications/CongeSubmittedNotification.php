<?php

namespace App\Notifications;

use App\Models\Conge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification envoyée quand un employé soumet une demande de congé.
 * Notifie le manager de l'équipe.
 */
class CongeSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private Conge $conge;

    public function __construct(Conge $conge)
    {
        $this->conge = $conge;
    }

    /**
     * Canaux de notification.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    /**
     * Notification par email.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $employe = $this->conge->employe;

        return (new MailMessage)
            ->subject("Nouvelle demande de congé - {$employe->name}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("{$employe->name} a soumis une demande de congé.")
            ->line("Type : {$this->conge->type}")
            ->line("Du {$this->conge->date_debut->format('d/m/Y')} au {$this->conge->date_fin->format('d/m/Y')}")
            ->line("Nombre de jours : {$this->conge->nombre_jours}")
            ->action('Voir la demande', url("/leaves/{$this->conge->id}"))
            ->line('Veuillez valider ou refuser cette demande.');
    }

    /**
     * Notification en base de données.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'conge_id' => $this->conge->id,
            'employe_id' => $this->conge->employe_id,
            'employe_nom' => $this->conge->employe->name,
            'type' => $this->conge->type,
            'date_debut' => $this->conge->date_debut->format('Y-m-d'),
            'date_fin' => $this->conge->date_fin->format('Y-m-d'),
            'nombre_jours' => $this->conge->nombre_jours,
            'raison' => $this->conge->raison,
            'message' => "{$this->conge->employe->name} demande un congé {$this->conge->type}",
        ];
    }

    /**
     * Notification broadcast (temps réel via Reverb/Pusher).
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'conge_submitted',
            'title' => 'Nouvelle demande de congé',
            'message' => "{$this->conge->employe->name} demande un congé",
            'data' => $this->toArray($notifiable),
            'created_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Canal de broadcast.
     */
    public function broadcastOn(): array
    {
        return ['private-user.' . $this->conge->employe->manager_id];
    }

    /**
     * Type de l'événement broadcast.
     */
    public function broadcastType(): string
    {
        return 'conge.submitted';
    }
}
