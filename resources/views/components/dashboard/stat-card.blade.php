@props([
    'label',
    'value',
    'hint' => null,
    'icon' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-xl bg-surface p-5 shadow-sm ring-1 ring-inset ring-border-subtle']) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-sm font-medium text-ink-muted">{{ $label }}</p>

        @if ($icon)
            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-50">
                <svg class="size-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                </svg>
            </span>
        @endif
    </div>

    <p class="mt-3 text-3xl font-semibold tracking-tight text-ink">{{ $value }}</p>

    @if ($hint)
        <p class="mt-1 text-xs text-ink-subtle">{{ $hint }}</p>
    @endif
</div>
