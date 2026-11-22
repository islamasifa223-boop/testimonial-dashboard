@php
    $theme = app(App\Services\SpaceThemePreview::class)->page($space->theme);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $space->title }}</title>
    <meta name="description" content="{{ $space->subtitle ?? $space->title }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full {{ $theme['page'] }}">
    <main class="mx-auto flex min-h-screen w-full max-w-2xl flex-col justify-center px-6 py-16">
        <div class="rounded-2xl border p-8 text-center shadow-sm backdrop-blur {{ $theme['card'] }}">
            <h1 class="text-2xl font-semibold tracking-tight {{ $theme['heading'] }}">You're officially awesome! 🎉</h1>

            <p class="mt-2 text-sm {{ $theme['body'] }}">Your kind words just made someone's day.</p>

            <div class="mt-8 text-left">
                <x-ui.alert variant="success">
                    We have received your testimonial. Thank you for taking the time to share it.
                </x-ui.alert>
            </div>
        </div>

        <p class="mt-6 text-center text-xs {{ $theme['muted'] }}">
            Powered by {{ config('app.name', 'Kudosly') }}
        </p>
    </main>
</body>
</html>
