@php
    // The controller passes the raw query string back. Only the values the
    // server actually honours may be marked as selected, so a hand edited
    // URL such as ?rating=9 can never look like a valid choice.
    $statusFilter = in_array($filters['status'], ['', 'pending', 'approved', 'hidden', 'on_wall', 'favorite'], true)
        ? $filters['status']
        : '';

    $ratingFilter = in_array($filters['rating'], ['', '0', '1', '2', '3', '4', '5'], true)
        ? $filters['rating']
        : '';

    // The space the filter selects. Only a space the account owns can be
    // selected, so a foreign or unknown id selects nothing.
    $spaceFilter = '';
    if ($filters['space'] !== '' && $spaces->contains('id', (int) $filters['space'])) {
        $spaceFilter = $filters['space'];
    }

    // Whether a filter was asked for at all. A space filter counts even
    // when it could not be honoured, because the controller empties the
    // inbox in that case rather than falling back to showing everything.
    // Falling back would show the owner every testimonial and make it
    // look as though the filter had been applied, which is misleading
    // and a quiet hint about what the account holds.
    $spaceFilterWasRequested = $filters['space'] !== '';

    $hasFilters = $filters['q'] !== '' || $statusFilter !== '' || $ratingFilter !== '' || $spaceFilterWasRequested;
@endphp

<x-layouts.app title="Inbox">
    <div class="space-y-8">
        <x-dashboard.section-heading
            title="Testimonial inbox"
            description="Review, approve, and organise the testimonials your customers send you."
        />

        <form method="GET" action="{{ route('dashboard.inbox') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2 lg:col-span-1">
                <x-ui.label for="q">Search</x-ui.label>
                <input
                    id="q"
                    name="q"
                    type="search"
                    value="{{ $filters['q'] }}"
                    placeholder="Name, company, or words"
                    class="block w-full rounded-lg border-0 bg-surface px-3 py-2 text-sm text-ink shadow-sm ring-1 ring-inset ring-border-subtle placeholder:text-ink-subtle focus:ring-2 focus:ring-inset focus:ring-brand-500"
                >
            </div>

            <div>
                <x-ui.label for="space">Space</x-ui.label>
                <select
                    id="space"
                    name="space"
                    class="block w-full rounded-lg border-0 bg-surface px-3 py-2 text-sm text-ink shadow-sm ring-1 ring-inset ring-border-subtle focus:ring-2 focus:ring-inset focus:ring-brand-500"
                >
                    <option value="">All spaces</option>
                    @foreach ($spaces as $space)
                        <option value="{{ $space->id }}" @selected($spaceFilter === (string) $space->id)>{{ $space->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-ui.label for="status">Status</x-ui.label>
                <select
                    id="status"
                    name="status"
                    class="block w-full rounded-lg border-0 bg-surface px-3 py-2 text-sm text-ink shadow-sm ring-1 ring-inset ring-border-subtle focus:ring-2 focus:ring-inset focus:ring-brand-500"
                >
                    <option value="">All statuses</option>
                    <option value="pending" @selected($statusFilter === 'pending')>Pending</option>
                    <option value="approved" @selected($statusFilter === 'approved')>Approved</option>
                    <option value="hidden" @selected($statusFilter === 'hidden')>Hidden</option>
                    <option value="on_wall" @selected($statusFilter === 'on_wall')>On the Wall</option>
                    <option value="favorite" @selected($statusFilter === 'favorite')>Favourites</option>
                </select>
            </div>

            <div>
                <x-ui.label for="rating">Rating</x-ui.label>
                <select
                    id="rating"
                    name="rating"
                    class="block w-full rounded-lg border-0 bg-surface px-3 py-2 text-sm text-ink shadow-sm ring-1 ring-inset ring-border-subtle focus:ring-2 focus:ring-inset focus:ring-brand-500"
                >
                    <option value="">Any rating</option>
                    <option value="0" @selected($ratingFilter === '0')>No rating</option>
                    @for ($star = 1; $star <= 5; $star++)
                        <option value="{{ $star }}" @selected($ratingFilter === (string) $star)>{{ $star }} star{{ $star === 1 ? '' : 's' }}</option>
                    @endfor
                </select>
            </div>

            <div class="flex items-end gap-3 sm:col-span-2 lg:col-span-4">
                <x-ui.button type="submit">Filter</x-ui.button>
                <x-ui.button href="{{ route('dashboard.inbox') }}" variant="ghost">Clear</x-ui.button>
            </div>
        </form>

        @if ($testimonials->isEmpty())
            @if ($hasFilters)
                <x-ui.empty-state
                    icon="funnel"
                    title="No testimonials match your filters"
                    description="Try a different search term, or clear the filters to see everything your customers have sent."
                >
                    <x-ui.button href="{{ route('dashboard.inbox') }}" variant="secondary">Clear filters</x-ui.button>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state
                    icon="inbox"
                    title="No testimonials yet"
                    description="Once you share a space link, customer submissions will appear here for you to approve, favourite, and publish."
                />
            @endif
        @else
            <ul class="space-y-4">
                @foreach ($testimonials as $testimonial)
                    <li class="rounded-xl bg-surface p-5 shadow-sm ring-1 ring-inset ring-border-subtle">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-semibold text-ink">{{ $testimonial->name }}</p>

                                    @if ($testimonial->company_name)
                                        <p class="text-sm text-ink-muted">{{ $testimonial->company_name }}</p>
                                    @endif

                                    <x-ui.badge variant="neutral">{{ $testimonial->space->name }}</x-ui.badge>

                                    @if ($testimonial->approved)
                                        <x-ui.badge variant="success">Approved</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="warning">Pending</x-ui.badge>
                                    @endif

                                    @if ($testimonial->getAttribute('hidden'))
                                        <x-ui.badge>Hidden</x-ui.badge>
                                    @endif

                                    @if ($testimonial->favorite)
                                        <x-ui.badge variant="brand">Favourite</x-ui.badge>
                                    @endif

                                    @if ($testimonial->hearted)
                                        <x-ui.badge variant="brand">On the Wall</x-ui.badge>
                                    @endif
                                </div>

                                <p class="mt-1 text-xs text-ink-subtle">{{ $testimonial->created_at->format('j M Y, H:i') }}</p>
                            </div>

                            @if ($testimonial->rating)
                                <span class="flex items-center gap-0.5" role="img" aria-label="{{ $testimonial->rating }} out of 5 stars">
                                    @for ($star = 1; $star <= 5; $star++)
                                        <svg class="size-4 {{ $star <= $testimonial->rating ? 'text-amber-500' : 'text-ink-subtle' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.563.563 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                                        </svg>
                                    @endfor
                                </span>
                            @endif
                        </div>

                        <p class="mt-3 whitespace-pre-line text-sm text-ink">{{ $testimonial->body }}</p>

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-4 border-t border-border-subtle pt-4">
                            <p class="text-xs text-ink-subtle">
                                <span class="sr-only">Customer email, visible only to you: </span>
                                {{ $testimonial->email }}
                            </p>

                            <div class="flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('dashboard.testimonials.approve', $testimonial) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="secondary" size="sm">
                                        {{ $testimonial->approved ? 'Withdraw approval' : 'Approve' }}
                                        <span class="sr-only">for {{ $testimonial->name }}</span>
                                    </x-ui.button>
                                </form>

                                <form method="POST" action="{{ route('dashboard.testimonials.hide', $testimonial) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="secondary" size="sm">
                                        {{ $testimonial->getAttribute('hidden') ? 'Make visible' : 'Hide' }}
                                        <span class="sr-only">for {{ $testimonial->name }}</span>
                                    </x-ui.button>
                                </form>

                                <form method="POST" action="{{ route('dashboard.testimonials.favorite', $testimonial) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="secondary" size="sm">
                                        {{ $testimonial->favorite ? 'Unfavourite' : 'Favourite' }}
                                        <span class="sr-only">for {{ $testimonial->name }}</span>
                                    </x-ui.button>
                                </form>

                                <form method="POST" action="{{ route('dashboard.testimonials.heart', $testimonial) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="secondary" size="sm">
                                        {{ $testimonial->hearted ? 'Remove from Wall' : 'Add to Wall' }}
                                        <span class="sr-only">for {{ $testimonial->name }}</span>
                                    </x-ui.button>
                                </form>

                                <x-ui.button
                                    href="{{ route('dashboard.testimonials.edit', $testimonial) }}"
                                    variant="ghost"
                                    size="sm"
                                >Edit<span class="sr-only"> {{ $testimonial->name }}</span></x-ui.button>

                                <form
                                    method="POST"
                                    action="{{ route('dashboard.testimonials.destroy', $testimonial) }}"
                                    x-data="{ open: false }"
                                    x-on:keydown.escape.window="open = false"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <x-ui.button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        x-on:click="open = true; $nextTick(() => $refs.cancel.focus())"
                                    >
                                        Delete<span class="sr-only"> {{ $testimonial->name }}</span>
                                    </x-ui.button>

                                    <x-ui.modal
                                        title="Delete this testimonial?"
                                        :description="'This removes the testimonial from '.$testimonial->space->name.' for good. It cannot be undone.'"
                                        id="delete-title-{{ $testimonial->id }}"
                                    >
                                        <x-slot:footer>
                                            <x-ui.button
                                                type="button"
                                                variant="secondary"
                                                x-ref="cancel"
                                                x-on:click="open = false"
                                            >Cancel</x-ui.button>
                                            <x-ui.button type="submit" variant="danger">Delete testimonial</x-ui.button>
                                        </x-slot:footer>
                                    </x-ui.modal>
                                </form>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($testimonials->hasPages())
                <div class="pt-2">
                    {{ $testimonials->links() }}
                </div>
            @endif
        @endif
    </div>
</x-layouts.app>
