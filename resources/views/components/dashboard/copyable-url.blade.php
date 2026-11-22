@props(['url' => null, 'label' => 'Copy this link', 'hint' => null])

@if ($url)
    <div {{ $attributes->merge(['class' => 'space-y-2']) }}>
        <label for="copyable-url" class="block text-sm font-medium text-ink">{{ $label }}</label>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <input
                id="copyable-url"
                type="text"
                readonly
                value="{{ $url }}"
                onclick="this.select()"
                class="block w-full flex-1 rounded-lg border-0 bg-surface px-3 py-2 text-sm text-ink shadow-sm ring-1 ring-inset ring-border-subtle focus:ring-2 focus:ring-inset focus:ring-brand-500"
            >

            <x-ui.button
                type="button"
                variant="secondary"
                onclick="navigator.clipboard.writeText(this.previousElementSibling.value); this.querySelector('[data-copy-label]').textContent='Copied';"
            >
                <span data-copy-label>Copy link</span>
            </x-ui.button>
        </div>

        <p class="text-xs text-ink-muted">
            {{ $hint ?? 'Send this link to a customer and they can leave you a testimonial on this space.' }}
        </p>
    </div>
@endif
