@props(['variant' => 'neutral'])

@php
    $variants = [
        'neutral' => 'bg-surface-muted text-ink-muted ring-1 ring-inset ring-border-subtle',
        'brand' => 'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-200',
        'success' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200',
        'warning' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200',
        'rose' => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium '.($variants[$variant] ?? $variants['neutral'])]) }}>
    {{ $slot }}
</span>
