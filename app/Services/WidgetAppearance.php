<?php

namespace App\Services;

use App\Models\Widget;

/**
 * Describes how a widget looks.
 *
 * A widget is shown on somebody else's website, so it carries its own
 * appearance rather than following the host page. This turns the
 * settings of a widget into the CSS custom properties the view writes
 * out, so the view holds no conditionals and no colour maths.
 *
 * The properties are written inline on the widget's root element rather
 * than into the stylesheet, so a change of colour does not need the
 * stylesheet to be rebuilt and served again.
 *
 * The colours are plain CSS rather than design tokens, because the
 * widget ships its own stylesheet and must not depend on the
 * application's design tokens.
 */
class WidgetAppearance
{
    /**
     * The pattern a background colour has to match.
     *
     * A background is only used when it is exactly a hash followed by
     * six hexadecimal digits. Anything else is refused, so a colour
     * that was written to the database in another shape can never
     * reach the stylesheet.
     */
    public const BACKGROUND_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    /**
     * The CSS custom properties the widget is rendered with.
     *
     * The keys are property names without the leading dashes, so the
     * view can write them out as --{name}: {value}.
     *
     * @return array<string, string>
     */
    public function for(Widget $widget): array
    {
        $dark = $widget->theme->value === 'dark';

        $properties = $dark
            ? [
                'klw-card' => '#111827',
                'klw-heading' => '#f9fafb',
                'klw-body-ink' => '#d1d5db',
                'klw-muted' => '#9ca3af',
                'klw-border' => '#1f2937',
                'klw-star' => '#f59e0b',
            ]
            : [
                'klw-card' => '#ffffff',
                'klw-heading' => '#111827',
                'klw-body-ink' => '#374151',
                'klw-muted' => '#6b7280',
                'klw-border' => '#e5e7eb',
                'klw-star' => '#f59e0b',
            ];

        // The background is the owner's own colour, so it is checked
        // here rather than trusted. The stored value is only as
        // trustworthy as the row it was read from, and a colour that
        // does not match the expected shape is refused in favour of
        // the surface colour of the appearance.
        $properties['klw-surface'] = $this->surfaceFor($widget, $dark);

        return $properties;
    }

    /**
     * Whether a background colour may be stored and rendered.
     *
     * An empty background is valid. It means the widget uses the
     * default surface of its theme, so an owner who does not want to
     * pick a colour does not have to.
     *
     * A background that is given has to be exactly a hash followed by
     * six hexadecimal digits. This is the single place that decides
     * what a background may look like, so the builder validation and
     * the renderer agree on the answer and the rule is not written
     * out twice.
     */
    public function isValidBackground(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return preg_match(self::BACKGROUND_PATTERN, $value) === 1;
    }

    /**
     * The background colour the widget is rendered with.
     *
     * The owner's colour is only used when it is exactly a hash
     * followed by six hexadecimal digits. A widget that was never given
     * a colour, and a widget whose stored colour is of another shape,
     * both fall back to the surface colour of the appearance.
     */
    protected function surfaceFor(Widget $widget, bool $dark): string
    {
        $default = $dark ? '#0b1120' : '#ffffff';

        $background = $widget->background;

        if (! $this->isValidBackground($background)) {
            return $default;
        }

        return $background === null || $background === ''
            ? $default
            : $background;
    }
}
