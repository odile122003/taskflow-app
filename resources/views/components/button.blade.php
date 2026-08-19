@props(['variant' => 'primary', 'href' => null])

@php
    $base = 'inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-medium transition cursor-pointer';

    $variants = [
        'primary' => 'bg-indigo-600 text-white hover:bg-indigo-500',
        'secondary' => 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50',
    ];

    $classes = $base . ' ' . ($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
