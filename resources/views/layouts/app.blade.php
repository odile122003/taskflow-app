{{-- Layout attendu par Route::livewire() pour les composants "pages::" (Module 13).
     Délègue entièrement au composant <x-layout> existant (Module 2) plutôt que de
     dupliquer l'en-tête/nav — un seul endroit à modifier pour changer la mise en page. --}}
<x-layout :title="$title ?? 'TaskFlow'">
    {{ $slot }}
</x-layout>
