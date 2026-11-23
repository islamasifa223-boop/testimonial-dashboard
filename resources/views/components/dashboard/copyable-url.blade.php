@props(['url' => null, 'label' => 'Copy this link', 'hint' => null])

@if ($url)
    @php
        // Each instance gets its own id, so a page that shows the component
        // more than once never repeats an id. The uuid is generated for
        // every render, which is what makes it unique per instance.
        $instanceId = 'copyable-url-'.Str::uuid()->toString();
        $fieldId = $instanceId.'-field';
        $statusId = $instanceId.'-status';
    @endphp

    {{-- The scope holds both the field and the button, so that the button
         can read both the copied state and the value of the field. --}}
    <div
        {{ $attributes->merge(['class' => 'space-y-2']) }}
        x-data="{ copied: false, message: '' }"
        @copy-confirmed.window="message = 'Copied to the clipboard.'"
    >
        <label for="{{ $fieldId }}" class="block text-sm font-medium text-ink">{{ $label }}</label>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <input
                id="{{ $fieldId }}"
                x-ref="source"
                type="text"
                readonly
                value="{{ $url }}"
                onclick="this.select()"
                class="block w-full flex-1 rounded-lg border-0 bg-surface px-3 py-2 text-sm text-ink shadow-sm ring-1 ring-inset ring-border-subtle focus:ring-2 focus:ring-inset focus:ring-brand-500"
            >

            {{-- The label only changes once the clipboard write has actually
                 succeeded, so a failed copy never claims to have worked. The
                 change is reverted after a moment, so the button can be used
                 again. --}}
            <x-ui.button
                type="button"
                variant="secondary"
                @click="navigator.clipboard.writeText($refs.source.value).then(() => { copied = true; message = 'Copied to the clipboard.'; setTimeout(() => copied = false, 2000) }).catch(() => {})"
            >
                <span x-show="!copied" data-copy-label>Copy link</span>
                <span x-show="copied" x-cloak>Copied!</span>
            </x-ui.button>
        </div>

        {{-- The confirmation is read out as well as shown, so that a copy
             is not silent for anyone who cannot see the button. The region
             starts empty, so nothing is announced on page load. The message
             is only set once the clipboard write has succeeded. --}}
        <p
            id="{{ $statusId }}"
            class="sr-only"
            role="status"
            aria-live="polite"
            x-text="message"
        ></p>

        <p class="text-xs text-ink-muted">
            {{ $hint ?? 'Send this link to a customer and they can leave you a testimonial on this space.' }}
        </p>
    </div>
@endif
