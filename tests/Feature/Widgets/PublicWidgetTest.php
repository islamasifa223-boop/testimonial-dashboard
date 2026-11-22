
  <?php

  use App\Enums\WidgetAppearance;
  use App\Enums\WidgetLayout;
  use App\Models\Space;
  use App\Models\Testimonial;
  use App\Models\User;
  use App\Models\Widget;

  /*
  |--------------------------------------------------------------------------
  | The public widget
  |--------------------------------------------------------------------------
  |
  | A widget is embedded on somebody else's website, so it is a public
  | surface like the Wall of Love. It shows the testimonials of a space
  | that are approved, not hidden and consented. The heart is not part of
  | that rule: the heart is the owner's own selection for the Wall, and
  | a widget shows what the space allows to be shown publicly.
  |
  | The widget is found by its public id, which is the only secret in the
  | URL. It is generated on the server and is not mass assignable.
  |
  | Nothing private ever reaches the page: no email, no social url, no
  | storage path, no moderation flag.
  |
  */

  beforeEach(function () {
      seedPlans();
  });

  /*
  |--------------------------------------------------------------------------
  | Helpers
  |--------------------------------------------------------------------------
  */

  /**
   * A space with a widget.
   *
   * The user is optional, so a test that only needs a space does not have
   * to think about who owns it. The attributes are optional too, so a
   * test that needs a particular title passes it as the second argument.
   */
  function publicWidgetSpace(?User $user = null, array $attributes = []): Space
  {
      return Space::factory()->for($user ?? User::factory()->create())->create($attributes);
  }

  /**
   * A widget of the given space.
   */
  function publicWidgetWidget(Space $space, array $attributes = []): Widget
  {
      return Widget::factory()->for($space)->create($attributes);
  }

  /**
   * A testimonial that may be shown publicly.
   *
   * All three conditions hold: approved, not hidden and consented. The
   * heart is deliberately left off, because a widget does not need it.
   */
  function publicWidgetTestimonial(Space $space, array $attributes = []): Testimonial
  {
      return Testimonial::factory()
          ->for($space)
          ->approved()
          ->consented()
          ->create($attributes);
  }

  /*
  |--------------------------------------------------------------------------
  | The widget is found
  |--------------------------------------------------------------------------
  */

  test('the widget is shown at its public id', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('klw-widget');
  });

  test('an unknown public id returns a 404', function () {
      $this->get(route('widgets.show', ['no-such-widget']))->assertNotFound();
  });

  test('a widget of a soft deleted space returns a 404', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space);

      $space->delete();

      $this->get(route('widgets.show', [$widget->public_id]))->assertNotFound();
  });

  test('the widget of another space does not show this space', function () {
      $space = publicWidgetSpace();

      $other = publicWidgetSpace();

      $widget = publicWidgetWidget($other);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertDontSee($space->title);
  });

  /*
  |--------------------------------------------------------------------------
  | The testimonials that are shown
  |--------------------------------------------------------------------------
  */

  test('an approved consented testimonial is shown', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space);

      publicWidgetTestimonial($space, ['name' => 'Ada Lovelace', 'body' => 'A fine product.']);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('Ada Lovelace')
          ->assertSee('A fine product.');
  });

  test('a testimonial that is not approved is not shown', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space);

      Testimonial::factory()->for($space)->consented()->create(['name' => 'Hidden Pending']);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertDontSee('Hidden Pending');
  });

  test('a hidden testimonial is not shown', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space);

      Testimonial::factory()->for($space)->approved()->consented()->hidden()
          ->create(['name' => 'Hidden By Owner']);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertDontSee('Hidden By Owner');
  });

  test('a testimonial without consent is not shown', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space);

      Testimonial::factory()->for($space)->approved()->create(['name' => 'No Consent']);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertDontSee('No Consent');
  });

  test('a testimonial without a heart is shown', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space);

      $testimonial = publicWidgetTestimonial($space, ['name' => 'No Heart']);

      expect($testimonial->hearted)->toBeFalse();

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('No Heart');
  });

  test('a hearted testimonial is shown', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space);

      Testimonial::factory()->for($space)->approved()->consented()->hearted()
          ->create(['name' => 'With Heart']);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('With Heart');
  });

  test('a testimonial of another space is not shown', function () {
      $space = publicWidgetSpace();

      $other = publicWidgetSpace();

      $widget = publicWidgetWidget($space);

      publicWidgetTestimonial($other, ['name' => 'Other Space']);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertDontSee('Other Space');
  });

  /*
  |--------------------------------------------------------------------------
  | Nothing private is shown
  |--------------------------------------------------------------------------
  */

  test('the email of a testimonial is never shown', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space);

      publicWidgetTestimonial($space, ['email' => 'ada@example.test']);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertDontSee('ada@example.test');
  });

  test('the social url of a testimonial is never shown', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space);

      publicWidgetTestimonial($space, ['social_url' => 'https://example.test/ada']);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertDontSee('https://example.test/ada');
  });

  test('the storage path of a photo is never shown', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space);

      publicWidgetTestimonial($space, ['photo_path' => 'testimonials/1/secret.png']);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertDontSee('secret.png');
  });

  test('the moderation flags are never shown', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space);

      publicWidgetTestimonial($space);

      $response = $this->get(route('widgets.show', [$widget->public_id]));

      $response->assertOk();

      // The flags are looked for as attribute names, as data attribute
      // names, and as keys of a JSON object. The page legitimately
      // carries the word "hidden" in the aria-hidden attribute of the
      // decorative star icon, and that attribute tells a screen reader
      // to ignore the icon, so it leaks nothing about the
      // testimonial. The lookbehind keeps the match on the name
      // itself: it refuses a match that is preceded by a word
      // character or a dash, so aria-hidden is not read as hidden.
      $content = $response->getContent();

      expect(preg_match(
          '/(?<![\w-])(?:data-)?(approved|consented|hidden)\s*=|["\'](?:approved|consented|hidden)["\']\s*:/i',
          $content,
      ))->toBe(0);
  });

  /*
  |--------------------------------------------------------------------------
  | The layout
  |--------------------------------------------------------------------------
  */

  test('the masonry layout is rendered', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space, ['layout' => WidgetLayout::Masonry]);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('klw-widget--masonry');
  });

  test('the carousel layout is rendered', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space, ['layout' => WidgetLayout::Carousel]);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('klw-widget--carousel');
  });

  test('the carousel track carries the track marker', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space, ['layout' => WidgetLayout::Carousel]);

      // A testimonial is needed, because the track is only rendered
      // when there is a card to put in it. Without one the widget
      // shows the empty message and there is no track to mark.
      publicWidgetTestimonial($space);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('data-klw-track', false);
  });

  test('the masonry layout carries no track marker', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space, ['layout' => WidgetLayout::Masonry]);

      // A testimonial is needed here for the same reason. The marker
      // must be absent because the masonry layout does not use a
      // track, not because the widget happened to be empty.
      publicWidgetTestimonial($space);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertDontSee('data-klw-track', false);
  });

  /*
  |--------------------------------------------------------------------------
  | The appearance
  |--------------------------------------------------------------------------
  */

  test('the light theme is rendered', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space, ['theme' => WidgetAppearance::Light]);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('data-klw-theme="light"', false);
  });

  test('the dark theme is rendered', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space, ['theme' => WidgetAppearance::Dark]);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('data-klw-theme="dark"', false);
  });

  test('the light theme uses the light card colour', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space, ['theme' => WidgetAppearance::Light]);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('--klw-card: #ffffff;', false);
  });

  test('the dark theme uses the dark card colour', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space, ['theme' => WidgetAppearance::Dark]);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('--klw-card: #111827;', false);
  });

  test('a valid background colour is used as the surface', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space, ['background' => '#123456']);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('--klw-surface: #123456;', false);
  });

  test('a background colour that is not a hex value falls back to the default', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space, ['background' => 'red; position: fixed']);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('--klw-surface: #ffffff;', false)
          ->assertDontSee('red; position: fixed', false);
  });

  test('a three digit hex colour falls back to the default', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space, ['background' => '#abc']);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('--klw-surface: #ffffff;', false);
  });

  test('a background colour with trailing characters falls back to the default', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space, ['background' => '#123456; background: url(x)']);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('--klw-surface: #ffffff;', false);
  });

  test('no background colour falls back to the default', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space, ['background' => null]);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('--klw-surface: #ffffff;', false);
  });

  test('a dark widget without a background uses the dark default', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space, ['theme' => WidgetAppearance::Dark, 'background' => null]);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('--klw-surface: #0b1120;', false);
  });

  /*
  |--------------------------------------------------------------------------
  | The animation
  |--------------------------------------------------------------------------
  */

  test('animation is enabled when the widget asks for it', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space, ['animation' => true]);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('data-klw-animation="true"', false);
  });

  test('animation is disabled when the widget asks for it', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space, ['animation' => false]);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('data-klw-animation="false"', false);
  });

  /*
  |--------------------------------------------------------------------------
  | The limit
  |--------------------------------------------------------------------------
  */

  test('the limit of the widget is respected', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space, ['limit' => 2]);

      for ($index = 1; $index <= 5; $index++) {
          publicWidgetTestimonial($space, ['name' => 'Customer '.$index]);
      }

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('Customer 5')
          ->assertDontSee('Customer 1')
          ->assertDontSee('Customer 2');
  });

  test('a limit of one shows a single testimonial', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space, ['limit' => 1]);

      for ($index = 1; $index <= 3; $index++) {
          publicWidgetTestimonial($space, ['name' => 'Only '.$index]);
      }

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('Only 3')
          ->assertDontSee('Only 1')
          ->assertDontSee('Only 2');
  });

  test('a limit larger than the number of testimonials shows all of them', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space, ['limit' => 10]);

      publicWidgetTestimonial($space, ['name' => 'The Only One']);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('The Only One');
  });

  /*
  |--------------------------------------------------------------------------
  | The newest are shown first
  |--------------------------------------------------------------------------
  */

  test('the newest testimonial is shown first', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space, ['limit' => 1]);

      publicWidgetTestimonial($space, ['name' => 'Older One']);

      publicWidgetTestimonial($space, ['name' => 'Newer One']);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('Newer One')
          ->assertDontSee('Older One');
  });

  /*
  |--------------------------------------------------------------------------
  | An empty widget
  |--------------------------------------------------------------------------
  */

  test('a widget without testimonials shows the empty message', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('No testimonials yet');
  });

  /*
  |--------------------------------------------------------------------------
  | The response
  |--------------------------------------------------------------------------
  */

  test('the widget is not cached', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space);

      $response = $this->get(route('widgets.show', [$widget->public_id]));

      $response->assertOk();

      // The header is read through the directive lookup rather than
      // compared as a string, because Symfony re-serialises the header
      // in its own order.
      expect($response->headers->hasCacheControlDirective('no-store'))->toBeTrue()
          ->and($response->headers->getCacheControlDirective('no-store'))->toBeTrue();
  });

  test('the widget page is not indexed', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('noindex', false);
  });

  test('the widget page does not load a webfont', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space);

      // A font would be fetched from a third party host, which would tell
      // that host the address of the visitor of the page the widget is
      // embedded in.
      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertDontSee('fonts.bunny.net', false)
          ->assertDontSee('fonts.googleapis.com', false);
  });

  test('the widget page loads only the widget assets', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space);

      $response = $this->get(route('widgets.show', [$widget->public_id]));

      $response->assertOk();

      // The built file names carry a content hash, so the source paths
      // are not in the page. The asset method resolves a source path to
      // the url the page actually links, so the page is checked
      // against that instead of against the source path.
      $vite = app(Illuminate\Foundation\Vite::class);

      expect($response->getContent())
          ->toContain($vite->asset('resources/css/widget.css'))
          ->toContain($vite->asset('resources/js/widget.js'));
  });

  test('the widget page does not load the application stylesheet', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space);

      $response = $this->get(route('widgets.show', [$widget->public_id]));

      $response->assertOk();

      // The application bundle must not be loaded on a widget page. The
      // built file names carry a content hash, so the asset method
      // resolves the source path to the url the page would link, and
      // that url must be absent from the page.
      $vite = app(Illuminate\Foundation\Vite::class);

      expect($response->getContent())
          ->not->toContain($vite->asset('resources/css/app.css'))
          ->not->toContain($vite->asset('resources/js/app.js'));
  });

  test('the widget page carries the title of the space', function () {
      $space = publicWidgetSpace(null, ['title' => 'Acme Reviews']);

      $widget = publicWidgetWidget($space);

      $this->get(route('widgets.show', [$widget->public_id]))
          ->assertOk()
          ->assertSee('Acme Reviews');
  });

  test('the widget page does not require a visitor to be signed in', function () {
      $space = publicWidgetSpace();

      $widget = publicWidgetWidget($space);

      $this->assertGuest();

      $this->get(route('widgets.show', [$widget->public_id]))->assertOk();
  });
