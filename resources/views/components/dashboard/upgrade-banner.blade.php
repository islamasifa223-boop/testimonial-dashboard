@props(['plan', 'spacesUsed', 'spacesLimit'])

@if ($plan && ! $plan->isPro() && $spacesUsed >= $spacesLimit)
    <div {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-4 rounded-xl bg-gradient-to-r from-brand-600 to-brand-700 px-6 py-5']) }}>
        <div class="text-white">
            <p class="text-sm font-semibold">You've used all {{ $spacesLimit }} spaces on the {{ $plan->label }} plan</p>
            <p class="mt-0.5 text-sm text-brand-100">Upgrade to Pro for {{ $plan->max_spaces }} spaces and up to 1,000 testimonials per space.</p>
        </div>

        <x-ui.button href="{{ route('dashboard.billing') }}" variant="secondary" size="md" class="bg-white text-brand-700 hover:bg-brand-50">
            View plans
        </x-ui.button>
    </div>
@endif
