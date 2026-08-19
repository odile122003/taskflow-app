<x-layout :title="'Modifier '.$project->name.' — TaskFlow'">
    <h1 class="mb-6 text-2xl font-bold text-slate-900">Modifier « {{ $project->name }} »</h1>

    <x-card>
        <form method="POST" action="{{ route('projects.update', $project) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="name" class="block text-sm font-medium text-slate-700">Nom</label>
                <input
                    type="text" name="name" id="name" value="{{ old('name', $project->name) }}"
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                >
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="slug" class="block text-sm font-medium text-slate-700">Identifiant (slug)</label>
                <input
                    type="text" name="slug" id="slug" value="{{ old('slug', $project->slug) }}"
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                >
                @error('slug')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="color" class="block text-sm font-medium text-slate-700">Couleur</label>
                <input
                    type="text" name="color" id="color" value="{{ old('color', $project->color) }}"
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                >
                @error('color')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-2">
                <input
                    type="checkbox" name="is_archived" id="is_archived" value="1"
                    @checked(old('is_archived', $project->is_archived))
                    class="rounded border-slate-300"
                >
                <label for="is_archived" class="text-sm text-slate-700">Archivé</label>
            </div>

            <div class="flex justify-end gap-3">
                <x-button :href="route('projects.show', $project)" variant="secondary">Annuler</x-button>
                <x-button type="submit">Enregistrer</x-button>
            </div>
        </form>
    </x-card>
</x-layout>
