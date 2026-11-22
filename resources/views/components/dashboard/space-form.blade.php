@props([
    'space' => null,
    'action' => null,
    'method' => 'POST',
])

@php
    $isEdit = $space !== null;
    $selectedTheme = old('theme', $space?->theme?->value ?? App\Enums\SpaceTheme::MinimalLight->value);
    $selectedOrder = old('field_order', $space?->fieldOrder() ?? App\Enums\SpaceField::defaultOrder());
    $ratingEnabled = (bool) old('rating_enabled', $space?->rating_enabled ?? false);
@endphp

<form
    method="POST"
    action="{{ $action }}"
    class="space-y-8"
>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-ui.card>
        <x-slot:title>Space details</x-slot>
        <x-slot:description>The basics customers see on your public page.</x-slot:description>

        <div class="space-y-5">
            <x-ui.input
                name="name"
                label="Space name"
                :value="$space?->name"
                placeholder="Acme Reviews"
                required
            />

            <x-ui.input
                name="title"
                label="Title"
                :value="$space?->title"
                placeholder="Share your experience with Acme"
                required
            />

            <x-ui.input
                name="subtitle"
                label="Subtitle"
                :value="$space?->subtitle"
                placeholder="A sentence that sets the scene"
            />

            <div>
                <x-ui.label for="request" required>Testimonial request</x-ui.label>
                <textarea
                    id="request"
                    name="request"
                    rows="4"
                    maxlength="2000"
                    placeholder="What would you like your customers to say?"
                    class="block w-full rounded-lg border-0 bg-surface px-3 py-2 text-sm text-ink shadow-sm ring-1 ring-inset ring-border-subtle placeholder:text-ink-subtle focus:ring-2 focus:ring-inset focus:ring-brand-500"
                >{{ old('request', $space?->request) }}</textarea>
                <p class="mt-1.5 text-xs text-ink-muted">This message is shown to the customer on your public page. Leave it empty to use a default.</p>
                @error('request')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </x-ui.card>

    <x-ui.card>
        <x-slot:title>Theme</x-slot:title>
        <x-slot:description>Pick how your public page looks.</x-slot:description>

        <x-dashboard.theme-picker name="theme" :value="$selectedTheme" />
    </x-ui.card>

    <x-ui.card>
        <x-slot:title>Fields customers fill in</x-slot:title>
        <x-slot:description>Choose what customers are asked for. Name and Email are always collected.</x-slot:description>

        <div class="space-y-6">
            <x-dashboard.space-field-toggles :space="$space" />

            <x-dashboard.field-order-list :order="$selectedOrder" />
        </div>
    </x-ui.card>

    <x-ui.card>
        <x-slot:title>Ratings</x-slot:title>
        <x-slot:description>Let customers rate their experience out of five.</x-slot:description>

        <label class="inline-flex cursor-pointer items-center gap-2">
            <input
                type="hidden"
                name="rating_enabled"
                value="0"
            >
            <input
                type="checkbox"
                name="rating_enabled"
                value="1"
                @checked($ratingEnabled)
                class="size-4 rounded border-border-subtle text-brand-600 focus:ring-brand-500"
            >
            <span class="text-sm text-ink">Ask customers for a rating</span>
        </label>

        @error('rating_enabled')
            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </x-ui.card>

    <div class="flex flex-wrap items-center gap-3">
        <x-ui.button type="submit">{{ $isEdit ? 'Save changes' : 'Create space' }}</x-ui.button>
        <x-ui.button
            href="{{ route('dashboard.spaces.index') }}"
            variant="ghost"
        >Cancel</x-ui.button>
    </div>
</form>
