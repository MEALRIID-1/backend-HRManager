<?php

namespace App\Notifications;

use App\Models\FichePaie;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification envoyée quand une fiche de paie est disponible.
 */
class PayslipAvailableNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private FichePaie $fichePaie;

    public function __construct(FichePaie $fichePaie)
    {
        $this->fichePaie = $fichePaie;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $periode = $this->fichePaie->periode;

        return (new MailMessage)
            ->subject("Votre fiche de paie {$periode} est disponible")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Votre fiche de paie pour la période {$periode} est maintenant disponible.")
            ->line("Salaire net : {$this->fichePaie->salaire_net} €")
            ->action('Consulter ma fiche de paie', url("/payslips/{$this->fichePaie->id}"))
            ->line('Cette fiche de paie est confidentielle.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'fiche_paie_id' => $this->fichePaie->id,
            'periode' => $this->fichePaie->periode,
            'mois' => $this->fichePaie->mois,
            'annee' => $this->fichePaie->annee,
            'salaire_net' => $this->fichePaie->salaire_net,
            'salaire_brut' => $this->fichePaie->salaire_brut,
            'date_paie' => $this->fichePaie->date_paie->format('Y-m-d'),
            'message' => "Votre fiche de paie {$this->fichePaie->periode} est disponible",
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'payslip_available',
            'title' => 'Fiche de paie disponible',
            'message' => "Votre fiche de paie {$this->fichePaie->periode} est disponible",
            'data' => $this->toArray($notifiable),
            'created_at' => now()->toIso8601String(),
        ]);
    }

    public function broadcastOn(): array
    {
        return ['private-user.' . $this->fichePaie->employe_id];
    }
}
