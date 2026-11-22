@props(['space' => null])

@php
    $enabledFor = function (App\Enums\SpaceField $field) use ($space): bool {
        if ($field->isAlwaysEnabled()) {
            return true;
        }

        $old = old($field->value.'_enabled');

        if ($old !== null) {
            return (bool) $old;
        }

        return $space ? $space->isFieldEnabled($field) : false;
    };

    $requiredFor = function (App\Enums\SpaceField $field) use ($space): bool {
        if ($field->isAlwaysRequired()) {
            return true;
        }

        $old = old($field->value.'_required');

        if ($old !== null) {
            return (bool) $old;
        }

        return $space ? $space->isFieldRequired($field) : false;
    };
@endphp

<fieldset {{ $attributes->merge(['class' => 'space-y-4']) }}>
    <legend class="text-sm font-medium text-ink">Fields customers fill in</legend>

    <div class="space-y-3">
        @foreach (App\Enums\SpaceField::cases() as $field)
            @php
                $enabled = $enabledFor($field);
                $required = $requiredFor($field);
            @endphp

            <div class="rounded-lg bg-surface p-4 shadow-sm ring-1 ring-inset ring-border-subtle">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-ink">{{ $field->label() }}</p>
                        <p class="mt-0.5 text-xs text-ink-muted">
                            @if ($field->isAlwaysEnabled())
                                Always collected, and always required.
                            @else
                                Choose whether customers are asked for this.
                            @endif
                        </p>
                    </div>

                    <label class="inline-flex shrink-0 cursor-pointer items-center gap-2">
                        <span class="text-xs text-ink-muted">Show</span>
                        <input
                            type="checkbox"
                            name="{{ $field->value }}_enabled"
                            value="1"
                            @checked($enabled)
                            @disabled($field->isAlwaysEnabled())
                            class="size-4 rounded border-border-subtle text-brand-600 focus:ring-brand-500 disabled:opacity-60"
                        >
                    </label>
                </div>

                @unless ($field->isAlwaysEnabled())
                    <label class="mt-3 inline-flex cursor-pointer items-center gap-2 border-t border-border-subtle pt-3">
                        <input
                            type="checkbox"
                            name="{{ $field->value }}_required"
                            value="1"
                            @checked($required)
                            class="size-4 rounded border-border-subtle text-brand-600 focus:ring-brand-500 disabled:opacity-60"
                        >
                        <span class="text-xs text-ink-muted">Make it required</span>
                    </label>
                @endunless
            </div>
        @endforeach
    </div>
</fieldset>
