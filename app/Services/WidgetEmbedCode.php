<?php

namespace App\Services;

use App\Models\Widget;

/**
 * Builds the snippets an owner pastes onto their own website.
 *
 * The snippets are produced on the server and handed to the dashboard
 * as plain text. They are never rendered as trusted markup, so the
 * owner's own settings cannot turn into script on the dashboard.
 *
 * The height is fixed. A widget that sized itself would need the
 * embedded page and the host page to talk to each other across
 * origins, which means trusting a message from a page that is not
 * ours. A fixed height keeps the embed a plain frame.
 */
class WidgetEmbedCode
{
    /**
     * The height of an embedded widget, in pixels.
     */
    public const HEIGHT = 600;

    /**
     * The public page of the widget.
     *
     * The url is built from the named route rather than from the path
     * on the model, so the url follows the route if the route ever
     * moves.
     */
    public function pageUrl(Widget $widget): string
    {
        return route('widgets.show', ['publicId' => $widget->public_id]);
    }

    /**
     * The script tag that loads the embed loader.
     */
    public function loaderUrl(): string
    {
        return url('/embed.js');
    }

    /**
     * The iframe snippet.
     *
     * The height is fixed, the width fills the host page, and the frame
     * is lazy so a widget below the fold does not cost anything until
     * it is scrolled to.
     */
    public function iframe(Widget $widget): string
    {
        $url = $this->pageUrl($widget);

        $title = 'Testimonials from '.($widget->space?->title ?? 'Kudosly');

        return '<iframe src="'.$url.'" width="100%" height="'.self::HEIGHT.'" frameborder="0" loading="lazy" title="'.$this->escape($title).'" style="border:0;display:block;max-width:100%;"></iframe>';
    }

    /**
     * The JavaScript snippet.
     *
     * The loader script replaces the tag with the same iframe, so the
     * two snippets produce the same result on the host page.
     */
    public function script(Widget $widget): string
    {
        $url = $this->loaderUrl();

        return '<script src="'.$url.'" data-widget="'.$this->escape($widget->public_id).'" async></script>';
    }

    /**
     * Escape a value that goes into an attribute or a snippet.
     *
     * The values that reach here are the owner's own title and the
     * public id of the widget. The public id is generated on the
     * server and is always alphanumeric, but the title is free text
     * and is escaped here so a title with a quote in it cannot break
     * out of the attribute.
     */
    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
