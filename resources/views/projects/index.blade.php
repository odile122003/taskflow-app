<x-layout title="Projets — TaskFlow">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-slate-900">Projets</h1>
        <x-button :href="route('projects.create')">Nouveau projet</x-button>
    </div>

    <div class="space-y-4">
        @forelse ($projects as $project)
            <x-card>
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">
                            <a href="{{ route('projects.show', $project) }}" class="hover:underline">
                                {{ $project->name }}
                            </a>
                        </h2>
                        <p class="text-sm text-slate-500">/{{ $project->slug }}</p>
                        <p class="mt-1 text-xs text-slate-400">
                            Projet {{ $loop->iteration }} / {{ $loop->count }}
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <x-badge :status="$project->is_archived ? 'archived' : 'active'">
                            {{ $project->is_archived ? 'Archivé' : 'Actif' }}
                        </x-badge>

                        <x-modal>
                            <x-slot:trigger>
                                <x-button variant="secondary">Aperçu</x-button>
                            </x-slot:trigger>

                            <h3 class="text-lg font-semibold">{{ $project->name }}</h3>
                            <p class="mt-2 text-sm text-slate-600">Identifiant : {{ $project->slug }}</p>
                            <p class="mt-1 text-sm text-slate-600">
                                Statut :
                                <x-badge :status="$project->is_archived ? 'archived' : 'active'">
                                    {{ $project->is_archived ? 'Archivé' : 'Actif' }}
                                </x-badge>
                            </p>
                        </x-modal>
                    </div>
                </div>
            </x-card>
        @empty
            <x-card class="text-center text-slate-500">
                Aucun projet pour l'instant.
            </x-card>
        @endforelse
    </div>
</x-layout>
