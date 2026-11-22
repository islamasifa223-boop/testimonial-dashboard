<x-layouts.app title="Wall of Love">
    <div class="space-y-8">
        <x-dashboard.section-heading
            title="Wall of Love"
            description="A public page showcasing the testimonials you've featured."
        />

        @if ($spaces->isEmpty())
            <x-ui.empty-state
                icon="sparkles"
                title="You don't have any spaces yet"
                description="A Wall of Love belongs to a space. Create one, collect testimonials, and heart the best ones to fill your wall."
            >
                <x-ui.button href="{{ route('dashboard.spaces.create') }}">Create a space</x-ui.button>
            </x-ui.empty-state>
        @else
            <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($spaces as $space)
                    <li class="flex flex-col rounded-xl bg-surface p-5 shadow-sm ring-1 ring-inset ring-border-subtle">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('dashboard.spaces.show', $space) }}" class="block truncate text-sm font-semibold text-ink hover:text-brand-700">{{ $space->name }}</a>
                                <p class="mt-0.5 truncate text-xs text-ink-subtle">{{ $space->wallPath() }}</p>
                            </div>
                            <x-ui.badge>{{ $space->wall_count }} on wall</x-ui.badge>
                        </div>

                        <div class="mt-4 flex items-center gap-2 border-t border-border-subtle pt-4">
                            <x-ui.button href="{{ route('walls.show', $space->slug) }}" variant="secondary" size="sm" target="_blank" rel="noopener">View wall</x-ui.button>
                            <x-ui.button href="{{ route('dashboard.spaces.show', $space) }}" variant="ghost" size="sm">Manage</x-ui.button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($excluded->isNotEmpty())
            <x-ui.card>
                <x-slot:title>Hearts that are not on the wall</x-slot:title>
                <x-slot:description>These testimonials have a heart, but they are not shown on the public wall yet.</x-slot:description>

                <ul class="divide-y divide-border-subtle">
                    @foreach ($excluded as $item)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0">
                            <p class="min-w-0 truncate text-sm font-medium text-ink">{{ $item['testimonial']->name }}</p>

                            <div class="flex flex-wrap items-center gap-2">
                                @foreach ($item['reasons'] as $reason)
                                    <x-ui.badge variant="warning">{{ $reason }}</x-ui.badge>
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        <x-ui.card>
            <x-slot:title>How the Wall of Love works</x-slot:title>

            <ul class="space-y-2 text-sm text-ink-muted">
                <li>· Every space gets its own public page at <span class="font-mono text-xs">/wall/your-space</span></li>
                <li>· A testimonial is shown only when it is hearted, approved, not hidden, and consented</li>
                <li>· Customer email addresses are never displayed</li>
            </ul>
        </x-ui.card>
    </div>
</x-layouts.app>
