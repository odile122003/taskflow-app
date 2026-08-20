<?php

namespace App\Mail;

use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * ShouldQueue : l'envoi part sur la file d'attente (table jobs, driver
 * QUEUE_CONNECTION=database) au lieu de bloquer la requête HTTP qui a
 * déclenché l'invitation. Un worker (php artisan queue:work) doit tourner
 * pour que le mail parte réellement — approfondi au Module 8.
 */
class TeamInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Team $team,
        public string $signedUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invitation à rejoindre l'équipe {$this->team->name} sur TaskFlow",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.team-invitation',
        );
    }
}
