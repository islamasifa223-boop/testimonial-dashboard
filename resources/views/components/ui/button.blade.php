@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition focus-ring disabled:cursor-not-allowed disabled:opacity-60';

    $variants = [
        'primary' => 'bg-brand-600 text-white shadow-sm hover:bg-brand-700',
        'secondary' => 'bg-surface text-ink ring-1 ring-inset ring-border-subtle shadow-sm hover:bg-surface-muted',
        'ghost' => 'text-ink-muted hover:bg-surface-muted hover:text-ink',
        'danger' => 'bg-red-600 text-white shadow-sm hover:bg-red-700',
    ];

    $sizes = [
        'sm' => 'px-2.5 py-1.5 text-xs',
        'md' => 'px-3.5 py-2 text-sm',
        'lg' => 'px-5 py-2.5 text-sm',
    ];

    $classes = implode(' ', [
        $base,
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
