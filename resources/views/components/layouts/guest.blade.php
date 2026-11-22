<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', 'Kudosly') }}</title>

    @fonts

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
    <div class="flex min-h-full flex-col justify-center bg-surface-muted px-4 py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="flex items-center justify-center gap-2.5">
                <span class="flex size-9 items-center justify-center rounded-lg bg-brand-600 text-base font-bold text-white">K</span>
                <span class="text-xl font-semibold tracking-tight text-ink">Kudosly</span>
            </div>

            <div class="mt-8 rounded-xl bg-surface px-6 py-8 shadow-sm ring-1 ring-inset ring-border-subtle sm:px-8">
                <h1 class="text-xl font-semibold tracking-tight text-ink">
                    {{ $title ?? 'Welcome' }}
                </h1>

                @if (isset($description))
                    <p class="mt-1.5 text-sm text-ink-muted">{{ $description }}</p>
                @endif

                <div class="mt-6">
                    {{ $slot }}
                </div>
            </div>

            <p class="mt-6 text-center text-xs text-ink-subtle">
                &copy; {{ date('Y') }} {{ config('app.name', 'Kudosly') }}
            </p>
        </div>
    </div>
</body>
</html>
