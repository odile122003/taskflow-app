<?php

namespace App\Mail;

use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{completed: int, active_projects: int, top_contributor: ?string}  $stats
     */
    public function __construct(
        public Team $team,
        public array $stats,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Rapport hebdomadaire — {$this->team->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.weekly-report',
        );
    }
}
