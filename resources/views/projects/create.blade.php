<x-layout title="Nouveau projet — TaskFlow">
    <h1 class="mb-6 text-2xl font-bold text-slate-900">Nouveau projet</h1>

    <x-card>
        <form method="POST" action="{{ route('projects.store') }}" class="space-y-4">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium text-slate-700">Nom</label>
                <input
                    type="text" name="name" id="name" value="{{ old('name') }}"
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                >
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="slug" class="block text-sm font-medium text-slate-700">Identifiant (slug)</label>
                <input
                    type="text" name="slug" id="slug" value="{{ old('slug') }}"
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                >
                @error('slug')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="color" class="block text-sm font-medium text-slate-700">Couleur</label>
                <input
                    type="text" name="color" id="color" value="{{ old('color') }}" placeholder="#6366f1"
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                >
                @error('color')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end gap-3">
                <x-button :href="route('projects.index')" variant="secondary">Annuler</x-button>
                <x-button type="submit">Créer</x-button>
            </div>
        </form>
    </x-card>
</x-layout>
