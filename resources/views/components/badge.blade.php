@props(['status' => 'default'])

@php
    $colors = match ($status) {
        'archived' => 'bg-slate-200 text-slate-700',
        'active' => 'bg-emerald-100 text-emerald-800',
        'todo' => 'bg-slate-100 text-slate-700',
        'in_progress' => 'bg-amber-100 text-amber-800',
        'done' => 'bg-emerald-100 text-emerald-800',
        default => 'bg-slate-100 text-slate-700',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium $colors"]) }}>
    {{ $slot }}
</span>
