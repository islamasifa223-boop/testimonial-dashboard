@php
    $nav = $this->links();
    $secondary = $this->secondaryLinks();
@endphp

{{-- Desktop sidebar --}}
<div>
<nav class="hidden lg:fixed lg:inset-y-0 lg:flex lg:w-64 lg:flex-col lg:border-r lg:border-border-subtle lg:bg-surface" aria-label="Main">
    <div class="flex h-16 shrink-0 items-center gap-2.5 border-b border-border-subtle px-6">
        <span class="flex size-8 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white">K</span>
        <span class="text-base font-semibold tracking-tight text-ink">Kudosly</span>
    </div>

    <div class="flex flex-1 flex-col gap-1 overflow-y-auto p-4">
        @foreach ($nav as $link)
            <x-dashboard.nav-link
                :href="route($link['route'])"
                :active="$this->isActive($link['route'])"
                :icon="$link['icon']"
            >{{ $link['label'] }}</x-dashboard.nav-link>
        @endforeach

        <div class="my-4 h-px bg-border-subtle"></div>

        @foreach ($secondary as $link)
            <x-dashboard.nav-link
                :href="route($link['route'])"
                :active="$this->isActive($link['route'])"
                :icon="$link['icon']"
            >{{ $link['label'] }}</x-dashboard.nav-link>
        @endforeach
    </div>

    <div class="shrink-0 border-t border-border-subtle p-4">
        <div class="flex items-center gap-3 rounded-lg px-2 py-1.5">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">
                {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-ink">{{ auth()->user()->name }}</p>
                <p class="truncate text-xs text-ink-subtle">{{ auth()->user()->email }}</p>
            </div>
        </div>
    </div>
</nav>

{{-- Mobile drawer --}}
<div
    @if ($mobileOpen) x-cloak @endif
    x-data="{ open: @entangle('mobileOpen') }"
    x-on:navigation-toggle.window="open = true"
    class="lg:hidden"
>
    <div
        x-show="open"
        x-transition.opacity.duration.200ms
        class="fixed inset-0 z-40 bg-ink/40"
        @click="open = false"
        aria-hidden="true"
    ></div>

    <nav
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-surface shadow-xl"
        aria-label="Main"
    >
        <div class="flex h-16 shrink-0 items-center justify-between gap-2.5 border-b border-border-subtle px-6">
            <span class="flex items-center gap-2.5">
                <span class="flex size-8 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white">K</span>
                <span class="text-base font-semibold tracking-tight text-ink">Kudosly</span>
            </span>
            <button type="button" wire:click="closeMobile" class="rounded-md p-1.5 text-ink-muted hover:bg-surface-muted hover:text-ink focus-ring">
                <span class="sr-only">Close navigation</span>
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="flex flex-1 flex-col gap-1 overflow-y-auto p-4">
            @foreach ($nav as $link)
                <x-dashboard.nav-link
                    :href="route($link['route'])"
                    :active="$this->isActive($link['route'])"
                    :icon="$link['icon']"
                    wire:click="closeMobile"
                >{{ $link['label'] }}</x-dashboard.nav-link>
            @endforeach

            <div class="my-4 h-px bg-border-subtle"></div>

            @foreach ($secondary as $link)
                <x-dashboard.nav-link
                    :href="route($link['route'])"
                    :active="$this->isActive($link['route'])"
                    :icon="$link['icon']"
                    wire:click="closeMobile"
                >{{ $link['label'] }}</x-dashboard.nav-link>
            @endforeach
        </div>

        <div class="shrink-0 border-t border-border-subtle p-4">
            <div class="flex items-center gap-3 rounded-lg px-2 py-1.5">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-ink">{{ auth()->user()->name }}</p>
                    <p class="truncate text-xs text-ink-subtle">{{ auth()->user()->email }}</p>
                </div>
            </div>
        </div>
    </nav>
</div>
</div>
