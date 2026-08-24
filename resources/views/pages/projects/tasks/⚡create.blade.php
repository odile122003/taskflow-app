<?php

use App\Actions\Tasks\CreateTaskAction;
use App\DataTransferObjects\CreateTaskData;
use App\Http\Requests\StoreTaskRequest;
use App\Models\Project;
use App\Models\Task;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Formulaire de création de tâche (Module 13) : le stub laissé en attente
 * depuis le Module 5 (TaskController::create() renvoyait un 501). wire:model
 * + validation temps réel — plus besoin d'un aller-retour complet de page
 * pour voir une erreur, ni de dupliquer les règles : rules() délègue à
 * StoreTaskRequest::rulesFor() (Module 5/10), la même validation qu'à l'API.
 */
new #[Title('Nouvelle tâche')] class extends Component
{
    public Project $project;

    #[Validate]
    public string $title = '';

    #[Validate]
    public string $priority = 'normal';

    #[Validate]
    public ?string $dueDate = null;

    #[Validate]
    public ?int $assigneeId = null;

    public function mount(): void
    {
        $this->authorize('create', [Task::class, $this->project]);
    }

    /**
     * Clés traduites en camelCase (convention du projet pour les variables,
     * voir CLAUDE.md) : StoreTaskRequest::rulesFor() reste en snake_case
     * (colonnes de base), Livewire doit retrouver ces règles sous le nom
     * exact des propriétés du composant.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = StoreTaskRequest::rulesFor($this->project);

        return [
            'title' => $rules['title'],
            'priority' => $rules['priority'],
            'dueDate' => $rules['due_date'],
            'assigneeId' => $rules['assignee_id'],
        ];
    }

    public function handleCreate(CreateTaskAction $action): void
    {
        $this->authorize('create', [Task::class, $this->project]);

        $validated = $this->validate();

        $data = new CreateTaskData(
            title: $validated['title'],
            priority: $validated['priority'],
            dueDate: $validated['dueDate'],
            assigneeId: $validated['assigneeId'],
            tagIds: [],
        );

        $action->handle($this->project, $data);

        $this->redirectRoute('projects.show', $this->project);
    }
};
?>

<div class="max-w-lg">
    <a href="{{ route('projects.show', $project) }}" class="text-sm text-indigo-600 hover:underline">&larr; {{ $project->name }}</a>
    <h1 class="mt-2 mb-6 text-2xl font-bold text-slate-900">Nouvelle tâche</h1>

    <form wire:submit="handleCreate" class="space-y-5">
        <div>
            <label for="title" class="block text-sm font-medium text-slate-700">Titre</label>
            <input
                id="title"
                type="text"
                wire:model.live.blur="title"
                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="priority" class="block text-sm font-medium text-slate-700">Priorité</label>
            <select
                id="priority"
                wire:model.live="priority"
                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
                <option value="low">Basse</option>
                <option value="normal">Normale</option>
                <option value="high">Haute</option>
            </select>
            @error('priority') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="dueDate" class="block text-sm font-medium text-slate-700">
                Échéance @if ($priority === 'high') <span class="text-red-500">*</span> @endif
            </label>
            <input
                id="dueDate"
                type="date"
                wire:model.live.blur="dueDate"
                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
            @error('dueDate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="assigneeId" class="block text-sm font-medium text-slate-700">Assigné à</label>
            <select
                id="assigneeId"
                wire:model.live="assigneeId"
                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
                <option value="">Non assigné</option>
                @foreach ($project->team->users as $member)
                    <option value="{{ $member->id }}">{{ $member->name }}</option>
                @endforeach
            </select>
            @error('assigneeId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
            Créer la tâche
        </button>
    </form>
</div>
