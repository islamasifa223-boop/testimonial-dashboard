
<div class="space-y-8">
    @php
        $spaces = $this->spaces();
        $widgets = $this->widgets();
        $selectedSpace = $this->space();
        $previewTestimonials = $this->previewTestimonials();
        $previewWidget = $this->previewWidget();
        $previewAppearance = $this->previewAppearance();
        $embedCode = $this->widget()
            ? app(\App\Services\WidgetEmbedCode::class)
            : null;
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-ink">
                Widget Builder
            </h2>
            <p class="mt-1 text-sm text-ink-muted">
                Create an embeddable testimonial widget for your website.
            </p>
        </div>

        <x-ui.button wire:click="createWidget">
            New widget
        </x-ui.button>
    </div>

    @if ($spaces->isEmpty())
        <x-ui.card>
            <h3 class="text-sm font-semibold text-ink">
                Create a space first
            </h3>
            <p class="mt-2 text-sm text-ink-muted">
                You need at least one space before you can create a widget.
            </p>

            <div class="mt-4">
                <x-ui.button href="{{ route('dashboard.spaces.create') }}">
                    Create a space
                </x-ui.button>
            </div>
        </x-ui.card>
    @else
        <div class="grid gap-8 lg:grid-cols-2">
            <section class="space-y-6">
                <x-ui.card>
                    <h3 class="text-sm font-semibold text-ink">
                        Widget settings
                    </h3>

                    <div class="mt-6 space-y-5">
                        @if (! $this->editingExistingWidget())
                            <div>
                                <x-ui.label for="widget-space">
                                    Space
                                </x-ui.label>

                                <select
                                    id="widget-space"
                                    wire:model.live="spaceId"
                                    class="mt-1.5 block w-full rounded-lg border-0 bg-surface px-3 py-2 text-sm text-ink shadow-sm ring-1 ring-inset ring-border-subtle focus:ring-2 focus:ring-inset focus:ring-brand-500"
                                >
                                    <option value="">Choose a space</option>
                                    @foreach ($spaces as $space)
                                        <option value="{{ $space->id }}">
                                            {{ $space->name }}
                                        </option>
                                    @endforeach
                                </select>

                                @error('spaceId')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @else
                            <div>
                                <span class="block text-sm font-medium text-ink">
                                    Space
                                </span>
                                <p class="mt-1 text-sm text-ink">
                                    {{ $selectedSpace?->name ?? 'Unavailable' }}
                                </p>
                            </div>
                        @endif

                        <div>
                            <x-ui.label for="widget-layout">
                                Layout
                            </x-ui.label>

                            <select
                                id="widget-layout"
                                wire:model.live="layout"
                                class="mt-1.5 block w-full rounded-lg border-0 bg-surface px-3 py-2 text-sm text-ink shadow-sm ring-1 ring-inset ring-border-subtle focus:ring-2 focus:ring-inset focus:ring-brand-500"
                            >
                                <option value="masonry">Masonry</option>
                                <option value="carousel">Carousel</option>
                            </select>

                            @error('layout')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <x-ui.label for="widget-theme">
                                Appearance
                            </x-ui.label>

                            <select
                                id="widget-theme"
                                wire:model.live="theme"
                                class="mt-1.5 block w-full rounded-lg border-0 bg-surface px-3 py-2 text-sm text-ink shadow-sm ring-1 ring-inset ring-border-subtle focus:ring-2 focus:ring-inset focus:ring-brand-500"
                            >
                                <option value="light">Light</option>
                                <option value="dark">Dark</option>
                            </select>

                            @error('theme')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <x-ui.label for="widget-background">
                                Background colour
                            </x-ui.label>

                            <input
                                id="widget-background"
                                type="text"
                                wire:model.live.debounce.300ms="background"
                                placeholder="#ffffff"
                                maxlength="7"
                                class="mt-1.5 block w-full rounded-lg border-0 bg-surface px-3 py-2 text-sm text-ink shadow-sm ring-1 ring-inset ring-border-subtle placeholder:text-ink-subtle focus:ring-2 focus:ring-inset focus:ring-brand-500"
                            >

                            <p class="mt-1 text-xs text-ink-subtle">
                                Optional. Use a six-digit hex colour, for example #f3f4f6.
                            </p>

                            @error('background')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <x-ui.label for="widget-limit">
                                Number of testimonials
                            </x-ui.label>

                            <input
                                id="widget-limit"
                                type="number"
                                min="1"
                                max="20"
                                wire:model.live="limit"
                                class="mt-1.5 block w-full rounded-lg border-0 bg-surface px-3 py-2 text-sm text-ink shadow-sm ring-1 ring-inset ring-border-subtle focus:ring-2 focus:ring-inset focus:ring-brand-500"
                            >

                            @error('limit')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <label class="flex items-center gap-3">
                            <input
                                type="checkbox"
                                wire:model.live="animation"
                                class="size-4 rounded border-0 bg-surface text-brand-600 shadow-sm ring-1 ring-inset ring-border-subtle focus:ring-2 focus:ring-inset focus:ring-brand-500"
                            >
                            <span class="text-sm font-medium text-ink">
                                Enable carousel animation
                            </span>
                        </label>

                        @error('animation')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-border-subtle pt-5">
                        <x-ui.button wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save">
                                {{ $this->editingExistingWidget() ? 'Save changes' : 'Create widget' }}
                            </span>
                            <span wire:loading wire:target="save">
                                Saving...
                            </span>
                        </x-ui.button>

                        @if ($this->editingExistingWidget())
                            <x-ui.button variant="danger" wire:click="deleteWidget" wire:confirm="Are you sure you want to delete this widget?">
                                Delete widget
                            </x-ui.button>
                        @endif
                    </div>
                </x-ui.card>

                @if ($this->editingExistingWidget() && $embedCode)
                    @php
                        $savedWidget = $this->widget();
                    @endphp

                    @if ($savedWidget)
                        <x-ui.card>
                            <h3 class="text-sm font-semibold text-ink">
                                Embed your widget
                            </h3>

                            <p class="mt-1 text-sm text-ink-muted">
                                Copy either snippet and add it to your website.
                            </p>

                            <div class="mt-5">
                                <span class="block text-sm font-medium text-ink">
                                    JavaScript embed code
                                </span>

                                <pre class="mt-1.5 overflow-x-auto rounded-lg bg-surface-muted p-3 text-xs text-ink"><code>{{ $embedCode->script($savedWidget) }}</code></pre>

                                <button
                                    type="button"
                                    x-data="{ copied: false }"
                                    @click="navigator.clipboard.writeText(@js($embedCode->script($savedWidget))).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                    class="mt-2 text-sm font-medium text-brand-600 hover:text-brand-700"
                                >
                                    <span x-show="!copied">Copy JavaScript code</span>
                                    <span x-show="copied" x-cloak>Copied!</span>
                                </button>
                            </div>

                            <div class="mt-5">
                                <span class="block text-sm font-medium text-ink">
                                    iframe embed code
                                </span>

                                <pre class="mt-1.5 overflow-x-auto rounded-lg bg-surface-muted p-3 text-xs text-ink"><code>{{ $embedCode->iframe($savedWidget) }}</code></pre>

                                <button
                                    type="button"
                                    x-data="{ copied: false }"
                                    @click="navigator.clipboard.writeText(@js($embedCode->iframe($savedWidget))).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                    class="mt-2 text-sm font-medium text-brand-600 hover:text-brand-700"
                                >
                                    <span x-show="!copied">Copy iframe code</span>
                                    <span x-show="copied" x-cloak>Copied!</span>
                                </button>
                            </div>
                        </x-ui.card>
                    @endif
                @endif
            </section>

            <section class="space-y-6">
                <x-ui.card>
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-ink">
                            Live preview
                        </h3>
                        <x-ui.badge>{{ $previewTestimonials->count() }} shown</x-ui.badge>
                    </div>

                    <div class="mt-5 overflow-hidden rounded-lg border border-border-subtle">
                        @if ($selectedSpace)
                            {{-- The nesting and the attributes are the ones the public widget
                                 page uses, so the preview looks like the widget: the body
                                 carries the surface colour and the widget root carries the
                                 layout, and the custom properties are written with the
                                 leading dashes the stylesheet reads. --}}
                            <div
                                class="klw-body"
                                style="{{ collect($previewAppearance)->map(fn ($value, $key) => '--'.$key.': '.$value.';')->implode(' ') }}"
                            >
                                <div
                                    class="klw-widget {{ $previewWidget->layout->value === 'carousel' ? 'klw-widget--carousel' : 'klw-widget--masonry' }}"
                                    data-klw-theme="{{ $previewWidget->theme->value }}"
                                    data-klw-animation="{{ $previewWidget->animation ? 'true' : 'false' }}"
                                >
                                    @if ($previewTestimonials->isNotEmpty())
                                        <ul
                                            class="klw-list"
                                            @if ($previewWidget->layout->value === 'carousel')
                                                data-klw-track
                                            @endif
                                        >
                                            @foreach ($previewTestimonials as $testimonial)
                                                @include('dashboard.widgets.partials.preview-card', [
                                                    'testimonial' => $testimonial,
                                                ])
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="klw-empty">
                                            No public testimonials are available for this space yet.
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="p-10 text-center text-sm text-ink-subtle">
                                Choose a space to see your widget preview.
                            </div>
                        @endif
                    </div>

                    <p class="mt-3 text-xs text-ink-subtle">
                        Preview includes approved, visible testimonials with consent, whether or not they are hearted.
                    </p>
                </x-ui.card>

                <x-ui.card>
                    <h3 class="text-sm font-semibold text-ink">
                        Your widgets
                    </h3>

                    @if ($widgets->isEmpty())
                        <p class="mt-3 text-sm text-ink-muted">
                            You haven't created any widgets yet.
                        </p>
                    @else
                        <div class="mt-4 divide-y divide-border-subtle">
                            @foreach ($widgets as $widgetItem)
                                <div wire:key="widget-{{ $widgetItem->id }}" class="flex items-center justify-between gap-4 py-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-ink">
                                            {{ $widgetItem->space?->name ?? 'Unavailable space' }}
                                        </p>
                                        <p class="mt-1 text-xs text-ink-subtle">
                                            {{ ucfirst($widgetItem->layout->value) }} · {{ ucfirst($widgetItem->theme->value) }}
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        wire:click="editWidget({{ $widgetItem->id }})"
                                        class="shrink-0 text-sm font-semibold text-brand-600 hover:text-brand-700"
                                    >
                                        Edit
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>
            </section>
        </div>
    @endif
</div>
