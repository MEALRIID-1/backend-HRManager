<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Contrat;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContratCreeMail extends Mailable
{
    use Queueable, SerializesModels;

    public Contrat $contrat;
    public User $employe;

    /**
     * Create a new message instance.
     */
    public function __construct(Contrat $contrat, User $employe)
    {
        $this->contrat = $contrat;
        $this->employe = $employe;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $typeContrat = $this->contrat->type;
        return new Envelope(
            subject: "Votre contrat de travail {$typeContrat} a été créé",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.contrat-cree',
            with: [
                'contrat' => $this->contrat,
                'employe' => $this->employe,
                'contratUrl' => config('app.frontend_url') . '/contrats/' . $this->contrat->id,
                'pdfUrl' => $this->contrat->pdf_url ? asset('storage/' . $this->contrat->pdf_url) : null,
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
        // Optionnel: attacher le PDF du contrat s'il existe
        // if ($this->contrat->pdf_url && Storage::disk('public')->exists($this->contrat->pdf_url)) {
        //     return [
        //         Attachment::fromStorageDisk('public', $this->contrat->pdf_url)
        //             ->as('contrat.pdf')
        //             ->withMime('application/pdf'),
        //     ];
        // }

        return [];
    }
}
