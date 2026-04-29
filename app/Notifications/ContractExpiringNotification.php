<?php

namespace App\Notifications;

use App\Models\Contrat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification pour les contrats expirant bientôt.
 * Notifie RH et Directeur.
 */
class ContractExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private Contrat $contrat;

    public function __construct(Contrat $contrat)
    {
        $this->contrat = $contrat;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $joursRestants = $this->contrat->duree_restante;
        $employe = $this->contrat->employe;

        return (new MailMessage)
            ->subject('Alerte : Contrat expirant bientôt')
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Le contrat de {$employe->name} expire dans {$joursRestants} jours.")
            ->line('Type de contrat : ' . strtoupper($this->contrat->type))
            ->line('Date de fin : ' . $this->contrat->date_fin->format('d/m/Y'))
            ->action('Voir le contrat', url("/contracts/{$this->contrat->id}"))
            ->line('Veuillez prendre les mesures nécessaires.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'contrat_id' => $this->contrat->id,
            'employe_id' => $this->contrat->employe_id,
            'employe_nom' => $this->contrat->employe->name,
            'type' => $this->contrat->type,
            'date_fin' => $this->contrat->date_fin->format('Y-m-d'),
            'jours_restants' => $this->contrat->duree_restante,
            'message' => "Le contrat de {$this->contrat->employe->name} expire dans {$this->contrat->duree_restante} jours.",
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'contract_expiring',
            'title' => 'Contrat expirant',
            'message' => "Le contrat de {$this->contrat->employe->name} expire bientôt",
            'data' => $this->toArray($notifiable),
            'created_at' => now()->toIso8601String(),
        ]);
    }

    public function broadcastOn(): array
    {
        // Broadcast sur le canal privé de chaque RH/Admin notifié
        return ['private-user.' . auth()->id()];
    }
}
