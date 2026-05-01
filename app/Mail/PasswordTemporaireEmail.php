<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordTemporaireEmail extends Mailable
{
    use Queueable, SerializesModels;

    public User $employe;
    public string $passwordTemporaire;

    /**
     * Create a new message instance.
     */
    public function __construct(User $employe, string $passwordTemporaire)
    {
        $this->employe = $employe;
        $this->passwordTemporaire = $passwordTemporaire;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre compte HRManager a été créé',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.password-temporaire',
            with: [
                'employe' => $this->employe,
                'passwordTemporaire' => $this->passwordTemporaire,
                'loginUrl' => config('app.frontend_url') . '/login',
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
