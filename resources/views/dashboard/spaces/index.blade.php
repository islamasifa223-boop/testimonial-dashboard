<x-layouts.app title="Spaces">
    <div class="space-y-8">
        <x-dashboard.section-heading
            title="Your spaces"
            description="Each space is a branded page where customers can share a testimonial."
        >
            @if ($canCreateSpace)
                <x-ui.button href="{{ route('dashboard.spaces.create') }}">Create space</x-ui.button>
            @endif
        </x-dashboard.section-heading>

        <x-dashboard.upgrade-banner
            :plan="auth()->user()->plan"
            :spaces-used="$spacesUsed"
            :spaces-limit="$spacesLimit"
        />

        @if ($spaces->isEmpty())
            <x-ui.empty-state
                icon="sparkles"
                title="You don't have any spaces yet"
                description="A Space is a branded page where customers can share a testimonial. Create one, share the link, and start collecting kind words."
            >
                @if ($canCreateSpace)
                    <x-ui.button href="{{ route('dashboard.spaces.create') }}">Create your first space</x-ui.button>
                @else
                    <x-ui.button href="{{ route('dashboard.billing') }}" variant="secondary">View plans</x-ui.button>
                @endif
            </x-ui.empty-state>

            <div class="rounded-xl bg-surface p-6 shadow-sm ring-1 ring-inset ring-border-subtle">
                <h3 class="text-sm font-semibold text-ink">How Kudosly works</h3>
                <ol class="mt-4 grid gap-6 sm:grid-cols-3">
                    <li class="flex gap-3">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">1</span>
                        <div>
                            <p class="text-sm font-medium text-ink">Create a space</p>
                            <p class="mt-1 text-sm text-ink-muted">Name it, pick a theme, and choose which fields customers fill in.</p>
                        </div>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">2</span>
                        <div>
                            <p class="text-sm font-medium text-ink">Share the link</p>
                            <p class="mt-1 text-sm text-ink-muted">Send your public space URL to customers after they sign up.</p>
                        </div>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">3</span>
                        <div>
                            <p class="text-sm font-medium text-ink">Publish and embed</p>
                            <p class="mt-1 text-sm text-ink-muted">Approve the best ones and embed them on your own site.</p>
                        </div>
                    </li>
                </ol>
            </div>
        @else
            <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($spaces as $space)
                    <li class="rounded-xl bg-surface p-5 shadow-sm ring-1 ring-inset ring-border-subtle">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('dashboard.spaces.show', $space) }}" class="block truncate text-sm font-semibold text-ink hover:text-brand-700">{{ $space->name }}</a>
                                <p class="mt-0.5 truncate text-xs text-ink-subtle">{{ $space->publicPath() }}</p>
                            </div>
                            <x-ui.badge>{{ $space->theme->label() }}</x-ui.badge>
                        </div>
                        <p class="mt-3 line-clamp-2 text-sm text-ink-muted">{{ $space->subtitle ?? $space->title }}</p>
                        <div class="mt-4 flex items-center gap-2 border-t border-border-subtle pt-4">
                            <x-ui.button href="{{ route('dashboard.spaces.show', $space) }}" variant="secondary" size="sm">Manage</x-ui.button>
                            <x-ui.button href="{{ route('dashboard.spaces.edit', $space) }}" variant="ghost" size="sm">Edit</x-ui.button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.app>
