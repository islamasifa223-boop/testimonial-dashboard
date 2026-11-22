<x-layouts.app title="Edit space">
    <div class="mx-auto max-w-3xl space-y-8">
        <x-dashboard.section-heading
            title="Edit {{ $space->name }}"
            :description="'Changes are reflected on your public page at '.$space->publicPath().'.'"
        />

        <x-dashboard.space-form
            :space="$space"
            :action="route('dashboard.spaces.update', $space)"
            method="PATCH"
        />
    </div>
</x-layouts.app>
