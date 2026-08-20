@php use App\Enums\TaskStatus; @endphp
<x-layout :title="$project->name.' — Kanban'">
    <div
        x-data="{
            banner: false,
            moveTask(taskId, status) {
                fetch(`{{ url('/projects/'.$project->slug.'/tasks') }}/${taskId}`, {
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ status }),
                });
            },
        }"
        x-init="
            window.Echo.private('projects.{{ $project->id }}')
                .listen('.task.moved', () => {
                    banner = true;
                    setTimeout(() => window.location.reload(), 1500);
                });
        "
    >
        <div x-show="banner" x-cloak class="mb-4 rounded-md bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
            Une tâche vient d'être déplacée — actualisation…
        </div>

        <div class="mb-6">
            <a href="{{ route('projects.show', $project) }}" class="text-sm text-indigo-600 hover:underline">&larr; {{ $project->name }}</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">Kanban — {{ $project->name }}</h1>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            @php $cases = TaskStatus::cases(); @endphp
            @foreach ($cases as $status)
                @php $columnTasks = $tasksByStatus[$status->value] ?? collect(); $index = array_search($status, $cases, true); @endphp
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

                                <div class="mt-3 flex gap-3">
                                    @if ($index > 0)
                                        <button
                                            type="button"
                                            @click="moveTask({{ $task->id }}, '{{ $cases[$index - 1]->value }}')"
                                            class="text-xs text-slate-500 hover:text-slate-900"
                                        >&larr; {{ $cases[$index - 1]->label() }}</button>
                                    @endif
                                    @if ($index < count($cases) - 1)
                                        <button
                                            type="button"
                                            @click="moveTask({{ $task->id }}, '{{ $cases[$index + 1]->value }}')"
                                            class="text-xs text-indigo-600 hover:text-indigo-900"
                                        >{{ $cases[$index + 1]->label() }} &rarr;</button>
                                    @endif
                                </div>
                            </x-card>
                        @empty
                            <p class="text-sm text-slate-400">Aucune tâche.</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-layout>
