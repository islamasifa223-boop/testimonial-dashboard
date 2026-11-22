<x-layouts.app title="Space created">
    <div class="mx-auto max-w-3xl space-y-8">
        <x-ui.card>
            <x-slot:title>Your space is ready</x-slot:title>
            <x-slot:description>Share this link with customers and they can leave you a testimonial.</x-slot:description>

            <div class="space-y-6">
                <x-dashboard.copyable-url :url="url($space->publicPath())" />

                <dl class="divide-y divide-border-subtle">
                    <div class="flex items-center justify-between gap-4 py-3 first:pt-0">
                        <dt class="text-sm text-ink-muted">Name</dt>
                        <dd class="text-sm font-medium text-ink">{{ $space->name }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-sm text-ink-muted">Theme</dt>
                        <dd><x-ui.badge>{{ $space->theme->label() }}</x-ui.badge></dd>
                    </div>
                </dl>

                <div class="flex flex-wrap items-center gap-3 border-t border-border-subtle pt-6">
                    <x-ui.button href="{{ route('dashboard.spaces.show', $space) }}">View space</x-ui.button>
                    <x-ui.button href="{{ route('dashboard.spaces.index') }}" variant="ghost">Back to spaces</x-ui.button>
                </div>
            </div>
        </x-ui.card>
    </div>
</x-layouts.app>
