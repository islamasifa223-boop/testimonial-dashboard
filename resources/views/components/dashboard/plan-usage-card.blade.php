@props([
    'plan',
    'used',
    'limit',
    'unit' => 'spaces',
])

@php
    // A limit of zero is possible before a plan is assigned, so the ratio is
    // guarded and the progress bar is hidden rather than showing a
    // degenerate 0/0 range.
    $hasLimit = $limit > 0;
    $percentage = $hasLimit ? min(100, (int) round(($used / $limit) * 100)) : 0;
    $atLimit = $hasLimit && $used >= $limit;
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl bg-surface p-5 shadow-sm ring-1 ring-inset ring-border-subtle']) }}>
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm font-medium text-ink-muted">Usage</p>
        <x-ui.badge :variant="$plan?->isPro() ? 'brand' : 'neutral'">{{ $plan?->label ?? 'Free' }}</x-ui.badge>
    </div>

    <p class="mt-3 text-3xl font-semibold tracking-tight text-ink">
        {{ $used }}<span class="text-lg font-normal text-ink-subtle"> / {{ $limit }}</span>
    </p>
    <p class="mt-1 text-xs text-ink-subtle">{{ ucfirst($unit) }} used</p>

    @if ($hasLimit)
        <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-surface-muted" role="progressbar" aria-valuenow="{{ $used }}" aria-valuemin="0" aria-valuemax="{{ $limit }}" aria-label="{{ $unit }} used">
            <div class="h-full rounded-full {{ $atLimit ? 'bg-amber-500' : 'bg-brand-500' }}" style="width: {{ $percentage }}%"></div>
        </div>
    @else
        <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-surface-muted" aria-hidden="true"></div>
    @endif

    @if ($atLimit)
        <p class="mt-2 text-xs font-medium text-amber-700">You've reached the limit for the {{ $plan?->label ?? 'Free' }} plan.</p>
    @endif
</div>
