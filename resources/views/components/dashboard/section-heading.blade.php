@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-end justify-between gap-3']) }}>
    <div>
        <h2 class="text-lg font-semibold tracking-tight text-ink">{{ $title }}</h2>
        @if ($description)
            <p class="mt-1 text-sm text-ink-muted">{{ $description }}</p>
        @endif
    </div>

    @if (! $slot->isEmpty())
        <div class="flex items-center gap-2">
            {{ $slot }}
        </div>
    @endif
</div>
