<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', 'Kudosly') }}</title>

    @fonts

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    {{-- The scripts a page needs on top of the shared ones. The chart
         script is pushed by the overview page, so the charting library
         is only sent to the pages that draw a chart. --}}
    @stack('head')
</head>
<body class="h-full">
    <div class="min-h-full">
        <livewire:dashboard.navigation />

        <div class="lg:pl-64">
            <header class="sticky top-0 z-30 border-b border-border-subtle bg-surface/80 backdrop-blur">
                <div class="flex h-16 items-center gap-4 px-4 sm:px-6 lg:px-8">
                    <button
                        type="button"
                        x-on:click="$dispatch('navigation-toggle')"
                        class="rounded-md p-1.5 text-ink-muted hover:bg-surface-muted hover:text-ink focus-ring lg:hidden"
                    >
                        <span class="sr-only">Open navigation</span>
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>

                    <div class="min-w-0 flex-1">
                        <h1 class="truncate text-base font-semibold tracking-tight text-ink">
                            {{ $title ?? 'Overview' }}
                        </h1>
                    </div>

                    <x-dashboard.plan-badge :plan="auth()->user()->plan" />

                    <div x-data="{ open: false }" class="relative">
                        <button
                            type="button"
                            @click="open = !open"
                            @click.outside="open = false"
                            class="flex items-center gap-2 rounded-full focus-ring"
                        >
                            <span class="flex size-8 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">
                                {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                            </span>
                            <span class="sr-only">Open user menu</span>
                        </button>

                        <div
                            x-show="open"
                            x-cloak
                            x-transition.origin.top.right
                            @keydown.escape.window="open = false"
                            class="absolute right-0 mt-2 w-56 origin-top-right rounded-xl bg-surface p-1.5 shadow-lg ring-1 ring-inset ring-border-subtle"
                        >
                            <div class="border-b border-border-subtle px-3 py-2">
                                <p class="truncate text-sm font-medium text-ink">{{ auth()->user()->name }}</p>
                                <p class="truncate text-xs text-ink-subtle">{{ auth()->user()->email }}</p>
                            </div>

                            <a href="{{ route('dashboard.settings') }}" class="mt-1 block rounded-lg px-3 py-2 text-sm text-ink-muted hover:bg-surface-muted hover:text-ink">Settings</a>
                            <a href="{{ route('dashboard.billing') }}" class="block rounded-lg px-3 py-2 text-sm text-ink-muted hover:bg-surface-muted hover:text-ink">Billing</a>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="mt-1 block w-full rounded-lg px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50">Log out</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="px-4 py-8 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    @if (session('status'))
                        <div class="mb-6">
                            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="mb-6">
                            <x-ui.alert variant="error">{{ session('error') }}</x-ui.alert>
                        </div>
                    @endif

                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>
