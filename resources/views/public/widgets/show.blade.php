@php
    $style = collect($appearance)
        ->map(fn (string $value, string $name): string => '--'.$name.': '.$value.';')
        ->implode(' ');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $space->title }}</title>
    <meta name="robots" content="noindex">

    @vite(['resources/css/widget.css', 'resources/js/widget.js'])
</head>
<body class="klw-body" style="{{ $style }}">
    <div
        class="klw-widget klw-widget--{{ $widget->layout->value }}"
        data-klw-theme="{{ $widget->theme->value }}"
        data-klw-animation="{{ $widget->animation ? 'true' : 'false' }}"
    >
        @if ($testimonials->isEmpty())
            <p class="klw-empty">No testimonials yet</p>
        @else
            <ul
                class="klw-list"
                @if ($widget->layout === App\Enums\WidgetLayout::Carousel)
                    data-klw-track
                @endif
            >
                @foreach ($testimonials as $testimonial)
                    @include('public.widgets.partials.testimonial', [
                        'testimonial' => $testimonial,
                        'widget' => $widget,
                    ])
                @endforeach
            </ul>
        @endif
    </div>
</body>
</html>
