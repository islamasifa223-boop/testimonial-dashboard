<?php

use App\Enums\WidgetAppearance;
use App\Enums\WidgetLayout;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Widget;

/*
|--------------------------------------------------------------------------
| Widget model tests
|--------------------------------------------------------------------------
|
| These tests cover the widget model, its relation to the space, the
| ownership policy and the visibility rule the widget uses.
|
| The visibility rule is the rule of the public surface without the
| heart: a testimonial shows in a widget when it is approved, not
| hidden and consented. The heart belongs to the Wall of Love and is
| the owner's own selection, so it does not decide whether a
| testimonial may be shown at all.
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

function widgetModelSpace(?User $user = null): Space
{
    return Space::factory()->for($user ?? User::factory()->create())->create();
}

function widgetModelWidget(?Space $space = null, array $attributes = []): Widget
{
    return Widget::factory()->for($space ?? widgetModelSpace())->create($attributes);
}

/*
|--------------------------------------------------------------------------
| The relations
|--------------------------------------------------------------------------
*/

test('a widget belongs to a space', function (): void {
    $space = widgetModelSpace();
    $widget = widgetModelWidget($space);

    expect($widget->space)->toBeInstanceOf(Space::class)
        ->and($widget->space->id)->toBe($space->id);
});

test('a space has many widgets', function (): void {
    $space = widgetModelSpace();

    widgetModelWidget($space);
    widgetModelWidget($space);

    expect($space->widgets)->toHaveCount(2);
});

test('the widgets of a space are only its own', function (): void {
    $space = widgetModelSpace();
    $other = widgetModelSpace();

    $widget = widgetModelWidget($space);

    expect($space->widgets)->toHaveCount(1)
        ->and($space->widgets->first()->id)->toBe($widget->id)
        ->and($other->widgets)->toHaveCount(0);
});

test('a space without widgets has an empty widget collection', function (): void {
    $space = widgetModelSpace();

    expect($space->widgets)->toHaveCount(0);
});

/*
|--------------------------------------------------------------------------
| The public id
|--------------------------------------------------------------------------
*/

test('a widget is given a public id when it is created', function (): void {
    $widget = widgetModelWidget();

    expect($widget->public_id)->toBeString()
        ->and($widget->public_id)->not->toBe('');
});

test('the public id is thirty two characters long', function (): void {
    $widget = widgetModelWidget();

    expect($widget->public_id)->toHaveLength(Widget::PUBLIC_ID_LENGTH)
        ->and(Widget::PUBLIC_ID_LENGTH)->toBe(32);
});

test('two widgets do not share a public id', function (): void {
    $first = widgetModelWidget();
    $second = widgetModelWidget();

    expect($first->public_id)->not->toBe($second->public_id);
});

test('the public id is not the primary key', function (): void {
    $widget = widgetModelWidget();

    expect($widget->public_id)->not->toBe((string) $widget->getKey());
});

test('the embed path is built from the public id', function (): void {
    $widget = widgetModelWidget();

    expect($widget->embedPath())->toBe('/widget/'.$widget->public_id);
});

/*
|--------------------------------------------------------------------------
| The settings
|--------------------------------------------------------------------------
*/

test('a widget has a layout and a colour mode', function (): void {
    $widget = widgetModelWidget();

    expect($widget->layout)->toBe(WidgetLayout::Masonry)
        ->and($widget->theme)->toBe(WidgetAppearance::Light);
});

test('the layout is cast to the layout enum', function (): void {
    $widget = widgetModelWidget(null, ['layout' => WidgetLayout::Carousel]);

    expect($widget->layout)->toBe(WidgetLayout::Carousel)
        ->and($widget->layout->value)->toBe('carousel');
});

test('the colour mode is cast to the appearance enum', function (): void {
    $widget = widgetModelWidget(null, ['theme' => WidgetAppearance::Dark]);

    expect($widget->theme)->toBe(WidgetAppearance::Dark)
        ->and($widget->theme->value)->toBe('dark');
});

test('the animation flag is cast to a boolean', function (): void {
    $widget = widgetModelWidget(null, ['animation' => false]);

    expect($widget->animation)->toBeFalse();
});

test('the limit is cast to an integer', function (): void {
    $widget = widgetModelWidget(null, ['limit' => 12]);

    expect($widget->limit)->toBe(12);
});

test('a widget without a background has no background', function (): void {
    $widget = widgetModelWidget();

    expect($widget->background)->toBeNull()
        ->and($widget->hasBackground())->toBeFalse();
});

test('a widget with a background reports it', function (): void {
    $widget = widgetModelWidget(null, ['background' => '#101828']);

    expect($widget->hasBackground())->toBeTrue()
        ->and($widget->background)->toBe('#101828');
});

/*
|--------------------------------------------------------------------------
| Mass assignment
|--------------------------------------------------------------------------
*/

test('the appearance settings are fillable', function (): void {
    $widget = new Widget([
        'layout' => WidgetLayout::Carousel,
        'theme' => WidgetAppearance::Dark,
        'background' => '#101828',
        'animation' => false,
        'limit' => 10,
    ]);

    expect($widget->layout)->toBe(WidgetLayout::Carousel)
        ->and($widget->theme)->toBe(WidgetAppearance::Dark)
        ->and($widget->background)->toBe('#101828')
        ->and($widget->animation)->toBeFalse()
        ->and($widget->limit)->toBe(10);
});

test('the space is not mass assignable', function (): void {
    $space = widgetModelSpace();

    $widget = new Widget(['space_id' => $space->id]);

    expect($widget->space_id)->toBeNull();
});

test('the public id is not mass assignable', function (): void {
    $widget = new Widget(['public_id' => 'a-known-public-id']);

    expect($widget->public_id)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| The ownership
|--------------------------------------------------------------------------
*/

test('the owned by scope returns the widgets of the account', function (): void {
    $user = User::factory()->create();
    $space = widgetModelSpace($user);
    $widget = widgetModelWidget($space);

    expect(Widget::ownedBy($user)->pluck('id')->all())->toBe([$widget->id]);
});

test('the owned by scope leaves out the widgets of another account', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    widgetModelWidget(widgetModelSpace($other));

    expect(Widget::ownedBy($user)->count())->toBe(0);
});

test('the owned by scope leaves out the widgets of a deleted space', function (): void {
    $user = User::factory()->create();
    $space = widgetModelSpace($user);
    $widget = widgetModelWidget($space);

    $space->delete();

    expect(Widget::ownedBy($user)->count())->toBe(0)
        ->and(Widget::withoutGlobalScopes()->whereKey($widget->id)->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| The policy
|--------------------------------------------------------------------------
*/

test('the owner may view the widget', function (): void {
    $user = User::factory()->create();
    $widget = widgetModelWidget(widgetModelSpace($user));

    expect($user->can('view', $widget))->toBeTrue();
});

test('the owner may update the widget', function (): void {
    $user = User::factory()->create();
    $widget = widgetModelWidget(widgetModelSpace($user));

    expect($user->can('update', $widget))->toBeTrue();
});

test('the owner may delete the widget', function (): void {
    $user = User::factory()->create();
    $widget = widgetModelWidget(widgetModelSpace($user));

    expect($user->can('delete', $widget))->toBeTrue();
});

test('another account may not view the widget', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $widget = widgetModelWidget(widgetModelSpace($owner));

    expect($other->can('view', $widget))->toBeFalse();
});

test('another account may not update the widget', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $widget = widgetModelWidget(widgetModelSpace($owner));

    expect($other->can('update', $widget))->toBeFalse();
});

test('another account may not delete the widget', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $widget = widgetModelWidget(widgetModelSpace($owner));

    expect($other->can('delete', $widget))->toBeFalse();
});

test('a widget of a deleted space belongs to nobody', function (): void {
    $owner = User::factory()->create();
    $space = widgetModelSpace($owner);
    $widget = widgetModelWidget($space);

    $space->delete();

    expect($owner->can('view', $widget->fresh()))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| The factory
|--------------------------------------------------------------------------
*/

test('the factory makes a widget of a space', function (): void {
    $widget = Widget::factory()->create();

    expect($widget)->toBeInstanceOf(Widget::class)
        ->and($widget->space)->toBeInstanceOf(Space::class);
});

test('the factory gives the widget a public id', function (): void {
    $widget = Widget::factory()->create();

    expect($widget->public_id)->toHaveLength(32);
});

test('the factory state makes a carousel widget', function (): void {
    $widget = Widget::factory()->carousel()->create();

    expect($widget->layout)->toBe(WidgetLayout::Carousel);
});

test('the factory state makes a dark widget', function (): void {
    $widget = Widget::factory()->dark()->create();

    expect($widget->theme)->toBe(WidgetAppearance::Dark);
});

/*
|--------------------------------------------------------------------------
| The testimonials a widget may show
|--------------------------------------------------------------------------
*/

test('the visible publicly scope returns an approved consented testimonial', function (): void {
    $space = widgetModelSpace();

    Testimonial::factory()->for($space)->approved()->consented()->create();

    expect(Testimonial::query()->visiblePublicly()->count())->toBe(1);
});

test('the visible publicly scope leaves out an unapproved testimonial', function (): void {
    $space = widgetModelSpace();

    Testimonial::factory()->for($space)->consented()->create();

    expect(Testimonial::query()->visiblePublicly()->count())->toBe(0);
});

test('the visible publicly scope leaves out an unconsented testimonial', function (): void {
    $space = widgetModelSpace();

    Testimonial::factory()->for($space)->approved()->create();

    expect(Testimonial::query()->visiblePublicly()->count())->toBe(0);
});

test('the visible publicly scope leaves out a hidden testimonial', function (): void {
    $space = widgetModelSpace();

    Testimonial::factory()->for($space)->approved()->consented()->hidden()->create();

    expect(Testimonial::query()->visiblePublicly()->count())->toBe(0);
});

test('the visible publicly scope does not require a heart', function (): void {
    $space = widgetModelSpace();

    Testimonial::factory()->for($space)->approved()->consented()->create();

    expect(Testimonial::query()->visiblePublicly()->count())->toBe(1);
});

test('the visible publicly scope returns nothing when no testimonial qualifies', function (): void {
    $space = widgetModelSpace(User::factory()->create());

    Testimonial::factory()->for($space)->approved()->consented()->hidden()->create([
        'name' => 'Hidden One',
    ]);
    Testimonial::factory()->for($space)->approved()->create([
        'name' => 'Not Consented',
    ]);
    Testimonial::factory()->for($space)->consented()->create([
        'name' => 'Not Approved',
    ]);

    expect(Testimonial::query()->visiblePublicly()->count())->toBe(0);
});

test('the visible publicly scope and the model method agree on every combination', function (): void {
    $space = widgetModelSpace(User::factory()->create());

    foreach ([true, false] as $approved) {
        foreach ([true, false] as $hidden) {
            foreach ([true, false] as $consented) {
                $testimonial = Testimonial::factory()->for($space)->create([
                    'approved' => $approved,
                    'hidden' => $hidden,
                    'consented' => $consented,
                ]);

                $found = Testimonial::query()
                    ->whereKey($testimonial->id)
                    ->visiblePublicly()
                    ->exists();

                expect($found)->toBe($testimonial->isVisiblePublicly());
            }
        }
    }
});

test('the model method reports an approved consented testimonial as visible', function (): void {
    $testimonial = Testimonial::factory()->approved()->consented()->create();

    expect($testimonial->isVisiblePublicly())->toBeTrue();
});

test('the model method reports an unapproved testimonial as not visible', function (): void {
    $testimonial = Testimonial::factory()->consented()->create();

    expect($testimonial->isVisiblePublicly())->toBeFalse();
});

test('the model method reports an unconsented testimonial as not visible', function (): void {
    $testimonial = Testimonial::factory()->approved()->create();

    expect($testimonial->isVisiblePublicly())->toBeFalse();
});

test('the model method reports a hidden testimonial as not visible', function (): void {
    $testimonial = Testimonial::factory()->approved()->consented()->hidden()->create();

    expect($testimonial->isVisiblePublicly())->toBeFalse();
});

test('the model method does not require a heart', function (): void {
    $testimonial = Testimonial::factory()->approved()->consented()->create();

    expect($testimonial->isVisiblePublicly())->toBeTrue();
});
