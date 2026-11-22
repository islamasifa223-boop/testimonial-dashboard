<?php

namespace App\Enums;

/**
 * The layout a widget shows its testimonials in.
 *
 * The masonry layout stacks the testimonials in columns of their own
 * height. The carousel layout shows them side by side in a row that
 * scrolls. The two are independent of the colour mode of the widget.
 */
enum WidgetLayout: string
{
    case Masonry = 'masonry';
    case Carousel = 'carousel';

    public function label(): string
    {
        return match ($this) {
            self::Masonry => 'Masonry',
            self::Carousel => 'Carousel',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
