{{--
    A modal dialog.

    The component renders the overlay and the panel. The open state stays
    with the caller: put x-data="{ open: false }" on an ancestor, and the
    component reads that "open" through Alpine's scope chain. Nothing
    here declares its own x-data, because a nested x-data would shadow
    the caller's state instead of extending it.

    The trigger that opens the modal may sit outside the component. It
    can focus the panel's cancel button with
    $nextTick(() => $refs.cancel.focus()), because Alpine's $refs walks
    up the tree and the panel is a sibling of the trigger.

    Every attribute that is not a prop lands on the overlay, which is
    where the show, the backdrop dismissal and the escape handling live.
--}}

@props([
    'title',
    'description' => null,
    'maxWidth' => 'max-w-md',
])

@php
    // The heading carries the id, and the dialog names itself with that
    // same id. A caller passes it so a page of repeated modals keeps
    // every id unique.
    $headingId = $attributes->get('id') ?? 'modal-'.md5($title);
@endphp

<div
    {{ $attributes->except('id')->merge(['class' => 'fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4']) }}
    x-show="open"
    x-cloak
    x-on:click.self="open = false"
    x-on:keydown.escape.window="open = false"
>
    <div
        class="w-full {{ $maxWidth }} rounded-xl bg-surface p-6 shadow-lg"
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $headingId }}"
        x-trap.noscroll="open"
    >
        <h3 id="{{ $headingId }}" class="text-base font-semibold text-ink">
            {{ $title }}
        </h3>

        @if ($description)
            <p class="mt-2 text-sm text-ink-muted">{{ $description }}</p>
        @endif

        @if (! $slot->isEmpty())
            <div class="mt-4 text-sm text-ink-muted">
                {{ $slot }}
            </div>
        @endif

        @isset($footer)
            <div class="mt-6 flex flex-wrap justify-end gap-3">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>
