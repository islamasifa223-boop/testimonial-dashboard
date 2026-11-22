<?php

use App\Models\Space;
use App\Models\User;
use App\Models\Widget;
use App\Services\WidgetEmbedCode;

/*
|--------------------------------------------------------------------------
| Widget embed code tests
|--------------------------------------------------------------------------
|
| These tests cover the two snippets an owner pastes onto their own
| website.
|
| The snippets are produced on the server and handed to the dashboard as
| plain text. They are never rendered as trusted markup, so the owner's
| own title cannot turn into script on the dashboard. That is why the
| title is escaped here and why the public id is the only thing the
| loader script needs.
|
*/

beforeEach(function (): void {
    seedPlans();
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
|
| The helpers are prefixed so they cannot collide with the helpers of
| another test file: a helper defined in one file is not available
| when another file is run on its own, but a name that is already
| taken would be a fatal error when the whole suite runs.
|
*/

function widgetEmbedSpace(?User $user = null): Space
{
    return Space::factory()->for($user ?? User::factory()->create())->create();
}

function widgetEmbedWidget(?Space $space = null, array $attributes = []): Widget
{
    return Widget::factory()->for($space ?? widgetEmbedSpace())->create($attributes);
}

/*
|--------------------------------------------------------------------------
| The public page
|--------------------------------------------------------------------------
*/

test('the page url points at the public widget page', function (): void {
    $widget = widgetEmbedWidget();

    $url = app(WidgetEmbedCode::class)->pageUrl($widget);

    expect($url)->toBe(route('widgets.show', ['publicId' => $widget->public_id]));
});

test('the page url contains the public id of the widget', function (): void {
    $widget = widgetEmbedWidget();

    $url = app(WidgetEmbedCode::class)->pageUrl($widget);

    expect($url)->toContain($widget->public_id)
        ->and($url)->toContain('/widget/');
});

/*
|--------------------------------------------------------------------------
| The loader url
|--------------------------------------------------------------------------
*/

test('the loader url points at the loader script', function (): void {
    $url = app(WidgetEmbedCode::class)->loaderUrl();

    expect($url)->toBe(url('/embed.js'));
});

/*
|--------------------------------------------------------------------------
| The iframe snippet
|--------------------------------------------------------------------------
*/

test('the iframe snippet points at the public page of the widget', function (): void {
    $widget = widgetEmbedWidget();

    $snippet = app(WidgetEmbedCode::class)->iframe($widget);

    expect($snippet)->toContain('src="'.route('widgets.show', ['publicId' => $widget->public_id]).'"');
});

test('the iframe snippet has a fixed height of six hundred pixels', function (): void {
    $widget = widgetEmbedWidget();

    $snippet = app(WidgetEmbedCode::class)->iframe($widget);

    expect($snippet)->toContain('height="600"')
        ->and(WidgetEmbedCode::HEIGHT)->toBe(600);
});

test('the iframe snippet fills the width of the host page', function (): void {
    $widget = widgetEmbedWidget();

    $snippet = app(WidgetEmbedCode::class)->iframe($widget);

    expect($snippet)->toContain('width="100%"');
});

test('the iframe snippet is lazy so it costs nothing below the fold', function (): void {
    $widget = widgetEmbedWidget();

    $snippet = app(WidgetEmbedCode::class)->iframe($widget);

    expect($snippet)->toContain('loading="lazy"');
});

test('the iframe snippet carries an accessible title', function (): void {
    $widget = widgetEmbedWidget();

    $snippet = app(WidgetEmbedCode::class)->iframe($widget);

    expect($snippet)->toContain('title="Testimonials from ')
        ->and($snippet)->toContain($widget->space->title);
});

test('the iframe title uses the public title of the space', function (): void {
    $space = widgetEmbedSpace();
    $space->update(['name' => 'Dashboard Name', 'title' => 'Public Title']);

    $widget = widgetEmbedWidget($space);

    $snippet = app(WidgetEmbedCode::class)->iframe($widget);

    expect($snippet)->toContain('title="Testimonials from Public Title"');
});

test('the iframe title escapes a quote in the title of the space', function (): void {
    $space = widgetEmbedSpace();
    $space->update(['title' => 'Ada\'s "Best" Choice']);

    $widget = widgetEmbedWidget($space);

    $snippet = app(WidgetEmbedCode::class)->iframe($widget);

    expect($snippet)->not->toContain('"Testimonials from Ada\'s "Best" Choice"')
        ->and($snippet)->toContain('&quot;Best&quot;')
        ->and($snippet)->toContain('&#039;');
});

test('the iframe title escapes a script tag in the title of the space', function (): void {
    $space = widgetEmbedSpace();
    $space->update(['title' => '<script>alert(1)</script>']);

    $widget = widgetEmbedWidget($space);

    $snippet = app(WidgetEmbedCode::class)->iframe($widget);

    expect($snippet)->not->toContain('<script>')
        ->and($snippet)->toContain('&lt;script&gt;');
});

test('the iframe snippet of a widget without a space falls back to a generic title', function (): void {
    $widget = widgetEmbedWidget();
    $widget->setRelation('space', null);

    $snippet = app(WidgetEmbedCode::class)->iframe($widget);

    expect($snippet)->toContain('title="Testimonials from Kudosly"');
});

/*
|--------------------------------------------------------------------------
| The script snippet
|--------------------------------------------------------------------------
*/

test('the script snippet loads the loader script', function (): void {
    $widget = widgetEmbedWidget();

    $snippet = app(WidgetEmbedCode::class)->script($widget);

    expect($snippet)->toContain('src="'.url('/embed.js').'"');
});

test('the script snippet carries the public id of the widget', function (): void {
    $widget = widgetEmbedWidget();

    $snippet = app(WidgetEmbedCode::class)->script($widget);

    expect($snippet)->toContain('data-widget="'.$widget->public_id.'"');
});

test('the script snippet is async so it does not block the host page', function (): void {
    $widget = widgetEmbedWidget();

    $snippet = app(WidgetEmbedCode::class)->script($widget);

    expect($snippet)->toContain('async');
});

test('the public id in the script snippet is thirty two alphanumeric characters', function (): void {
    $widget = widgetEmbedWidget();

    $snippet = app(WidgetEmbedCode::class)->script($widget);

    expect($widget->public_id)->toHaveLength(32)
        ->and($widget->public_id)->toMatch('/^[A-Za-z0-9]{32}$/');
});
