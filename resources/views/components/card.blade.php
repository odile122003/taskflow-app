@props(['padded' => true])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-slate-200 bg-white shadow-sm ' . ($padded ? 'p-6' : '')]) }}>
    {{ $slot }}
</div>
