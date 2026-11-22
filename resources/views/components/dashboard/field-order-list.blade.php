@props(['order' => null])

@php
    $known = App\Enums\SpaceField::values();

    // The chosen order first, then any field the owner left out appended
    // in the canonical sequence. A field is never dropped from the form.
    $selected = [];

    foreach (($order ?: App\Enums\SpaceField::defaultOrder()) as $field) {
        if (in_array($field, $known, true) && ! in_array($field, $selected, true)) {
            $selected[] = $field;
        }
    }

    foreach ($known as $field) {
        if (! in_array($field, $selected, true)) {
            $selected[] = $field;
        }
    }
@endphp

<fieldset {{ $attributes->merge(['class' => 'space-y-4']) }}>
    <legend class="text-sm font-medium text-ink">Field order</legend>
    <p class="text-xs text-ink-muted">The order customers see the fields in. Name and Email are always collected.</p>

    <ul class="divide-y divide-border-subtle rounded-lg bg-surface shadow-sm ring-1 ring-inset ring-border-subtle">
        @foreach ($selected as $field)
            @php
                $fieldEnum = App\Enums\SpaceField::from($field);
                $position = array_search($field, $selected, true);
            @endphp

            <li class="flex items-center justify-between gap-3 px-4 py-3">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-ink">{{ $fieldEnum->label() }}</p>
                    @if ($fieldEnum->isAlwaysEnabled())
                        <p class="mt-0.5 text-xs text-ink-subtle">Always collected and required</p>
                    @endif
                </div>

                <input
                    type="hidden"
                    name="field_order[]"
                    value="{{ $field }}"
                >

                <span class="flex items-center gap-2">
                    <span class="flex size-6 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">{{ $position + 1 }}</span>
                    <span class="text-xs text-ink-muted">
                        @if ($fieldEnum->isAlwaysEnabled())
                            Always collected
                        @else
                            Collected
                        @endif
                    </span>
                </span>
            </li>
        @endforeach
    </ul>

    <p class="text-xs text-ink-subtle">
        Name and Email always appear first. The remaining fields follow the order you choose.
    </p>
</fieldset>
