<x-layout title="Mon profil — TaskFlow">
    <h1 class="mb-6 text-2xl font-bold text-slate-900">Mon profil</h1>

    <div class="max-w-xl space-y-6">
        <x-card>
            @include('profile.partials.update-profile-information-form')
        </x-card>

        <x-card>
            @include('profile.partials.update-password-form')
        </x-card>

        <x-card>
            @include('profile.partials.delete-user-form')
        </x-card>
    </div>
</x-layout>
