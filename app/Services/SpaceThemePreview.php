<?php

namespace App\Services;

use App\Enums\SpaceTheme;

/**
 * Describes how each space theme looks.
 *
 * The preview is used by the theme picker in the dashboard and by the
 * public space page, so both show the same appearance.
 */
class SpaceThemePreview
{
    /**
     * The description shown next to the theme.
     */
    public function description(SpaceTheme $theme): string
    {
        return match ($theme) {
            SpaceTheme::MinimalLight => 'A clean white page with soft borders.',
            SpaceTheme::ModernDark => 'A dark page with bright accent colours.',
            SpaceTheme::SoftGradient => 'A colourful gradient with a bold header.',
        };
    }

    /**
     * The classes that render the preview swatch for a theme.
     *
     * @return array{surface: string, text: string, muted: string, accent: string}
     */
    public function swatch(SpaceTheme $theme): array
    {
        return match ($theme) {
            SpaceTheme::MinimalLight => [
                'surface' => 'bg-white',
                'text' => 'text-slate-900',
                'muted' => 'text-slate-500',
                'accent' => 'bg-slate-900',
            ],
            SpaceTheme::ModernDark => [
                'surface' => 'bg-slate-900',
                'text' => 'text-white',
                'muted' => 'text-slate-400',
                'accent' => 'bg-emerald-400',
            ],
            SpaceTheme::SoftGradient => [
                'surface' => 'bg-gradient-to-br from-violet-500 via-fuchsia-500 to-rose-400',
                'text' => 'text-white',
                'muted' => 'text-white/80',
                'accent' => 'bg-white/90',
            ],
        };
    }

    /**
     * The classes that render the public page for a theme.
     *
     * @return array{page: string, heading: string, body: string, muted: string, card: string}
     */
    public function page(SpaceTheme $theme): array
    {
        return match ($theme) {
            SpaceTheme::MinimalLight => [
                'page' => 'bg-white',
                'heading' => 'text-slate-900',
                'body' => 'text-slate-600',
                'muted' => 'text-slate-400',
                'card' => 'border-slate-200',
            ],
            SpaceTheme::ModernDark => [
                'page' => 'bg-slate-900',
                'heading' => 'text-white',
                'body' => 'text-slate-300',
                'muted' => 'text-slate-500',
                'card' => 'border-slate-700',
            ],
            SpaceTheme::SoftGradient => [
                'page' => 'bg-gradient-to-br from-violet-600 via-fuchsia-600 to-rose-500',
                'heading' => 'text-white',
                'body' => 'text-white/90',
                'muted' => 'text-white/70',
                'card' => 'border-white/30',
            ],
        };
    }
}
