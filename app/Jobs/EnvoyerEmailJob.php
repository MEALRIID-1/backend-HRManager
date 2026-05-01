<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnvoyerEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Le nombre de tentatives pour le job.
     */
    public $tries = 3;

    /**
     * Le nombre de secondes avant qu'un job en attente ne soit considéré comme échoué.
     */
    public $timeout = 60;

    /**
     * Le délai (en secondes) avant le retry après un échec.
     */
    public $backoff = [30, 60, 120]; // 30s, 60s, 120s

    /**
     * Le Mailable à envoyer.
     */
    public Mailable $mailable;

    /**
     * L'adresse email du destinataire.
     */
    public string $email;

    /**
     * Le nom du destinataire.
     */
    public ?string $nom;

    /**
     * Create a new job instance.
     */
    public function __construct(Mailable $mailable, string $email, ?string $nom = null)
    {
        $this->mailable = $mailable;
        $this->email = $email;
        $this->nom = $nom;
        
        // Spécifier la queue 'emails'
        $this->onQueue('emails');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $destinataire = $this->nom 
                ? ['email' => $this->email, 'name' => $this->nom] 
                : $this->email;

            Mail::to($destinataire)->send($this->mailable);

            Log::info('Email envoyé avec succès', [
                'to' => $this->email,
                'subject' => $this->mailable->envelope()->subject,
                'queue' => 'emails',
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'envoi de l\'email', [
                'to' => $this->email,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Si on n'a pas atteint le max de tentatives, on relance
            if ($this->attempts() < $this->tries) {
                throw $e; // Relance l'exception pour retry
            }

            // Log final failure
            Log::error('Échec définitif de l\'envoi de l\'email après ' . $this->tries . ' tentatives', [
                'to' => $this->email,
                'subject' => $this->mailable->envelope()->subject,
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Job d\'envoi d\'email échoué définitivement', [
            'to' => $this->email,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Ici on pourrait notifier les admins, envoyer à un service de monitoring, etc.
    }
}
