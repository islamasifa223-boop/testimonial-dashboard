<x-layouts.app title="Overview">
    @push('head')
        @vite('resources/js/chart.js')
    @endpush

    <div class="space-y-8">
        <x-dashboard.upgrade-banner
            :plan="auth()->user()->plan"
            :spaces-used="$spacesUsed"
            :spaces-limit="$spacesLimit"
        />

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-ink">Welcome back, {{ Str::before(auth()->user()->name, ' ') }}</h2>
                <p class="mt-1 text-sm text-ink-muted">Here's how your account is doing so far.</p>
            </div>

            <x-ui.button href="{{ route('dashboard.spaces.index') }}" size="md">View spaces</x-ui.button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-dashboard.stat-card
                label="Total spaces"
                :value="$totalSpaces"
                :hint="'Limit of '.$spacesLimit.' on your plan'"
                icon="M6 6.878V6a2.25 2.25 0 012.25-2.25h7.5A2.25 2.25 0 0118 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 004.5 9v.878m13.5-3A2.25 2.25 0 0119.5 9v.878m0 0a2.246 2.246 0 00-.75-.128H5.25a2.25 2.25 0 00-2.25 2.25V19.5a2.25 2.25 0 002.25 2.25h10.5A2.25 2.25 0 0021 19.5V9a2.25 2.25 0 00-.75-.128"
            />

            <x-dashboard.stat-card
                label="Total testimonials"
                :value="$totalTestimonials"
                :hint="'Collected across '.$totalSpaces.' '.Str::plural('space', $totalSpaces)"
                icon="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
            />

            <x-dashboard.stat-card
                label="Unique customers"
                :value="$uniqueCustomers"
                :hint="'People who left a testimonial'"
                icon="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"
            />

            <x-dashboard.stat-card
                label="Average rating"
                :value="$averageRating === null ? '—' : number_format($averageRating, 1)"
                :hint="$averageRating === null ? 'No ratings yet' : 'Based on '.$ratingCount.' '.Str::plural('rating', $ratingCount)"
                icon="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.562.562 0 00.475-.345L11.48 3.5z"
            />

            <x-dashboard.stat-card
                label="Testimonials this month"
                :value="$thisMonth"
                :hint="'Since '.now()->startOfMonth()->format('j M')"
                icon="M2.25 13.5h3.86a2.25 2.25 0 002.012 1.244h8.756a2.25 2.25 0 002.012-1.244h3.86m-16.5 0a2.25 2.25 0 011.884-2.25l5.72-8.25a2.25 2.25 0 011.882 0l5.72 8.25A2.25 2.25 0 0117.25 11.25m-15 0V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 4.056A12.318 12.318 0 0013.5 2.25h-3a3 3 0 00-3 3v3M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125v-12c0-.621-.504-1.125-1.125-1.125H17.25"
            />
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-ui.card>
                    <x-slot:title>Submissions over time</x-slot:title>
                    <x-slot:description>Daily testimonial submissions across your spaces.</x-slot:description>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-1 rounded-lg bg-surface-muted p-1">
                            @foreach ([7, 30, 90] as $option)
                                <a
                                    href="{{ route('dashboard.overview', ['range' => $option]) }}"
                                    @class([
                                        'rounded-md px-3 py-1.5 text-xs font-medium transition focus-ring',
                                        'bg-surface text-ink shadow-sm' => $trendRange === $option,
                                        'text-ink-muted hover:text-ink' => $trendRange !== $option,
                                    ])
                                    @if ($trendRange === $option) aria-current="page" @endif
                                >
                                    {{ $option }} days
                                </a>
                            @endforeach
                        </div>

                        <p class="text-xs text-ink-subtle">
                            {{ $trendTotal }} {{ Str::plural('submission', $trendTotal) }} in the last {{ $trendRange }} {{ Str::plural('day', $trendRange) }}
                        </p>
                    </div>

                    <div class="mt-4">
                        @if ($trendTotal === 0)
                            <div class="flex h-64 items-center justify-center rounded-lg border border-dashed border-border-subtle">
                                <p class="text-sm text-ink-subtle">No submissions in the last {{ $trendRange }} {{ Str::plural('day', $trendRange) }}</p>
                            </div>
                        @else
                            {{-- The series travels as JSON in a script block rather than in
                                 an attribute. A script block is not parsed as HTML, so the
                                 JSON is read exactly as it was written, and the hex flags
                                 make sure a value can never close the block early.

                                 The payload is built first and emitted in one piece. A
                                 directive with a multi-line array inside it is not
                                 parsed reliably, so the array is assembled here and
                                 the directive only ever sees a single value. --}}
                            @php
                                $chartData = json_encode([
                                    'labels' => array_column($trendPoints, 'label'),
                                    'counts' => array_column($trendPoints, 'count'),
                                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                            @endphp

                            <script type="application/json" data-submission-chart-data>{!! $chartData !!}</script>

                            <div class="h-64">
                                <canvas
                                    data-submission-chart
                                    aria-label="Daily testimonial submissions over the last {{ $trendRange }} days"
                                    role="img"
                                ></canvas>
                            </div>

                            {{-- The table is the same numbers for a reader who cannot see
                                 the chart, and for a screen reader. It is hidden from
                                 sight but not from a reader. --}}
                            <table class="sr-only">
                                <caption>Daily testimonial submissions</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Date</th>
                                        <th scope="col">Submissions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($trendPoints as $point)
                                        <tr>
                                            <th scope="row">{{ $point['date'] }}</th>
                                            <td>{{ $point['count'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </x-ui.card>

                <x-ui.card>
                    <x-slot:title>Recent submissions</x-slot:title>
                    <x-slot:description>The latest testimonials your customers left.</x-slot:description>

                    @if ($recentSubmissions->isEmpty())
                        <x-ui.empty-state
                            icon="inbox"
                            title="No submissions yet"
                            description="When a customer leaves a testimonial it appears here, so you can approve it and reply to it."
                        >
                            <x-ui.button href="{{ route('dashboard.spaces.index') }}">Share a space</x-ui.button>
                        </x-ui.empty-state>
                    @else
                        <ul class="divide-y divide-border-subtle">
                            @foreach ($recentSubmissions as $submission)
                                <li class="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-ink">{{ $submission->name }}</p>
                                        <p class="mt-1 text-xs text-ink-subtle">
                                            {{ $submission->space?->name ?? 'Unavailable space' }}
                                            &middot;
                                            {{ $submission->created_at->format('j M Y, H:i') }}
                                        </p>
                                    </div>

                                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
                                        @if ($submission->approved)
                                            <x-ui.badge variant="success">Approved</x-ui.badge>
                                        @else
                                            <x-ui.badge variant="warning">Pending</x-ui.badge>
                                        @endif

                                        @if ($submission->getAttribute('hidden'))
                                            <x-ui.badge>Hidden</x-ui.badge>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>

                <x-ui.card>
                    <x-slot:title>Recent spaces</x-slot:title>
                    <x-slot:description>Spaces you have created, newest first.</x-slot:description>

                    @if ($recentSpaces->isEmpty())
                        <x-ui.empty-state
                            icon="sparkles"
                            title="You don't have any spaces yet"
                            description="A Space is a branded page where customers can leave you a testimonial. Create one to get started."
                        >
                            <x-ui.button href="{{ route('dashboard.spaces.create') }}">Create your first space</x-ui.button>
                        </x-ui.empty-state>
                    @else
                        <ul class="divide-y divide-border-subtle">
                            @foreach ($recentSpaces as $space)
                                <li class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-ink">{{ $space->name }}</p>
                                        <p class="truncate text-xs text-ink-subtle">{{ $space->publicPath() }}</p>
                                    </div>
                                    <x-ui.badge>{{ $space->theme->label() }}</x-ui.badge>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>
            </div>

            <div class="space-y-6">
                <x-dashboard.plan-usage-card
                    :plan="auth()->user()->plan"
                    :used="$spacesUsed"
                    :limit="$spacesLimit"
                />
            </div>
        </div>
    </div>
</x-layouts.app>
