@props(['name' => 'theme', 'value' => null])

@php
    $preview = app(App\Services\SpaceThemePreview::class);
    $selected = old($name, $value);
@endphp

<fieldset {{ $attributes->merge(['class' => 'space-y-3']) }}>
    <legend class="text-sm font-medium text-ink">Theme</legend>

    <div class="grid gap-3 sm:grid-cols-3">
        @foreach (App\Enums\SpaceTheme::cases() as $theme)
            @php
                $swatch = $preview->swatch($theme);
                $isSelected = $selected === $theme->value;
            @endphp

            <label class="relative flex cursor-pointer flex-col gap-2 rounded-xl bg-surface p-3 shadow-sm ring-1 ring-inset transition
                {{ $isSelected ? 'ring-2 ring-brand-500' : 'ring-border-subtle hover:ring-border-subtle' }}">
                <input
                    type="radio"
                    name="{{ $name }}"
                    value="{{ $theme->value }}"
                    @checked($isSelected)
                    class="sr-only"
                >

                <span class="flex h-24 items-center justify-center rounded-lg {{ $swatch['surface'] }}">
                    <span class="rounded-md px-3 py-1.5 text-xs font-semibold {{ $swatch['accent'] }} {{ $swatch['text'] }}">
                        Aa
                    </span>
                </span>

                <span class="flex items-center gap-2">
                    @if ($isSelected)
                        <svg class="size-4 shrink-0 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    @else
                        <span class="size-4 shrink-0"></span>
                    @endif

                    <span class="min-w-0">
                        <span class="block text-sm font-medium text-ink">{{ $theme->label() }}</span>
                        <span class="mt-0.5 block text-xs text-ink-muted">{{ $preview->description($theme) }}</span>
                    </span>
                </span>
            </label>
        @endforeach
    </div>

    @error($name)
        <p class="text-xs text-red-600">{{ $message }}</p>
    @enderror
</fieldset>
