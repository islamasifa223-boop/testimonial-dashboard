<x-layouts.app title="Billing">
    <div class="space-y-8">
        <x-dashboard.section-heading
            title="Plan and billing"
            description="Your current plan, what you've used, and what upgrading would give you."
        />

        @if ($upgradePending)
            <x-ui.alert variant="info">
                We're processing your upgrade. Your Pro access is applied as soon as Stripe confirms the payment, which usually takes a few seconds. Refresh this page in a moment to see it.
            </x-ui.alert>
        @endif

        @if ($subscription)
            <x-ui.card>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-ink">Subscription</p>
                        <p class="mt-0.5 text-sm text-ink-muted">
                            @if ($subscription->pastDue())
                                Your last payment failed. Pro access continues until Stripe cancels the subscription.
                            @elseif ($subscription->onGracePeriod())
                                Your subscription is set to cancel at the end of the current period. Pro access continues until then.
                            @elseif ($subscription->ended())
                                Your subscription has ended.
                            @elseif ($subscription->onTrial())
                                Your subscription is on trial.
                            @else
                                Your subscription is active.
                            @endif
                        </p>
                    </div>

                    @if ($currentPlan?->isPro())
                        <x-ui.button href="{{ route('dashboard.billing.portal') }}" variant="secondary" size="md">
                            Manage billing
                        </x-ui.button>
                    @endif
                </div>
            </x-ui.card>
        @endif

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
                                @if ($proCheckoutAvailable)
                                    <form method="POST" action="{{ route('dashboard.billing.upgrade') }}">
                                        @csrf
                                        <x-ui.button type="submit" variant="primary" class="w-full">Upgrade to Pro</x-ui.button>
                                    </form>
                                @else
                                    <x-ui.button disabled variant="secondary" class="w-full" title="Pro checkout is not available in this environment">Upgrade to Pro</x-ui.button>
                                @endif
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
