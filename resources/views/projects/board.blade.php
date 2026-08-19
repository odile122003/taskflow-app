@php use App\Enums\TaskStatus; @endphp
<x-layout :title="$project->name.' — Kanban'">
    <div class="mb-6">
        <a href="{{ route('projects.show', $project) }}" class="text-sm text-indigo-600 hover:underline">&larr; {{ $project->name }}</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Kanban — {{ $project->name }}</h1>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        @foreach (TaskStatus::cases() as $status)
            @php $columnTasks = $tasksByStatus[$status->value] ?? collect(); @endphp
            <div>
                <h2 class="mb-3 flex items-center justify-between text-sm font-semibold tracking-wide text-slate-500 uppercase">
                    {{ $status->label() }}
                    <x-badge :status="$status->value">{{ $columnTasks->count() }}</x-badge>
                </h2>

                <div class="space-y-3">
                    @forelse ($columnTasks as $task)
                        <x-card>
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
</x-layout>
