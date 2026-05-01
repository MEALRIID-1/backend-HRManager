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

class CongeDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public Conge $conge;
    public User $employe;
    public string $decision; // 'approuve' ou 'refuse'
    public ?string $motifRefus;
    public User $decideur;

    /**
     * Create a new message instance.
     */
    public function __construct(Conge $conge, User $employe, string $decision, ?string $motifRefus = null, ?User $decideur = null)
    {
        $this->conge = $conge;
        $this->employe = $employe;
        $this->decision = $decision;
        $this->motifRefus = $motifRefus;
        $this->decideur = $decideur;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->decision === 'approuve' 
            ? 'Votre demande de congé a été approuvée' 
            : 'Votre demande de congé a été refusée';

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.conge-decision',
            with: [
                'conge' => $this->conge,
                'employe' => $this->employe,
                'decision' => $this->decision,
                'motifRefus' => $this->motifRefus,
                'decideur' => $this->decideur,
                'duree' => $this->conge->date_debut->diffInDays($this->conge->date_fin) + 1,
                'isApprouve' => $this->decision === 'approuve',
                'isRefuse' => $this->decision === 'refuse',
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
