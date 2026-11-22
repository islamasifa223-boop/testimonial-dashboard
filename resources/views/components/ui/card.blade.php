@props(['padding' => 'p-6'])

<div {{ $attributes->merge(['class' => 'rounded-xl bg-surface shadow-sm ring-1 ring-inset ring-border-subtle']) }}>
    @if (isset($title) || isset($description))
        <div class="border-b border-border-subtle px-6 py-4">
            @isset($title)
                <h3 class="text-sm font-semibold text-ink">{{ $title }}</h3>
            @endisset
            @isset($description)
                <p class="mt-0.5 text-sm text-ink-muted">{{ $description }}</p>
            @endisset
        </div>
    @endif

    <div class="{{ $padding }}">
        {{ $slot }}
    </div>
</div>
