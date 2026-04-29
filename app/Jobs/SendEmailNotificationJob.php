<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Job pour envoyer les emails en arrière-plan.
 * 
 * @performance Réduit le temps de réponse HTTP de 500-2000ms
 * @performance Regroupe les envois pour optimiser la connexion SMTP
 */
class SendEmailNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 5;
    public $backoff = [10, 30, 60, 120, 300];

    public $queue = 'emails'; // Queue dédiée aux emails

    protected string $mailableClass;
    protected array $recipients;
    protected array $data;

    public function __construct(string $mailableClass, array $recipients, array $data = [])
    {
        $this->mailableClass = $mailableClass;
        $this->recipients = $recipients;
        $this->data = $data;
    }

    public function handle(): void
    {
        $startTime = microtime(true);

        try {
            $mailable = new $this->mailableClass($this->data);

            // Envoi avec retry intégré
            Mail::to($this->recipients)->send($mailable);

            $duration = round(microtime(true) - $startTime, 2);
            
            Log::info("[Email Job] Email envoyé", [
                'mailable' => $this->mailableClass,
                'recipients' => count($this->recipients),
                'duration' => $duration,
            ]);

        } catch (\Exception $e) {
            Log::error("[Email Job] Échec envoi email", [
                'mailable' => $this->mailableClass,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[Email Job] Échec définitif", [
            'mailable' => $this->mailableClass,
            'recipients' => $this->recipients,
            'error' => $exception->getMessage(),
        ]);
    }
}
