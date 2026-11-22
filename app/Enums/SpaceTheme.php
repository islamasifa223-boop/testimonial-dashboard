<?php

namespace App\Enums;

enum SpaceTheme: string
{
    case MinimalLight = 'minimal-light';
    case ModernDark = 'modern-dark';
    case SoftGradient = 'soft-gradient';

    public function label(): string
    {
        return match ($this) {
            self::MinimalLight => 'Minimal Light',
            self::ModernDark => 'Modern Dark',
            self::SoftGradient => 'Soft Gradient',
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
