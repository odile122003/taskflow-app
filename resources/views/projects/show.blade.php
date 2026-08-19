<x-layout :title="$project->name.' — TaskFlow'">
    <div class="mb-6">
        <a href="{{ route('projects.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Tous les projets</a>
        <div class="mt-2 flex items-center gap-3">
            <h1 class="text-2xl font-bold text-slate-900">{{ $project->name }}</h1>
            <x-badge :status="$project->is_archived ? 'archived' : 'active'">
                {{ $project->is_archived ? 'Archivé' : 'Actif' }}
            </x-badge>
        </div>
    </div>

    <div class="mb-4">
        <x-button :href="route('projects.board', $project)" variant="secondary">Vue kanban</x-button>
    </div>

    <h2 class="mb-3 text-lg font-semibold text-slate-900">Tâches</h2>

    <div class="space-y-3">
        @forelse ($project->tasks as $task)
            <x-card :padded="false" class="flex items-center justify-between px-4 py-3">
                <span>{{ $task->title }}</span>
                <x-badge :status="$task->status->value">{{ $task->status->label() }}</x-badge>
            </x-card>
        @empty
            <x-card class="text-center text-slate-500">
                Aucune tâche pour ce projet.
            </x-card>
        @endforelse
    </div>
</x-layout>
