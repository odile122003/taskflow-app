<?php

namespace App\Mail;

use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable volontairement minimal (vue Blade simple, pas de Markdown, pas de
 * ShouldQueue) : le Module 6 se concentre sur l'URL signée elle-même. Le
 * Module 7 (e-mails et notifications) reprendra ce Mailable pour le mettre
 * en file d'attente et le passer au format Markdown.
 */
class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Team $team,
        public string $signedUrl,
    ) {}

    public function build(): self
    {
        return $this
            ->subject("Invitation à rejoindre l'équipe {$this->team->name} sur TaskFlow")
            ->view('emails.team-invitation');
    }
}
