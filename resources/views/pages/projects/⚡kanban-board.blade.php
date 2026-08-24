<?php

use App\Actions\Tasks\MoveTaskAction;
use App\Enums\TaskStatus;
use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Composant page (Module 13) : remplace ProjectController::board(), qui
 * envoyait un fetch() manuel avec en-tête CSRF à la main vers l'endpoint
 * PATCH .../move (Module 11) et rechargeait toute la page au moindre
 * changement — même le sien. Livewire gère le jeton CSRF et ne repeint que
 * ce qui a changé.
 */
new #[Title('Kanban')] class extends Component
{
    // Route::livewire('projects/{project}/board', ...) : le binding de route
    // hydrate directement cette propriété, aucun mount() nécessaire pour ça.
    public Project $project;

    public bool $movedByOther = false;

    public function mount(): void
    {
        $this->authorize('view', $this->project);
    }

    /**
     * TaskMoved (Module 8) diffuse déjà sur ce canal privé — l'ancienne vue
     * Blade l'écoutait en JS brut (window.Echo...) pour recharger toute la
     * page. Ici, l'événement rafraîchit juste ce qui a changé : {project.id}
     * dans l'attribut résout dynamiquement le canal par projet (un seul
     * tableau ne doit jamais recevoir les déplacements d'un autre projet).
     * Le "." devant task.moved : broadcastAs() renvoie un nom personnalisé,
     * pas le nom de la classe d'événement (convention Echo, voir events.md).
     */
    #[On('echo-private:projects.{project.id},.task.moved')]
    public function handleMovedElsewhere(): void
    {
        unset($this->tasksByStatus);

        $this->movedByOther = true;
    }

    /**
     * #[Computed] plutôt qu'une propriété publique exposant une Collection
     * Eloquent : mémoïsé pour la durée d'une seule requête (jamais entre deux
     * mises à jour), et jamais sérialisé dans l'état Livewire côté client —
     * une Collection de modèles en propriété publique le serait, exposant au
     * passage des données non destinées au front (voir security.md).
     */
    #[Computed]
    public function tasksByStatus()
    {
        return $this->project->tasks()
            ->with('assignee')
            ->get()
            ->groupBy(fn ($task) => $task->status->value);
    }

    /**
     * Nom de méthode "handleX" (convention du projet Livewire lui-même,
     * voir vendor/livewire/livewire/.claude/rules/writing-docs.md) : distingue
     * une action appelable depuis wire:click/$wire d'une méthode interne.
     *
     * Lookup scopé via la relation ($this->project->tasks(), jamais
     * Task::findOrFail()) : un id de tâche appartenant à un autre projet ne
     * peut jamais matcher, la policy ci-dessous devient une deuxième ligne de
     * défense plutôt que la seule.
     */
    public function handleMove(int $taskId, string $status): void
    {
        $task = $this->project->tasks()->findOrFail($taskId);

        $this->authorize('update', $task);

        app(MoveTaskAction::class)->handle($task, TaskStatus::from($status));

        unset($this->tasksByStatus);
    }
};
?>

<div>
    @if ($movedByOther)
        <div class="mb-4 flex items-center justify-between rounded-md bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
            Une tâche vient d'être déplacée par quelqu'un d'autre.
            <button type="button" wire:click="$set('movedByOther', false)" class="text-indigo-600 hover:underline">Masquer</button>
        </div>
    @endif

    <div class="mb-6">
        <a href="{{ route('projects.show', $project) }}" class="text-sm text-indigo-600 hover:underline">&larr; {{ $project->name }}</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Kanban — {{ $project->name }}</h1>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        @foreach (TaskStatus::cases() as $status)
            @php $columnTasks = $this->tasksByStatus[$status->value] ?? collect(); @endphp

            <div
                x-on:dragover.prevent
                x-on:drop="$wire.handleMove(event.dataTransfer.getData('text/plain'), '{{ $status->value }}')"
                class="rounded-lg border-2 border-dashed border-transparent p-1 transition-colors"
                x-on:dragenter.prevent="$el.classList.add('border-indigo-300')"
                x-on:dragleave="$el.classList.remove('border-indigo-300')"
                x-on:drop.window="$el.classList.remove('border-indigo-300')"
            >
                <h2 class="mb-3 flex items-center justify-between text-sm font-semibold tracking-wide text-slate-500 uppercase">
                    {{ $status->label() }}
                    <x-badge :status="$status->value">{{ $columnTasks->count() }}</x-badge>
                </h2>

                <div class="space-y-3">
                    @forelse ($columnTasks as $task)
                        <x-card
                            wire:key="task-{{ $task->id }}"
                            draggable="true"
                            x-on:dragstart="event.dataTransfer.setData('text/plain', '{{ $task->id }}'); $el.classList.add('opacity-40')"
                            x-on:dragend="$el.classList.remove('opacity-40')"
                            class="cursor-move"
                        >
                            <p class="font-medium">{{ $task->title }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $task->assignee?->name ?? 'Non assigné' }}</p>
                        </x-card>
                    @empty
                        <p class="text-sm text-slate-400">Aucune tâche.</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
