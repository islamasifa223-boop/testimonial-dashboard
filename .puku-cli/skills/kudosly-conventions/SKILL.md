---
name: kudosly-conventions
description: Audit Kudosly application code against the project's verified Laravel conventions - 404-not-403 authorization, #[Fillable] attribute style, space-scoped ownership, server-generated values, entitlement enforcement, and test setup requirements. Use when reviewing, extending, or checking the accuracy of any code under app/, tests/, or database/ in this repository.
---

# Kudosly Conventions Audit

Read-only review of Kudosly source against the project's actual conventions. Report findings;
do not rewrite code unless explicitly asked.

## Source of truth

Verify every claim against the repository before reporting it. Never report a convention that
cannot be located in code:

- `CLAUDE.md` - project-specific agent guide
- `PRD.md` section 18 - the nine AI coding rules
- `database/migrations/` - the only authority on schema
- `app/Models/`, `app/Services/`, `app/Http/Requests/`, `app/Http/Controllers/` - implementation

## Conventions to check

### 1. Authorization returns 404, never 403

Cross-account access must be indistinguishable from a missing record, so identifiers cannot be
probed. The verified forms in this repository are:

- `abort_unless($condition, 404)` in controllers
  (`Dashboard/SpaceController.php`, `Dashboard/TestimonialController.php::findTestimonial()`)
- `failedAuthorization()` returning `abort(404)`
  (`Requests/Dashboard/SpaceRequest.php`, `Requests/Dashboard/UpdateTestimonialRequest.php`)
- `abort(404)` for a public resource that does not exist or is not published
  (`Public/WallController.php`, `Public/WidgetController.php`)

Flag any `abort(403)`, `deny()`, or a policy denial that surfaces as 403 for an ownership
failure. A 403 confirms the id belongs to a real record.

### 2. Fillable and hidden attributes, not properties

Laravel 13 style in this codebase - all five models use the attributes:

```php
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
```

`#[Fillable]` appears on `Plan`, `Space`, `Testimonial`, `User`, `Widget`. `#[Hidden]` appears on
`User` only.

Flag any model that declares `protected $fillable` or `protected $hidden` properties.

Note: `Testimonial` and `Space` reference Eloquent's own `Model::$hidden` in docblocks, and the
`hidden` column is therefore read through `getAttribute('hidden')` rather than `$this->hidden`.
That is deliberate, not a defect.

### 3. Ownership is always resolved through the space

`Testimonial` and `Widget` carry only `space_id`. Neither has a `user_id` column. Ownership is
derived with a scope that joins through the space, so soft-deleted spaces are excluded
automatically:

```php
public function scopeOwnedBy(Builder $query, User $user): Builder
{
    return $query->whereHas('space', fn (Builder $query) => $query->where('user_id', $user->getKey()));
}
```

Present on both `Testimonial` and `Widget`.

Flag any direct `where('user_id', ...)` ownership check on a space-scoped model, and any attempt
to add a redundant `user_id` column to `testimonials` or `widgets`.

### 4. Server-generated values are never accepted from the browser

Two different mechanisms are in play. Identify which one applies before reporting a violation.

**Model hooks** - the value is generated inside the model and is absent from `#[Fillable]`:

| Value | Mechanism |
|---|---|
| `spaces.name_key` | `Space::booted()` - `saving` hook, `SpaceNameNormalizer::key()` |
| `widgets.public_id` | `Widget::booted()` - `creating` hook, `Str::random(32)` |
| `users.plan_id` | `User::booted()` - `creating` hook, defaults to the Free plan |

**Service or controller layer** - the value IS mass-assignable at the model level, so the
guarantee rests on the caller never passing it in:

| Value | Mechanism |
|---|---|
| `spaces.slug` | `SpaceSlugGenerator::createFor()` sets the slug before `fill()`; `slug` IS in `Space`'s `#[Fillable]` |
| `testimonials.photo_path` | `TestimonialPhotoStorage` writes the file, then the controller assigns `$testimonial->photo_path`; `photo_path` IS in `Testimonial`'s `#[Fillable]` |

For the second group, flag a controller that mass-assigns a request payload straight onto the
model (for example `Space::create($request->all())`) instead of going through
`SpaceSlugGenerator` or the request's own attribute whitelist.

### 5. Moderation flags are set server-side only

`approved`, `hidden`, `favorite`, `hearted` and `consented` are absent from `Testimonial`'s
`#[Fillable]`. They are written by direct attribute assignment in the dashboard controller
(`$model->approved = ! $model->approved; $model->save();`), never from request input.

`UpdateTestimonialRequest::STORED_ATTRIBUTES` is exactly `['body', 'rating']`. The customer
details and the consent are the customer's, and are deliberately not editable by the owner.

Flag any attempt to make these fields fillable, or to accept them from a form.

### 6. Validation lives in form requests

Business validation belongs in `app/Http/Requests/`, not inline in controllers.

Public testimonial submission uses `StoreTestimonialRequest`, which carries:

- `kudosly_submission_token` (`TOKEN_ATTRIBUTE`) - required anti-spam token
- `website` (`HONEYPOT_ATTRIBUTE`) - honeypot field
- `SPAM_MESSAGE` - one uniform message for both the honeypot hit and a rejected token, so the
  honeypot never reveals itself

The token itself is issued and verified by `TestimonialSubmissionGuard`: encrypted payload
carrying issue time, nonce and space id; one-time nonce claimed in the cache; minimum age of 3
seconds; maximum age of 86400 seconds; the space id checked on use so a token cannot be replayed
against another space.

Field availability follows `SpaceField`. `Name` and `Email` are always enabled and always
required; `CompanyName`, `SocialUrl` and `Photo` are configurable. Rating is NOT part of
`SpaceField` - it is governed by the separate boolean `spaces.rating_enabled`, and
`prepareForValidation()` nulls the rating when that flag is off.

### 7. Entitlement is enforced in one place

`PlanEntitlement` (in `app/Services/`) owns the enforcement: `canCreateSpace`,
`assertCanCreateSpace`, `canSubmitTestimonial`, `assertCanSubmitTestimonial`, and the
`spacesRemaining` / `spacesUsed` / `testimonialsUsed` helpers. It throws `PlanLimitExceeded`.

`User::spacesLimit()` and `User::testimonialsLimit()` are read helpers that return the limit
off the user's plan - `PlanEntitlement` delegates to them, so this is not a duplicate
enforcement path and should not be reported as one.

`PlanName` has only two cases, `Free` and `Pro`. There is no third tier, so a hard-coded tier
check outside `PlanEntitlement` is a finding.

### 8. Plans are granted only by a verified webhook

The webhook route is fail-closed. `AppServiceProvider::configureWebhookRoute()` runs during
`register()`, before Cashier registers its own routes:

```php
if (blank(config('cashier.webhook.secret'))) {
    Cashier::ignoreRoutes();
    return;
}
Cashier::$registersRoutes = true;
```

With no secret configured the endpoint does not exist at all, so an unsigned request cannot
reach the handler.

`PlanProvisioner` is the only place a paid plan is granted, and it is reached only from
`Listeners/HandleStripeSubscriptionEvent`, which is registered on `WebhookHandled` in
`AppServiceProvider::boot()`. Confirm no other caller grants a plan.

Inside `PlanProvisioner` two separate steps must not be conflated:

- **Price identity** - `isProPrice()` compares the subscription's Price ID against
  `Plan::stripePriceId()` using `hash_equals`. A subscription on any other Price never changes
  the plan, and no live Stripe API call is made.
- **Status to plan** - `targetPlan()` maps the Stripe status constant to a `PlanName`:
  `canceled`, `incomplete`, `incomplete_expired` to Free; `active`, `trialing`, `past_due`,
  `unpaid` to Pro; anything else to `null`, which leaves the plan unchanged.

The plan is written with `forceFill()` and only when it differs from the current plan, so
repeated webhook deliveries are safe.

### 9. Seeding and schema details

- `PlanSeeder` uses `Plan::updateOrCreate(['name' => ...], $plan)`, so it is idempotent and
  corrects plan limits in place rather than duplicating rows.
- `PlanSeeder::applyStripePriceId()` sets the Pro Price ID from `services.stripe.pro_price_id`
  only when the Pro plan has none yet. A stored Price ID is treated as authoritative and is
  never overwritten, and the Free plan is never given one.
- Cashier v16 has no `customers` table. It uses `users.stripe_id`, declared
  `nullable()->index()` and deliberately NOT unique.
- `subscriptions.user_id` and `subscription_items.subscription_id` are declared with
  `foreignId()` and no `->constrained()`, so they carry no FK constraint; both have a plain
  composite index instead. This appears intentional - do not "fix" it without a deliberate
  decision.
- The Cashier migrations that actually run are the repo's own copies in
  `database/migrations/`. The package does not load its own migrations for this project;
  `Cashier::$customerModel`, `$subscriptionModel` and `$subscriptionItemModel` are not
  overridden, and the repo's `users`, `subscriptions` and `subscription_items` tables are the
  authoritative schema.
- There are 14 migrations in `database/migrations/` in total.

### 10. Test conventions

- `RefreshDatabase` is applied to the `Feature` suite only
  (`tests/Pest.php`: `pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature')`).
  `tests/Unit/` is plain PHPUnit with no database access.
- The test database is migrated but never seeded. Any Feature test that creates a user must
  call `seedPlans()` from `beforeEach()`, which creates the Free and Pro plan rows via
  factories. A test that creates a user without it will fail on the missing plan.
- Shared helpers live in `tests/Pest.php` rather than in an individual test file, because a
  helper defined in one test file is not available when another file runs on its own. The
  existing helpers are `seedPlans()`, `validSpacePayload()`, `validToken()`, `validPayload()`,
  `validSubmission()` and `removeModelEventListener()`.
- Tests must not be weakened to make a failure pass.

### 11. Frontend conventions

- Five Vite entry points in `vite.config.js`: `resources/css/app.css`,
  `resources/js/app.js`, `resources/js/chart.js`, `resources/css/widget.css`,
  `resources/js/widget.js`.
- `public/embed.js` is hand-written, dependency-free and served outside the build. Do not add a
  bundler dependency to it.
- Formatting is Pint with the Laravel preset. There is no `pint.json` in the repository, so do
  not assume custom rules.
- Useful commands: `composer test` (runs `php artisan test`), `composer setup`,
  `vendor/bin/pint --test`, `npm run build`.

## Output format

For each finding report:

1. File and line
2. Convention at risk (numbered above)
3. What the code does
4. What the convention requires
5. Severity: blocking / should-fix / observation

End with a short summary of counts by severity. If a convention does not apply to the reviewed
area, say so rather than omitting it silently.
