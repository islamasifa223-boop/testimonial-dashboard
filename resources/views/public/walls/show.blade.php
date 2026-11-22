@php
    $theme = app(App\Services\SpaceThemePreview::class)->page($space->theme);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $space->title }} · Wall of Love</title>
    <meta name="description" content="{{ $space->subtitle ?? $space->title }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full {{ $theme['page'] }}">
    <main class="mx-auto flex min-h-screen w-full max-w-5xl flex-col px-6 py-16">
        <header class="text-center">
            <h1 class="text-3xl font-semibold tracking-tight {{ $theme['heading'] }}">{{ $space->title }}</h1>

            @if ($space->subtitle)
                <p class="mt-2 text-sm {{ $theme['body'] }}">{{ $space->subtitle }}</p>
            @endif

            <p class="mt-4 text-xs {{ $theme['muted'] }}">
                {{ $testimonials->count() }} {{ Str::plural('testimonial', $testimonials->count()) }} from our customers
            </p>
        </header>

        @if ($testimonials->isEmpty())
            <div class="mx-auto mt-16 w-full max-w-md rounded-2xl border p-10 text-center shadow-sm backdrop-blur {{ $theme['card'] }}">
                <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-white/20">
                    <svg class="size-6 {{ $theme['heading'] }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                    </svg>
                </span>

                <h2 class="mt-4 text-lg font-semibold {{ $theme['heading'] }}">No testimonials yet</h2>

                <p class="mt-2 text-sm {{ $theme['body'] }}">
                    The wall of {{ $space->name }} is waiting for its first kind words.
                </p>
            </div>
        @else
            <ul class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($testimonials as $testimonial)
                    <li class="flex flex-col rounded-2xl border p-6 shadow-sm backdrop-blur {{ $theme['card'] }}">
                        @if ($testimonial->photo_path)
                            <img
                                src="{{ route('walls.photos.show', [$space->slug, $testimonial->id]) }}"
                                alt="{{ $testimonial->name }}"
                                width="80"
                                height="80"
                                loading="lazy"
                                decoding="async"
                                class="size-20 rounded-full object-cover"
                            >
                        @endif

                        <div class="mt-4 flex-1">
                            <p class="text-sm font-semibold {{ $theme['heading'] }}">{{ $testimonial->name }}</p>

                            @if ($testimonial->company_name)
                                <p class="mt-0.5 text-sm {{ $theme['muted'] }}">{{ $testimonial->company_name }}</p>
                            @endif

                            @if ($testimonial->rating)
                                <span class="mt-2 flex items-center gap-0.5" role="img" aria-label="{{ $testimonial->rating }} out of 5 stars">
                                    @for ($star = 1; $star <= 5; $star++)
                                        <svg class="size-4 {{ $star <= $testimonial->rating ? 'text-amber-400' : $theme['muted'] }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.563.563 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                                        </svg>
                                    @endfor
                                </span>
                            @endif

                            <p class="mt-3 whitespace-pre-line text-sm {{ $theme['body'] }}">{{ $testimonial->body }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <p class="mt-12 text-center text-xs {{ $theme['muted'] }}">
            Powered by {{ config('app.name', 'Kudosly') }}
        </p>
    </main>
</body>
</html>
