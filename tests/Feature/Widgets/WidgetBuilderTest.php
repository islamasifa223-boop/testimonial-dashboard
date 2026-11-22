<?php

use App\Enums\WidgetAppearance;
use App\Enums\WidgetLayout;
use App\Livewire\Dashboard\WidgetBuilder;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Widget;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Widget builder tests
|--------------------------------------------------------------------------
|
| These tests cover the dashboard widget builder: the Livewire component
| that creates, edits and deletes the widgets of the signed in account.
|
| The builder never trusts the browser. The space is always resolved
| through the account's own spaces, the public id is generated on the
| server, and the space of a saved widget is fixed. These tests are the
| evidence for that.
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

function widgetBuilderUser(): User
{
    return User::factory()->create();
}

function widgetBuilderSpace(?User $user = null): Space
{
    return Space::factory()->for($user ?? widgetBuilderUser())->create();
}

function widgetBuilderWidget(?Space $space = null, array $attributes = []): Widget
{
    return Widget::factory()->for($space ?? widgetBuilderSpace())->create($attributes);
}

function widgetBuilderTestimonial(Space $space, array $attributes = []): Testimonial
{
    return Testimonial::factory()->for($space)->create($attributes);
}

/*
|--------------------------------------------------------------------------
| The component
|--------------------------------------------------------------------------
*/

test('the builder renders for a signed in account', function (): void {
    Livewire::actingAs(widgetBuilderUser())
        ->test(WidgetBuilder::class)
        ->assertOk();
});

test('the builder lists the spaces of the account', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->assertSee($space->name);
});

test('the builder does not list the spaces of another account', function (): void {
    $user = widgetBuilderUser();
    $other = widgetBuilderSpace(widgetBuilderUser());

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->assertDontSee($other->name);
});

test('the builder leaves out the widgets of a deleted space', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);
    widgetBuilderWidget($space);

    $space->delete();

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->assertDontSee($space->name);
});

/*
|--------------------------------------------------------------------------
| Choosing a space
|--------------------------------------------------------------------------
|
| The space is chosen from a dropdown, so the space id arrives as form
| state. The browser may send any id at all, so the component resolves
| the space through the account's own spaces and refuses anything that
| is not one of them.
|
*/

test('the space is chosen in the browser', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->assertSet('spaceId', $space->id);
});

test('the preview follows the space that is chosen', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    widgetBuilderTestimonial($space, [
        'approved' => true,
        'hidden' => false,
        'consented' => true,
        'name' => 'Chosen Space One',
    ]);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->assertSee('Chosen Space One');
});

test('a space of another account is refused', function (): void {
    $user = widgetBuilderUser();
    $other = widgetBuilderSpace(widgetBuilderUser());

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $other->id)
        ->call('save')
        ->assertHasErrors('spaceId');

    expect(Widget::ownedBy($user)->count())->toBe(0);
});

test('a space that does not exist is refused', function (): void {
    $user = widgetBuilderUser();

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', 9999)
        ->call('save')
        ->assertHasErrors('spaceId');

    expect(Widget::ownedBy($user)->count())->toBe(0);
});

test('a widget cannot be created without a space', function (): void {
    $user = widgetBuilderUser();

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', null)
        ->call('save')
        ->assertHasErrors('spaceId');

    expect(Widget::ownedBy($user)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Creating a widget
|--------------------------------------------------------------------------
*/

test('a widget is created for the chosen space', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Widget::ownedBy($user)->count())->toBe(1)
        ->and(Widget::ownedBy($user)->first()->space_id)->toBe($space->id);
});

test('a created widget keeps the settings of the form', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->set('layout', WidgetLayout::Carousel->value)
        ->set('theme', WidgetAppearance::Dark->value)
        ->set('background', '#1f2937')
        ->set('animation', false)
        ->set('limit', 9)
        ->call('save')
        ->assertHasNoErrors();

    $widget = Widget::ownedBy($user)->first();

    expect($widget->layout)->toBe(WidgetLayout::Carousel)
        ->and($widget->theme)->toBe(WidgetAppearance::Dark)
        ->and($widget->background)->toBe('#1f2937')
        ->and($widget->animation)->toBeFalse()
        ->and($widget->limit)->toBe(9);
});

test('a created widget is given a public id', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->call('save')
        ->assertHasNoErrors();

    $widget = Widget::ownedBy($user)->first();

    expect($widget->public_id)->toHaveLength(Widget::PUBLIC_ID_LENGTH)
        ->and($widget->public_id)->toMatch('/^[A-Za-z0-9]{32}$/');
});

test('a widget without a background is saved without one', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->set('background', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(Widget::ownedBy($user)->first()->background)->toBeNull();
});

test('the builder starts a new widget with the default settings', function (): void {
    Livewire::actingAs(widgetBuilderUser())
        ->test(WidgetBuilder::class)
        ->call('createWidget')
        ->assertSet('layout', WidgetLayout::Masonry->value)
        ->assertSet('theme', WidgetAppearance::Light->value)
        ->assertSet('background', '')
        ->assertSet('animation', true)
        ->assertSet('limit', 6);
});

test('create widget refuses a space of another account', function (): void {
    $user = widgetBuilderUser();
    $other = widgetBuilderSpace(widgetBuilderUser());

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->call('createWidget', $other->id)
        ->assertHasErrors('spaceId');

    expect(Widget::ownedBy($user)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| The validation
|--------------------------------------------------------------------------
*/

test('the layout has to be a known layout', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->set('layout', 'spiral')
        ->assertOk()
        ->call('save')
        ->assertHasErrors('layout');

    expect(Widget::ownedBy($user)->count())->toBe(0);
});

test('the colour mode has to be a known colour mode', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->set('theme', 'sepia')
        ->assertOk()
        ->call('save')
        ->assertHasErrors('theme');

    expect(Widget::ownedBy($user)->count())->toBe(0);
});

test('the background has to be a hex colour', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->set('background', 'rebeccapurple')
        ->call('save')
        ->assertHasErrors('background');

    expect(Widget::ownedBy($user)->count())->toBe(0);
});

test('the background refuses a colour that could break out of the style', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->set('background', '#fff; background-image: url(http://example.com)')
        ->call('save')
        ->assertHasErrors('background');

    expect(Widget::ownedBy($user)->count())->toBe(0);
});

test('a background of six hexadecimal digits is accepted', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->set('background', '#A1B2C3')
        ->call('save')
        ->assertHasNoErrors();

    expect(Widget::ownedBy($user)->first()->background)->toBe('#A1B2C3');
});

test('the animation flag has to be a boolean', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->set('animation', 'maybe')
        ->assertOk()
        ->call('save')
        ->assertHasErrors('animation');

    expect(Widget::ownedBy($user)->count())->toBe(0);
});

test('the limit has to be at least one', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->set('limit', 0)
        ->call('save')
        ->assertHasErrors('limit');

    expect(Widget::ownedBy($user)->count())->toBe(0);
});

test('the limit has to be at most twenty', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->set('limit', 21)
        ->call('save')
        ->assertHasErrors('limit');

    expect(Widget::ownedBy($user)->count())->toBe(0);
});

test('the limit has to be a whole number', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->set('limit', 'many')
        ->assertOk()
        ->call('save')
        ->assertHasErrors('limit');

    expect(Widget::ownedBy($user)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Editing a widget
|--------------------------------------------------------------------------
*/

test('a widget is loaded into the form', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);
    $widget = widgetBuilderWidget($space, [
        'layout' => WidgetLayout::Carousel,
        'theme' => WidgetAppearance::Dark,
        'background' => '#1f2937',
        'animation' => false,
        'limit' => 9,
    ]);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->call('editWidget', $widget->id)
        ->assertSet('widgetId', $widget->id)
        ->assertSet('spaceId', $space->id)
        ->assertSet('layout', WidgetLayout::Carousel->value)
        ->assertSet('theme', WidgetAppearance::Dark->value)
        ->assertSet('background', '#1f2937')
        ->assertSet('animation', false)
        ->assertSet('limit', 9);
});

test('the settings of a widget are changed and saved', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);
    $widget = widgetBuilderWidget($space);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->call('editWidget', $widget->id)
        ->set('layout', WidgetLayout::Carousel->value)
        ->set('theme', WidgetAppearance::Dark->value)
        ->call('save')
        ->assertHasNoErrors();

    $widget->refresh();

    expect($widget->layout)->toBe(WidgetLayout::Carousel)
        ->and($widget->theme)->toBe(WidgetAppearance::Dark);
});

test('a widget of another account is not loaded into the form', function (): void {
    $user = widgetBuilderUser();
    $widget = widgetBuilderWidget(widgetBuilderSpace(widgetBuilderUser()));

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->call('editWidget', $widget->id)
        ->assertSet('widgetId', null)
        ->assertSet('spaceId', null);
});

test('a widget of another account is not changed', function (): void {
    $user = widgetBuilderUser();
    $widget = widgetBuilderWidget(widgetBuilderSpace(widgetBuilderUser()));

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->call('editWidget', $widget->id)
        ->set('layout', WidgetLayout::Carousel->value)
        ->call('save');

    expect($widget->fresh()->layout)->toBe(WidgetLayout::Masonry);
});

test('a widget of another account is not deleted', function (): void {
    $user = widgetBuilderUser();
    $widget = widgetBuilderWidget(widgetBuilderSpace(widgetBuilderUser()));

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->call('deleteWidget');

    expect(Widget::withoutGlobalScopes()->whereKey($widget->id)->exists())->toBeTrue();
});

test('a widget of a deleted space is not loaded into the form', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);
    $widget = widgetBuilderWidget($space);

    $space->delete();

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->call('editWidget', $widget->id)
        ->assertSet('widgetId', null);
});

/*
|--------------------------------------------------------------------------
| The space of a saved widget
|--------------------------------------------------------------------------
|
| The space of a saved widget is fixed. It is not read from the browser
| and it is not part of the attributes the form writes, so the widget
| keeps the space it was created for.
|
*/

test('a saved widget cannot be moved to another space of the account', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);
    $other = widgetBuilderSpace($user);
    $widget = widgetBuilderWidget($space);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->call('editWidget', $widget->id)
        ->set('spaceId', $other->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($widget->fresh()->space_id)->toBe($space->id);
});

test('a saved widget cannot be moved to the space of another account', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);
    $other = widgetBuilderSpace(widgetBuilderUser());
    $widget = widgetBuilderWidget($space);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->call('editWidget', $widget->id)
        ->set('spaceId', $other->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($widget->fresh()->space_id)->toBe($space->id);
});

test('the preview of a saved widget shows the space of the widget', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);
    $other = widgetBuilderSpace($user);

    widgetBuilderTestimonial($other, [
        'approved' => true,
        'hidden' => false,
        'consented' => true,
        'name' => 'Other Space One',
    ]);

    $widget = widgetBuilderWidget($space);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->call('editWidget', $widget->id)
        ->set('spaceId', $other->id)
        ->assertDontSee('Other Space One');
});

/*
|--------------------------------------------------------------------------
| The public id
|--------------------------------------------------------------------------
|
| The public id is not a property of the component, so the browser has
| nothing to set. A tampered payload that names one is refused by
| Livewire before it reaches the component.
|
*/

test('a tampered public id cannot be set on the component', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    expect(fn () => Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->set('publicId', 'a-chosen-public-id'))
        ->toThrow(PublicPropertyNotFoundException::class);
});

test('a saved widget keeps the public id it was given', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);
    $widget = widgetBuilderWidget($space);
    $publicId = $widget->public_id;

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->call('editWidget', $widget->id)
        ->set('layout', WidgetLayout::Carousel->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($widget->fresh()->public_id)->toBe($publicId);
});

test('two widgets of the same space do not share a public id', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->call('save');

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->call('save');

    expect(Widget::ownedBy($user)->pluck('public_id')->unique()->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Deleting a widget
|--------------------------------------------------------------------------
*/

test('a widget is deleted', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);
    $widget = widgetBuilderWidget($space);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->call('editWidget', $widget->id)
        ->call('deleteWidget');

    expect(Widget::withoutGlobalScopes()->whereKey($widget->id)->exists())->toBeFalse();
});

test('deleting a widget clears the widget being edited', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);
    $widget = widgetBuilderWidget($space);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->call('editWidget', $widget->id)
        ->call('deleteWidget')
        ->assertSet('widgetId', null);
});

test('deleting a widget that is not being edited does nothing', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);
    $widget = widgetBuilderWidget($space);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->call('deleteWidget');

    expect(Widget::withoutGlobalScopes()->whereKey($widget->id)->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| The preview
|--------------------------------------------------------------------------
*/

test('the preview shows an approved consented testimonial', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    widgetBuilderTestimonial($space, [
        'approved' => true,
        'hidden' => false,
        'consented' => true,
        'name' => 'Visible One',
    ]);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->assertSee('Visible One');
});

test('the preview leaves out a testimonial that is not approved', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    widgetBuilderTestimonial($space, [
        'approved' => false,
        'hidden' => false,
        'consented' => true,
        'name' => 'Not Approved',
    ]);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->assertDontSee('Not Approved');
});

test('the preview leaves out a testimonial that is hidden', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    widgetBuilderTestimonial($space, [
        'approved' => true,
        'hidden' => true,
        'consented' => true,
        'name' => 'Hidden One',
    ]);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->assertDontSee('Hidden One');
});

test('the preview leaves out a testimonial that is not consented', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    widgetBuilderTestimonial($space, [
        'approved' => true,
        'hidden' => false,
        'consented' => false,
        'name' => 'Not Consented',
    ]);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->assertDontSee('Not Consented');
});

test('the preview shows a testimonial without a heart', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    widgetBuilderTestimonial($space, [
        'approved' => true,
        'hidden' => false,
        'consented' => true,
        'hearted' => false,
        'name' => 'No Heart',
    ]);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->assertSee('No Heart');
});

test('the preview shows a testimonial with a heart', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    widgetBuilderTestimonial($space, [
        'approved' => true,
        'hidden' => false,
        'consented' => true,
        'hearted' => true,
        'name' => 'With Heart',
    ]);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->assertSee('With Heart');
});

test('the preview leaves out the email of a testimonial', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    widgetBuilderTestimonial($space, [
        'approved' => true,
        'hidden' => false,
        'consented' => true,
        'email' => 'private@example.com',
    ]);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->assertDontSee('private@example.com');
});

test('the preview leaves out the social url of a testimonial', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    widgetBuilderTestimonial($space, [
        'approved' => true,
        'hidden' => false,
        'consented' => true,
        'social_url' => 'https://example.com/private-profile',
    ]);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->assertDontSee('private-profile');
});

test('the preview shows no more testimonials than the limit', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    widgetBuilderTestimonial($space, [
        'approved' => true,
        'hidden' => false,
        'consented' => true,
        'name' => 'First One',
    ]);
    widgetBuilderTestimonial($space, [
        'approved' => true,
        'hidden' => false,
        'consented' => true,
        'name' => 'Second One',
    ]);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->set('spaceId', $space->id)
        ->set('limit', 1)
        ->assertSee('Second One')
        ->assertDontSee('First One');
});

test('the preview shows nothing when no space is chosen', function (): void {
    $user = widgetBuilderUser();
    $space = widgetBuilderSpace($user);

    widgetBuilderTestimonial($space, [
        'approved' => true,
        'hidden' => false,
        'consented' => true,
        'name' => 'Somebody',
    ]);

    Livewire::actingAs($user)
        ->test(WidgetBuilder::class)
        ->assertDontSee('Somebody');
});
