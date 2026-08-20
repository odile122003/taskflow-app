<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Volontairement synchrone (pas de ShouldQueue), contrairement à
 * TeamInvitationMail : le canal `database` alimente le centre de
 * notifications affiché juste après dans l'interface — le mettre en file
 * d'attente retarderait le compteur de non-lus tant qu'aucun worker ne
 * tourne. TeamInvitationMail reste l'exemple de référence pour l'envoi en
 * file d'attente (Module 7 → Module 8).
 */
class TaskAssignedNotification extends Notification
{
    public function __construct(
        public Task $task,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Tâche assignée : {$this->task->title}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("On vous a assigné la tâche « {$this->task->title} » sur le projet « {$this->task->project->name} ».")
            ->action('Voir le tableau kanban', route('projects.board', $this->task->project))
            ->line('Merci de votre implication dans TaskFlow !');
    }

    /**
     * Ligne stockée dans la table `notifications` — c'est ce que lit le
     * centre de notifications de l'interface (icône cloche).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'project_id' => $this->task->project_id,
            'project_name' => $this->task->project->name,
            'message' => "Vous avez été assigné(e) à la tâche « {$this->task->title} ».",
        ];
    }
}
