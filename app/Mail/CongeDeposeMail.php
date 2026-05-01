<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Conge;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CongeDeposeMail extends Mailable
{
    use Queueable, SerializesModels;

    public Conge $conge;
    public User $validateur;
    public User $employe;

    /**
     * Create a new message instance.
     */
    public function __construct(Conge $conge, User $validateur, User $employe)
    {
        $this->conge = $conge;
        $this->validateur = $validateur;
        $this->employe = $employe;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Nouvelle demande de congé - {$this->employe->prenom} {$this->employe->nom}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.conge-depose',
            with: [
                'conge' => $this->conge,
                'validateur' => $this->validateur,
                'employe' => $this->employe,
                'validationUrl' => config('app.frontend_url') . '/conges/validation/' . $this->conge->id,
                'duree' => $this->conge->date_debut->diffInDays($this->conge->date_fin) + 1,
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
