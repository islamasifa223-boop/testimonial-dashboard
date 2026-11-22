<x-layouts.app title="Widgets">
    @vite(['resources/css/widget.css'])

    <div class="space-y-8">
        <x-dashboard.section-heading
            title="Embeddable widgets"
            description="Show your approved testimonials on any website."
        />

        <livewire:dashboard.widget-builder />
    </div>
</x-layouts.app>
