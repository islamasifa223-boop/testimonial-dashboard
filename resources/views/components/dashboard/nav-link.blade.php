@props(['href', 'active' => false, 'icon'])

@php
    $classes = 'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition '
        . ($active
            ? 'bg-brand-50 text-brand-700'
            : 'text-ink-muted hover:bg-surface-muted hover:text-ink');
@endphp

<a href="{{ $href }}" @if ($active) aria-current="page" @endif {{ $attributes->merge(['class' => $classes]) }}>
    <svg class="size-5 shrink-0 {{ $active ? 'text-brand-600' : 'text-ink-subtle group-hover:text-ink-muted' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
    </svg>
    <span>{{ $slot }}</span>
</a>
