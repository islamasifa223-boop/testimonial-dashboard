<x-layouts.app title="Billing">
    <div class="space-y-8">
        <x-dashboard.section-heading
            title="Plan and billing"
            description="Your current plan, what you've used, and what upgrading would give you."
        />

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.card title="Current plan">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-2xl font-semibold tracking-tight text-ink">{{ $currentPlan?->label ?? 'Free' }}</p>
                        <p class="mt-0.5 text-sm text-ink-muted">
                            @if ($currentPlan && $currentPlan->price_monthly_cents > 0)
                                ${{ $currentPlan->priceMonthly() }} per month
                            @else
                                Free forever
                            @endif
                        </p>
                    </div>
                    <x-dashboard.plan-badge :plan="$currentPlan" />
                </div>

                <div class="mt-5 border-t border-border-subtle pt-4">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-ink-muted">Spaces used</span>
                        <span class="font-medium text-ink">{{ $spacesUsed }} / {{ $spacesLimit }}</span>
                    </div>
                    <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-surface-muted">
                        <div class="h-full rounded-full bg-brand-500" style="width: {{ $spacesLimit > 0 ? min(100, (int) round(($spacesUsed / $spacesLimit) * 100)) : 0 }}%"></div>
                    </div>
                </div>

                @if ($currentPlan && ! $currentPlan->isPro())
                    <p class="mt-5 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-inset ring-amber-200">
                        Upgrades are handled by Stripe and become available in a later phase.
                    </p>
                @endif
            </x-ui.card>

            <x-ui.card title="What upgrading gives you">
                <ul class="space-y-2.5 text-sm text-ink-muted">
                    @foreach ($proFeatures as $feature)
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 size-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            <span>{{ $feature }}</span>
                        </li>
                    @endforeach
                </ul>

                <p class="mt-5 text-sm text-ink-muted">
                    Pro includes <span class="font-medium text-ink">25 spaces</span> and up to
                    <span class="font-medium text-ink">1,000 testimonials per space</span>, for
                    <span class="font-medium text-ink">$999 per month</span>.
                </p>
            </x-ui.card>
        </div>

        <div>
            <h3 class="text-base font-semibold tracking-tight text-ink">Compare plans</h3>

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                @foreach ($plans as $plan)
                    <div class="rounded-xl bg-surface p-6 shadow-sm ring-1 ring-inset {{ $plan->isPro() ? 'ring-brand-300' : 'ring-border-subtle' }}">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-lg font-semibold text-ink">{{ $plan->label }}</p>
                            @if ($plan->isPro())
                                <x-ui.badge variant="brand">Recommended</x-ui.badge>
                            @endif
                        </div>

                        <p class="mt-3 text-3xl font-semibold tracking-tight text-ink">
                            ${{ $plan->priceMonthly() }}
                            @if ($plan->price_monthly_cents > 0)
                                <span class="text-sm font-normal text-ink-subtle">/ month</span>
                            @endif
                        </p>

                        <dl class="mt-5 space-y-2 border-t border-border-subtle pt-4 text-sm">
                            <div class="flex items-center justify-between">
                                <dt class="text-ink-muted">Spaces</dt>
                                <dd class="font-medium text-ink">{{ $plan->max_spaces }}</dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-ink-muted">Testimonials per space</dt>
                                <dd class="font-medium text-ink">{{ number_format($plan->max_testimonials_per_space) }}</dd>
                            </div>
                        </dl>

                        <div class="mt-5">
                            @if ($currentPlan && $currentPlan->name === $plan->name)
                                <x-ui.button disabled variant="secondary" class="w-full">Your current plan</x-ui.button>
                            @elseif ($plan->isPro())
                                <x-ui.button disabled variant="secondary" class="w-full" title="Stripe checkout arrives in Phase 5">Upgrade to Pro</x-ui.button>
                            @else
                                <x-ui.button disabled variant="secondary" class="w-full">Downgrade to Free</x-ui.button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.app>
