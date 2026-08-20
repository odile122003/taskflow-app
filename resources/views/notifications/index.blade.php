<x-layout title="Notifications — TaskFlow">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-slate-900">Notifications</h1>

        @if ($notifications->contains(fn ($n) => $n->read_at === null))
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <x-button type="submit" variant="secondary">Tout marquer comme lu</x-button>
            </form>
        @endif
    </div>

    <div class="space-y-3">
        @forelse ($notifications as $notification)
            <x-card :padded="false" class="flex items-center justify-between px-4 py-3 {{ $notification->read_at === null ? 'border-indigo-300 bg-indigo-50/40' : '' }}">
                <div>
                    <p class="text-sm">{{ $notification->data['message'] ?? 'Notification' }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</p>
                </div>

                @if ($notification->read_at === null)
                    <form method="POST" action="{{ route('notifications.read', $notification) }}">
                        @csrf
                        <button type="submit" class="text-xs text-indigo-600 hover:underline">Marquer comme lu</button>
                    </form>
                @endif
            </x-card>
        @empty
            <x-card class="text-center text-slate-500">
                Aucune notification pour l'instant.
            </x-card>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $notifications->links() }}
    </div>
</x-layout>
