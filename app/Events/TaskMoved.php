<?php

namespace App\Events;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Événement de domaine : "une tâche a changé de statut". Découplé de ce
 * qu'on en fait — journalisation (LogTaskMovedActivity) et diffusion temps
 * réel (ShouldBroadcast) sont deux réactions indépendantes au même fait.
 */
class TaskMoved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Task $task,
        public TaskStatus $from,
        public TaskStatus $to,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('projects.'.$this->task->project_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'task.moved';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'task_id' => $this->task->id,
            'title' => $this->task->title,
            'from' => $this->from->value,
            'to' => $this->to->value,
        ];
    }
}
