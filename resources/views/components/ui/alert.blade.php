@props(['variant' => 'success'])

@php
    $variants = [
        'success' => 'bg-emerald-50 text-emerald-800 ring-1 ring-inset ring-emerald-200',
        'error' => 'bg-red-50 text-red-800 ring-1 ring-inset ring-red-200',
        'info' => 'bg-brand-50 text-brand-800 ring-1 ring-inset ring-brand-200',
        'warning' => 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-200',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg px-4 py-3 text-sm '.($variants[$variant] ?? $variants['info'])]) }}>
    {{ $slot }}
</div>
