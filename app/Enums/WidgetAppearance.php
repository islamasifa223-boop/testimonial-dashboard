<?php

namespace App\Enums;

/**
 * The colour mode a widget is shown in.
 *
 * A widget is shown on somebody else's website, where the page behind it
 * may be light or dark. The widget therefore carries its own colour mode
 * rather than following the host page, so that it stays readable either
 * way. The mode is independent of the layout.
 */
enum WidgetAppearance: string
{
    case Light = 'light';
    case Dark = 'dark';

    public function label(): string
    {
        return match ($this) {
            self::Light => 'Light',
            self::Dark => 'Dark',
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
