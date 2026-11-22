<x-layouts.app title="Create space">
    <div class="mx-auto max-w-3xl space-y-8">
        <x-dashboard.section-heading
            title="Create a space"
            description="A space is your branded page for collecting testimonials."
        />

        <x-dashboard.space-form
            :space="null"
            :action="route('dashboard.spaces.store')"
            method="POST"
        />
    </div>
</x-layouts.app>
