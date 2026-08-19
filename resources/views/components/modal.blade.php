@props(['trigger' => null])

<div x-data="{ open: false }" @keydown.escape.window="open = false" class="inline-block">
    @isset($trigger)
        <span @click="open = true" class="inline-block">{{ $trigger }}</span>
    @endisset

    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    >
        <div @click.outside="open = false" class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
            {{ $slot }}
        </div>
    </div>
</div>
