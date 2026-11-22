@props(['plan'])

{{-- Shares the same plan detection logic as plan-usage-card: the Plan::isPro()
     method, which compares the plan name against the PlanName enum. --}}
<x-ui.badge :variant="$plan?->isPro() ? 'brand' : 'neutral'">
    {{ $plan?->label ?? 'Free' }}
</x-ui.badge>
