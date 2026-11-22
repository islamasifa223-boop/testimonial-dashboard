<x-layouts.app :title="$space->name">
    <div class="mx-auto max-w-3xl space-y-8">
        <x-dashboard.section-heading
            :title="$space->name"
            :description="$space->subtitle ?? $space->title"
        >
            <x-ui.button href="{{ route('dashboard.spaces.edit', $space) }}" variant="secondary">Edit</x-ui.button>
        </x-dashboard.section-heading>

        <x-ui.card>
            <x-slot:title>Your public link</x-slot:title>
            <x-slot:description>Share this link with customers so they can leave a testimonial.</x-slot:description>

            <x-dashboard.copyable-url :url="url($space->publicPath())" />
        </x-ui.card>

        <x-ui.card>
            <x-slot:title>Space details</x-slot:title>
            <x-slot:description>How your space is currently set up.</x-slot:description>

            <dl class="divide-y divide-border-subtle">
                <div class="flex items-center justify-between gap-4 py-3 first:pt-0">
                    <dt class="text-sm text-ink-muted">Title</dt>
                    <dd class="text-sm font-medium text-ink">{{ $space->title }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 py-3">
                    <dt class="text-sm text-ink-muted">Subtitle</dt>
                    <dd class="text-sm font-medium text-ink">{{ $space->subtitle ?: '—' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 py-3">
                    <dt class="text-sm text-ink-muted">Theme</dt>
                    <dd><x-ui.badge>{{ $space->theme->label() }}</x-ui.badge></dd>
                </div>
                <div class="flex items-center justify-between gap-4 py-3">
                    <dt class="text-sm text-ink-muted">Ratings</dt>
                    <dd class="text-sm font-medium text-ink">{{ $space->rating_enabled ? 'Collected' : 'Not collected' }}</dd>
                </div>
            </dl>
        </x-ui.card>

        <x-ui.card>
            <x-slot:title>Fields collected</x-slot:title>
            <x-slot:description>The fields customers fill in, in the order they see them.</x-slot:description>

            <ul class="divide-y divide-border-subtle">
                @foreach ($space->fieldOrder() as $field)
                    @php
                        $fieldEnum = App\Enums\SpaceField::from($field);
                    @endphp

                    <li class="flex items-center justify-between gap-4 py-3 first:pt-0">
                        <span class="text-sm font-medium text-ink">{{ $fieldEnum->label() }}</span>
                        <span class="flex items-center gap-2">
                            @if ($space->isFieldEnabled($fieldEnum))
                                <x-ui.badge variant="success">Collected</x-ui.badge>
                                <x-ui.badge variant="neutral">{{ $space->isFieldRequired($fieldEnum) ? 'Required' : 'Optional' }}</x-ui.badge>
                            @else
                                <x-ui.badge variant="neutral">Not collected</x-ui.badge>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>

        <x-ui.card>
            <x-slot:title>Danger zone</x-slot:title>
            <x-slot:description>Deleting a space hides its public page. This cannot be undone.</x-slot:description>

            <form method="POST" action="{{ route('dashboard.spaces.destroy', $space) }}">
                @csrf
                @method('DELETE')

                <x-ui.button type="submit" variant="danger">Delete space</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-layouts.app>
