<?php

use App\Enums\PlanName;
use App\Exceptions\PlanLimitExceeded;
use App\Http\Requests\Public\StoreTestimonialRequest;
use App\Models\Plan;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\PlanEntitlement;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Public testimonial submission
|--------------------------------------------------------------------------
|
| These tests cover the anonymous submission flow at /s/{slug}. They prove
| that a valid submission is stored, that the one-time token cannot be
| forged or replayed, and that the plan quota and the rate limiter are
| enforced.
|
*/

beforeEach(function () {
    seedPlans();
});

/*
|--------------------------------------------------------------------------
| A valid submission
|--------------------------------------------------------------------------
*/

test('a valid submission is stored for the space', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space))
        ->assertRedirect(route('spaces.testimonials.thanks', $space->slug));

    $testimonial = Testimonial::query()->firstOrFail();

    expect(Testimonial::query()->count())->toBe(1)
        ->and($testimonial->space_id)->toBe($space->id)
        ->and($testimonial->name)->toBe('Ada Lovelace')
        ->and($testimonial->email)->toBe('ada@example.com')
        ->and($testimonial->body)->toBe('A wonderfully ordinary testimonial.');
});

test('a valid submission redirects to the thank you page', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space))
        ->assertRedirect(route('spaces.testimonials.thanks', $space->slug))
        ->assertSessionHas('status', 'Your testimonial has been received.');
});

test('the thank you page is reachable and shows the space title', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->get(route('spaces.testimonials.thanks', $space->slug))
        ->assertOk()
        ->assertSee($space->title);
});

test('a new testimonial is not approved and not visible publicly', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space));

    $testimonial = Testimonial::query()->firstOrFail();

    expect($testimonial->approved)->toBeFalse()
        ->and($testimonial->hidden)->toBeFalse()
        ->and($testimonial->favorite)->toBeFalse()
        ->and($testimonial->hearted)->toBeFalse()
        ->and($testimonial->isPubliclyVisible())->toBeFalse();
});

test('the consent checkbox is recorded when it is ticked', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, ['consent' => '1']));

    expect(Testimonial::query()->firstOrFail()->consented)->toBeTrue();
});

test('a submission without the consent checkbox is not consented', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space));

    expect(Testimonial::query()->firstOrFail()->consented)->toBeFalse();
});

test('the moderation flags cannot be set through the submission', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $other = Space::factory()->create();

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        'approved' => '1',
        'hidden' => '1',
        'favorite' => '1',
        'hearted' => '1',
        'space_id' => $other->id,
    ]));

    $testimonial = Testimonial::query()->firstOrFail();

    expect($testimonial->approved)->toBeFalse()
        ->and($testimonial->hidden)->toBeFalse()
        ->and($testimonial->favorite)->toBeFalse()
        ->and($testimonial->hearted)->toBeFalse()
        ->and($testimonial->space_id)->toBe($space->id);
});

test('a submission is stored for the space identified by the slug', function () {
    $space = Space::factory()->create(['slug' => 'my-public-space']);
    $other = Space::factory()->create(['slug' => 'another-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space));

    $testimonial = Testimonial::query()->firstOrFail();

    expect(Testimonial::query()->count())->toBe(1)
        ->and($testimonial->space_id)->toBe($space->id)
        ->and($testimonial->space_id)->not->toBe($other->id);
});

test('a submission to an unknown slug returns a 404', function () {
    $this->post(route('spaces.testimonials.store', 'no-such-space'), [
        StoreTestimonialRequest::TOKEN_ATTRIBUTE => validPayload(),
    ])
        ->assertNotFound();
});

test('a submission to a soft deleted space returns a 404', function () {
    $space = Space::factory()->create(['slug' => 'deleted-space']);

    $space->delete();

    $this->post(route('spaces.testimonials.store', 'deleted-space'), validSubmission($space))
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

test('a submission without a name is rejected', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, ['name' => '']))
        ->assertSessionHasErrors('name');

    expect(Testimonial::query()->count())->toBe(0);
});

test('a submission without an email is rejected', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, ['email' => '']))
        ->assertSessionHasErrors('email');

    expect(Testimonial::query()->count())->toBe(0);
});

test('a submission with an invalid email is rejected', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, ['email' => 'not-an-email']))
        ->assertSessionHasErrors('email');

    expect(Testimonial::query()->count())->toBe(0);
});

test('a submission without a body is rejected', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, ['body' => '']))
        ->assertSessionHasErrors('body');

    expect(Testimonial::query()->count())->toBe(0);
});

test('a body of exactly 2000 characters is accepted', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        'body' => str_repeat('a', 2000),
    ]))->assertSessionHasNoErrors();

    expect(Testimonial::query()->count())->toBe(1)
        ->and(Testimonial::query()->firstOrFail()->body)->toHaveLength(2000);
});

test('a body of 2001 characters is rejected', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        'body' => str_repeat('a', 2001),
    ]))->assertSessionHasErrors('body');

    expect(Testimonial::query()->count())->toBe(0);
});

test('a failed submission redirects back to the public page', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, ['body' => '']))
        ->assertRedirect(route('spaces.show', $space->slug));
});

test('a required company name must be filled in', function () {
    $space = Space::factory()->create([
        'slug' => 'public-space',
        'company_name_enabled' => true,
        'company_name_required' => true,
    ]);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, ['company_name' => '']))
        ->assertSessionHasErrors('company_name');

    expect(Testimonial::query()->count())->toBe(0);
});

test('an optional company name may be left empty', function () {
    $space = Space::factory()->create([
        'slug' => 'public-space',
        'company_name_enabled' => true,
        'company_name_required' => false,
    ]);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, ['company_name' => '']))
        ->assertSessionHasNoErrors();

    expect(Testimonial::query()->count())->toBe(1);
});

test('a required social url must be filled in', function () {
    $space = Space::factory()->create([
        'slug' => 'public-space',
        'social_url_enabled' => true,
        'social_url_required' => true,
    ]);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, ['social_url' => '']))
        ->assertSessionHasErrors('social_url');

    expect(Testimonial::query()->count())->toBe(0);
});

test('a social url that is not a url is rejected', function () {
    $space = Space::factory()->create([
        'slug' => 'public-space',
        'social_url_enabled' => true,
    ]);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, ['social_url' => 'not a url']))
        ->assertSessionHasErrors('social_url');

    expect(Testimonial::query()->count())->toBe(0);
});

test('an enabled company name is stored', function () {
    $space = Space::factory()->create([
        'slug' => 'public-space',
        'company_name_enabled' => true,
    ]);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        'company_name' => 'Analytical Engines Ltd.',
    ]));

    expect(Testimonial::query()->firstOrFail()->company_name)->toBe('Analytical Engines Ltd.');
});

test('an enabled social url is stored', function () {
    $space = Space::factory()->create([
        'slug' => 'public-space',
        'social_url_enabled' => true,
    ]);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        'social_url' => 'https://example.com/profile',
    ]));

    expect(Testimonial::query()->firstOrFail()->social_url)->toBe('https://example.com/profile');
});

/*
|--------------------------------------------------------------------------
| Disabled fields
|--------------------------------------------------------------------------
*/

test('a disabled company name is not stored', function () {
    $space = Space::factory()->create([
        'slug' => 'public-space',
        'company_name_enabled' => false,
    ]);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        'company_name' => 'Injected Company',
    ]))->assertSessionHasNoErrors();

    expect(Testimonial::query()->firstOrFail()->company_name)->toBeNull();
});

test('a disabled social url is not stored', function () {
    $space = Space::factory()->create([
        'slug' => 'public-space',
        'social_url_enabled' => false,
    ]);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        'social_url' => 'https://example.com/injected',
    ]))->assertSessionHasNoErrors();

    expect(Testimonial::query()->firstOrFail()->social_url)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Rating
|--------------------------------------------------------------------------
*/

test('a rating is stored when the space collects ratings', function () {
    $space = Space::factory()->create(['slug' => 'public-space', 'rating_enabled' => true]);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, ['rating' => 4]));

    expect(Testimonial::query()->firstOrFail()->rating)->toBe(4);
});

test('a rating is ignored when the space does not collect ratings', function () {
    $space = Space::factory()->create(['slug' => 'public-space', 'rating_enabled' => false]);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, ['rating' => 5]))
        ->assertSessionHasNoErrors();

    expect(Testimonial::query()->firstOrFail()->rating)->toBeNull();
});

test('a rating below one is rejected', function () {
    $space = Space::factory()->create(['slug' => 'public-space', 'rating_enabled' => true]);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, ['rating' => 0]))
        ->assertSessionHasErrors('rating');

    expect(Testimonial::query()->count())->toBe(0);
});

test('a rating above five is rejected', function () {
    $space = Space::factory()->create(['slug' => 'public-space', 'rating_enabled' => true]);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, ['rating' => 6]))
        ->assertSessionHasErrors('rating');

    expect(Testimonial::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| The honeypot
|--------------------------------------------------------------------------
*/

test('a filled honeypot field rejects the submission', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        StoreTestimonialRequest::HONEYPOT_ATTRIBUTE => 'https://spam.example.com',
    ]))->assertSessionHasErrors('body');

    expect(Testimonial::query()->count())->toBe(0);
});

test('a filled honeypot field shows the generic spam message', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        StoreTestimonialRequest::HONEYPOT_ATTRIBUTE => 'https://spam.example.com',
    ]))->assertSessionHasErrors([
        'body' => StoreTestimonialRequest::SPAM_MESSAGE,
    ]);
});

test('an empty honeypot field does not block the submission', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        StoreTestimonialRequest::HONEYPOT_ATTRIBUTE => '',
    ]))->assertSessionHasNoErrors();

    expect(Testimonial::query()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| The one-time token
|--------------------------------------------------------------------------
*/

test('a submission without a token is rejected', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $submission = validSubmission($space);

    unset($submission[StoreTestimonialRequest::TOKEN_ATTRIBUTE]);

    // The token rule itself fails, so the error is reported against the
    // token attribute rather than the body.
    $this->post(route('spaces.testimonials.store', $space->slug), $submission)
        ->assertSessionHasErrors(StoreTestimonialRequest::TOKEN_ATTRIBUTE);

    expect(Testimonial::query()->count())->toBe(0);
});

test('a submission with an empty token is rejected', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        StoreTestimonialRequest::TOKEN_ATTRIBUTE => '',
    ]))->assertSessionHasErrors(StoreTestimonialRequest::TOKEN_ATTRIBUTE);

    expect(Testimonial::query()->count())->toBe(0);
});

test('a submission with a forged token is rejected', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        StoreTestimonialRequest::TOKEN_ATTRIBUTE => 'not-a-real-token',
    ]))->assertSessionHasErrors([
        'body' => StoreTestimonialRequest::SPAM_MESSAGE,
    ]);

    expect(Testimonial::query()->count())->toBe(0);
});

test('a submission with a tampered token is rejected', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $token = validToken($space);

    // Change a character in the middle of the token. The last character
    // of a base64 string can carry unused padding bits, so changing it
    // may leave the decoded bytes unchanged and the token usable.
    $middle = intdiv(strlen($token), 2);

    $tampered = substr($token, 0, $middle)
        .(substr($token, $middle, 1) === 'A' ? 'B' : 'A')
        .substr($token, $middle + 1);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        StoreTestimonialRequest::TOKEN_ATTRIBUTE => $tampered,
    ]))->assertSessionHasErrors([
        'body' => StoreTestimonialRequest::SPAM_MESSAGE,
    ]);

    expect(Testimonial::query()->count())->toBe(0);
});

test('a submission with a token that was just issued is rejected', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    // The guard rejects a token younger than three seconds.
    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        StoreTestimonialRequest::TOKEN_ATTRIBUTE => validToken($space, [
            'iat' => now()->getTimestamp(),
        ]),
    ]))->assertSessionHasErrors([
        'body' => StoreTestimonialRequest::SPAM_MESSAGE,
    ]);

    expect(Testimonial::query()->count())->toBe(0);
});

test('a submission with an expired token is rejected', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        StoreTestimonialRequest::TOKEN_ATTRIBUTE => validToken($space, [
            'iat' => now()->getTimestamp() - 86401,
        ]),
    ]))->assertSessionHasErrors([
        'body' => StoreTestimonialRequest::SPAM_MESSAGE,
    ]);

    expect(Testimonial::query()->count())->toBe(0);
});

test('a token issued for another space is rejected', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);
    $other = Space::factory()->create(['slug' => 'another-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        StoreTestimonialRequest::TOKEN_ATTRIBUTE => validToken($other),
    ]))->assertSessionHasErrors([
        'body' => StoreTestimonialRequest::SPAM_MESSAGE,
    ]);

    expect(Testimonial::query()->count())->toBe(0);
});

test('a replayed token is rejected', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    // The token is generated once and the exact same string is submitted
    // twice, so the second attempt is a replay of a used token.
    $token = validToken($space);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        StoreTestimonialRequest::TOKEN_ATTRIBUTE => $token,
    ]))->assertSessionHasNoErrors();

    expect(Testimonial::query()->count())->toBe(1);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        StoreTestimonialRequest::TOKEN_ATTRIBUTE => $token,
    ]))->assertSessionHasErrors([
        'body' => StoreTestimonialRequest::SPAM_MESSAGE,
    ]);

    expect(Testimonial::query()->count())->toBe(1);
});

test('a malformed encrypted payload is rejected', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        StoreTestimonialRequest::TOKEN_ATTRIBUTE => Crypt::encryptString('this is not a payload'),
    ]))->assertSessionHasErrors([
        'body' => StoreTestimonialRequest::SPAM_MESSAGE,
    ]);

    expect(Testimonial::query()->count())->toBe(0);
});

test('an encrypted payload with missing keys is rejected', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        StoreTestimonialRequest::TOKEN_ATTRIBUTE => Crypt::encryptString(json_encode([
            'iat' => now()->getTimestamp() - 10,
        ])),
    ]))->assertSessionHasErrors([
        'body' => StoreTestimonialRequest::SPAM_MESSAGE,
    ]);

    expect(Testimonial::query()->count())->toBe(0);
});

test('an encrypted payload with the wrong types is rejected', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        StoreTestimonialRequest::TOKEN_ATTRIBUTE => Crypt::encryptString(json_encode([
            'iat' => 'yesterday',
            'nonce' => Str::random(40),
            'sid' => $space->id,
        ])),
    ]))->assertSessionHasErrors([
        'body' => StoreTestimonialRequest::SPAM_MESSAGE,
    ]);

    expect(Testimonial::query()->count())->toBe(0);
});

test('every token failure shows the same generic message', function () {
    $other = Space::factory()->create(['slug' => 'another-space']);

    $failures = [
        'forged' => 'not-a-real-token',
        'malformed' => Crypt::encryptString('this is not a payload'),
        'missing keys' => Crypt::encryptString(json_encode(['iat' => now()->getTimestamp() - 10])),
        'wrong types' => Crypt::encryptString(json_encode([
            'iat' => 'yesterday',
            'nonce' => Str::random(40),
            'sid' => 1,
        ])),
        'too young' => null,
        'expired' => null,
        'foreign space' => null,
    ];

    // Every failure is sent to its own space, so each one has its own
    // rate limit allowance and none of them is throttled.
    foreach ($failures as $label => $token) {
        $space = Space::factory()->create(['slug' => 'space-'.Str::slug($label)]);

        if ($token === null) {
            $token = match ($label) {
                'too young' => validToken($space, ['iat' => now()->getTimestamp()]),
                'expired' => validToken($space, ['iat' => now()->getTimestamp() - 86401]),
                'foreign space' => validToken($other),
            };
        }

        $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
            StoreTestimonialRequest::TOKEN_ATTRIBUTE => $token,
        ]))->assertSessionHasErrors([
            'body' => StoreTestimonialRequest::SPAM_MESSAGE,
        ]);
    }

    expect(Testimonial::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| The rate limiter
|--------------------------------------------------------------------------
*/

test('the sixth submission from the same address is throttled', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space))
            ->assertRedirect(route('spaces.testimonials.thanks', $space->slug));
    }

    expect(Testimonial::query()->count())->toBe(5);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space))
        ->assertStatus(429);

    expect(Testimonial::query()->count())->toBe(5);
});

test('the rate limiter is scoped to the space', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);
    $other = Space::factory()->create(['slug' => 'another-space']);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space));
    }

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space))
        ->assertStatus(429);

    // A different space has its own allowance.
    $this->post(route('spaces.testimonials.store', $other->slug), validSubmission($other))
        ->assertRedirect(route('spaces.testimonials.thanks', $other->slug));

    expect(Testimonial::query()->count())->toBe(6);
});

/*
|--------------------------------------------------------------------------
| The plan quota
|--------------------------------------------------------------------------
*/

test('a submission is refused once the space reached its testimonial limit', function () {
    $plan = Plan::query()->where('name', PlanName::Free)->firstOrFail();

    $plan->update(['max_testimonials_per_space' => 1]);

    $owner = User::factory()->create(['plan_id' => $plan->id]);
    $space = Space::factory()->for($owner)->create(['slug' => 'public-space']);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space))
        ->assertRedirect(route('spaces.testimonials.thanks', $space->slug));

    expect(Testimonial::query()->count())->toBe(1);

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space))
        ->assertRedirect(route('spaces.show', $space->slug))
        ->assertSessionHas('status', 'This space is not accepting new testimonials at the moment.');

    expect(Testimonial::query()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| The no-owner entitlement
|--------------------------------------------------------------------------
|
| A space whose owner no longer exists can never collect a testimonial.
| The space is never saved here and the owner is never removed, so no
| foreign key is bypassed and no orphaned record is created.
|
*/

test('a space without an owner cannot collect a testimonial', function () {
    $space = new Space;

    $entitlement = app(PlanEntitlement::class);

    expect($space->user)->toBeNull()
        ->and($entitlement->canSubmitTestimonial($space))->toBeFalse();
});

test('a space without an owner reports a testimonial limit of zero', function () {
    $space = new Space;

    $entitlement = app(PlanEntitlement::class);

    try {
        $entitlement->assertCanSubmitTestimonial($space);

        $this->fail('Expected a PlanLimitExceeded exception.');
    } catch (PlanLimitExceeded $exception) {
        expect($exception->resource)->toBe('testimonial')
            ->and($exception->limit)->toBe(0);
    }
});
