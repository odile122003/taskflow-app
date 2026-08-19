@php use App\Enums\TaskStatus; @endphp
<x-layout title="Tableau de bord — TaskFlow">
    <h1 class="mb-6 text-2xl font-bold text-slate-900">Tableau de bord</h1>

    <h2 class="mb-3 text-lg font-semibold text-slate-900">Répartition des tâches par statut</h2>
    <div class="mb-8 flex gap-3">
        @foreach (TaskStatus::cases() as $status)
            <x-badge :status="$status->value">
                {{ $status->label() }} : {{ $statusCounts[$status->value] ?? 0 }}
            </x-badge>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        @foreach ($projects as $project)
            <x-card>
                <h2 class="text-lg font-semibold">{{ $project->name }}</h2>
                <p class="text-sm text-slate-500">{{ $project->tasks->count() }} tâche(s)</p>

                <ul class="mt-3 space-y-1 text-sm">
                    @foreach ($project->tasks as $task)
                        <li class="flex items-center justify-between">
                            <span>{{ $task->title }}</span>
                            <span class="text-slate-500">{{ $task->assignee?->name ?? 'Non assigné' }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endforeach
    </div>

    <h2 class="mt-8 mb-3 text-lg font-semibold text-slate-900">Top contributeurs ce mois</h2>

    <x-card :padded="false">
        <ol class="divide-y divide-slate-100">
            @forelse ($topContributors as $user)
                <li class="flex items-center justify-between px-4 py-3 text-sm">
                    <span>{{ $user->name }}</span>
                    <span class="text-slate-500">{{ $user->completed_this_month }} tâche(s) terminée(s)</span>
                </li>
            @empty
                <li class="px-4 py-3 text-center text-sm text-slate-500">Aucune tâche terminée ce mois.</li>
            @endforelse
        </ol>
    </x-card>

    <h2 class="mt-8 mb-3 text-lg font-semibold text-slate-900">Activité récente</h2>

    <div class="space-y-2">
        @forelse ($activities as $activity)
            <x-card :padded="false" class="px-4 py-3 text-sm">
                <span class="font-medium">{{ $activity->causer?->name ?? 'Système' }}</span>
                {{ $activity->description }}
                <span class="text-slate-400">— {{ $activity->subject?->title ?? $activity->subject_type }}</span>
            </x-card>
        @empty
            <x-card class="text-center text-slate-500">Aucune activité récente.</x-card>
        @endforelse
    </div>
</x-layout>
