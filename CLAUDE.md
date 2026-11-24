# Kudosly — Agent Guide

Kudosly is a testimonial collection SaaS. Account owners create branded collection pages called **Spaces**, share a public link, receive submissions from customers who need no account, and publish approved testimonials as a **Wall of Love** or an embeddable **widget**.

This file is the project-specific guidance for AI coding sessions. It describes how this codebase actually works. Where it disagrees with generic Laravel advice, this file wins.

---

## 1. AI coding rules

These are the project's rules, from `PRD.md` §18. They take precedence over any general default.

1. Work only inside the existing Laravel project. Do not rebuild it in another framework.
2. Inspect existing code before changing files.
3. Preserve Laravel 13 compatibility.
4. Prefer Blade, Livewire, and standard Laravel conventions.
5. Implement one approved phase at a time.
6. Do not hardcode credentials.
7. Request approval before installing new packages, changing environment configuration, or performing destructive operations.
8. Write tests for implemented business rules.
9. Report changed files, commands run, test results, and remaining issues after each phase.

### Project-specific additions

10. **Never trust browser input.** Server-generated values are never accepted from the request: the space `slug`, the widget `public_id`, the space `name_key`, the `plan_id`, and the testimonial moderation flags are all set server-side and are excluded from `#[Fillable]`.
11. **A rule that appears in more than one place lives in one place.** Business rules that would otherwise be repeated (visibility, plan limits, field configuration, slug generation) belong in `app/Services/` or `app/Enums/`, not inline in a controller or a view.
12. **Write the "why" in a comment.** This codebase documents non-obvious decisions — why a 403 became a 404, why a count is a sum and not a count, why a migration refuses to run. Match that density and that tone: explain the reasoning, not the mechanics.
13. **Do not weaken a test to make it pass.** A failing test is either a real bug or a wrong expectation. Fix the code, or change the expectation deliberately and say why.

---

## 2. Stack and versions

| Layer | Technology |
|---|---|
| Framework | Laravel 13 (`laravel/framework ^13.17`) |
| PHP | `^8.4` — `composer.json` requires it; CI installs PHP 8.4 |
| Frontend | Blade, Livewire 4.4.8, Tailwind CSS v4 (via `@tailwindcss/vite`), Vite 8 |
| Charts | Chart.js 4 |
| Payments | Stripe Checkout + Laravel Cashier 16.11 |
| Tests | Pest 5 on PHPUnit |
| Code style | Laravel Pint (`laravel/pint ^1.27`) — no `pint.json`, so the Laravel preset applies |

Required PHP extensions: `intl`, `mbstring`, plus `pdo_sqlite` and `sqlite3` for the test suite.

Local development uses SQLite. MySQL is supported for production but is **not** exercised by CI.

---

## 3. Architecture

### Layers

```
routes/web.php
    │
    ├── Http/Controllers/Public/      ← anonymous surfaces (collection page, wall, widget)
    ├── Http/Controllers/Dashboard/   ← authenticated surfaces
    ├── Http/Controllers/Auth/
    │
    ├── Http/Requests/                ← validation, authorisation, payload shaping
    ├── Livewire/Dashboard/           ← interactive widgets (widget builder, nav)
    │
    ├── Models/                       ← Eloquent, one file per entity
    ├── Policies/                     ← owner-based authorisation
    ├── Services/                     ← business rules, one concern each
    ├── Enums/                        ← closed sets: PlanName, SpaceField, SpaceTheme,
    │                                    TestimonialVisibility, WidgetLayout, WidgetAppearance
    ├── Listeners/                    ← HandleStripeSubscriptionEvent
    └── Exceptions/                   ← PlanLimitExceeded, SlugGenerationFailed
```

**`app/Services/` is where business rules live.** One class per concern, each with a single job:

| Service | Responsibility |
|---|---|
| `PlanEntitlement` | All plan limit checks. The only place a limit is enforced. |
| `PlanProvisioner` | Maps a verified Stripe webhook to a plan change. |
| `TestimonialVisibility` logic | In the `TestimonialVisibility` enum, not a service. |
| `DashboardStatistics` | The overview figures, from a single query. |
| `SubmissionTrend` | The daily submission series, 7/30/90-day ranges. |
| `SpaceSlugGenerator` | Generates a unique slug. Never taken from the browser. |
| `SpaceNameNormalizer` | The normalised `name_key` used for uniqueness. |
| `SpaceFieldConfiguration` | Converts field config to/from columns. |
| `SpaceThemePreview` | Theme preview values. |
| `TestimonialSubmissionGuard` | Single-use encrypted submission tokens. |
| `TestimonialPhotoStorage` | Stores, serves and deletes photos on the private disk. |
| `WidgetEmbedCode` | Builds the iframe and script snippets. |
| `WidgetAppearance` | Widget colour mode resolution. |

### Route groups

`routes/web.php` defines three groups:

- **Public** — `/s/{slug}`, `/wall/{slug}`, `/widget/{publicId}`, plus the photo routes for the wall and widget. No authentication.
- **Guest** — register, login, forgot-password, reset-password. Uses the `guest` middleware.
- **Authenticated** — everything under `/dashboard`, plus logout. Uses the `auth` middleware.

`/` redirects to the dashboard when signed in, otherwise to the login page.

`bootstrap/app.php` registers the `guest` and `auth` middleware aliases, redirects guests to `login`, and renders the two custom exceptions as friendly redirects rather than error pages.

---

## 4. Key directories and file locations

| Path | Contents |
|---|---|
| `app/Models/` | `User`, `Plan`, `Space`, `Testimonial`, `Widget` — five entities |
| `app/Enums/` | The closed sets the codebase validates against |
| `app/Services/` | Business rules |
| `app/Policies/` | `SpacePolicy`, `TestimonialPolicy`, `WidgetPolicy` |
| `app/Http/Requests/` | Form requests; `Dashboard/`, `Public/`, `Auth/` |
| `app/Livewire/Dashboard/` | `Navigation`, `WidgetBuilder` |
| `resources/views/dashboard/` | Dashboard pages |
| `resources/views/public/` | Public collection, wall and widget pages |
| `resources/views/components/` | Blade components — `ui/`, `dashboard/`, `layouts/` |
| `resources/views/livewire/dashboard/` | Livewire component templates |
| `resources/css/`, `resources/js/` | `app.*` for the dashboard, `widget.*` for embedded widgets |
| `database/migrations/` | 14 migrations |
| `database/seeders/` | `DatabaseSeeder`, `PlanSeeder` |
| `database/factories/` | One factory per model, with named states |
| `tests/Feature/`, `tests/Unit/` | Pest test files |

### The two CSS/JS bundles

`vite.config.js` builds **five** entry points, not two:

```
resources/css/app.css        resources/js/app.js        → the dashboard
resources/css/widget.css     resources/js/widget.js     → embedded widgets
resources/js/chart.js                                → the analytics chart
```

`resources/js/widget.js` and `resources/css/widget.css` are served to third-party sites and must stay dependency-free and self-contained. `public/embed.js` is a separate, hand-written loader served straight from `public/` — it does **not** go through the build.

---

## 5. Database and Eloquent conventions

### Models

Five application models, plus two Cashier-managed tables.

- **`SpaceField` is not a model.** It is the enum `App\Enums\SpaceField`. A Space's field configuration is stored as columns on `spaces` (`company_name_enabled`, `company_name_required`, `social_url_enabled`, `social_url_required`, `photo_enabled`, `photo_required`, and a JSON `field_order`). There is no `space_fields` table.
- **`Subscription` is Cashier's model.** There is no `app/Models/Subscription.php`. Cashier's `Billable` trait supplies `User::subscriptions()`; `subscriptions` has `subscription_items`. No custom model is registered with `Cashier::useSubscriptionModel()`.
- **Stripe customer columns** (`stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at`) live on `users`.

### Mass assignment

Models use the **`#[Fillable]` attribute**, not a `$fillable` property:

```php
#[Fillable(['name', 'email', 'company_name', 'social_url', 'photo_path', 'body', 'rating'])]
class Testimonial extends Model
```

Only customer-supplied fields are fillable. `consented`, `approved`, `hidden`, `favorite`, `hearted` and `space_id` are **not** — they are set server-side through direct assignment or the relation.

### Casting

Enums are cast in `casts()`; booleans and integers are cast explicitly; JSON columns are cast to `array`.

### Ownership

`Testimonial` and `Widget` do **not** carry a `user_id`. They belong to a Space, and the Space belongs to the account. Ownership always goes through the space, using the `ownedBy()` scope:

```php
public function scopeOwnedBy(Builder $query, User $user): Builder
{
    return $query->whereHas('space', fn (Builder $query) => $query->where('user_id', $user->getKey()));
}
```

Because `Space` uses soft deletes, testimonials and widgets of a deleted space are excluded automatically.

### Other model conventions

- `Space` uses `SoftDeletes`. A soft-deleted space name stays reserved.
- `Widget::public_id` is generated on `creating` with `Str::random(32)` and is never mass assignable.
- `User::plan_id` is assigned in a `creating` hook, so an account can never set its own plan.
- `Space::name_key` is derived in a `saving` hook and is never accepted from the browser.
- Use `forceFill()` when server-side code must write a guarded attribute.

### Migrations

- Use `Schema::create` / `Schema::table` with `foreignId` and explicit `cascadeOnDelete` / `nullOnDelete`.
- Migrations that can fail on existing data should **preflight before touching the schema**, then throw with an explanation. See `2026_11_18_000004_add_field_configuration_to_spaces_table.php`, which detects duplicate names, aborts with a readable message, and leaves the database untouched.
- Cashier owns the `subscriptions`, `subscription_items`, `customers` and `jobs`/`cache` tables — do not hand-edit them.

### Seeding

`PlanSeeder` is idempotent (`updateOrCreate`) and **never overwrites an already stored `stripe_price_id`**. It copies the Price ID from `STRIPE_PRO_PRICE_ID` only when the column is still empty. Re-running it must never break a live environment.

`DatabaseSeeder` calls `PlanSeeder` and creates `test@example.com` / `password`. That is a development convenience.

---

## 6. Authentication and authorization

- Session auth only. Register, login, logout, password reset, profile editing.
- Guests are redirected to `login`; the `guest` middleware group protects the auth screens.
- New accounts start on the Free plan.
- Login is rate limited to **5 per minute** per email and IP — `RateLimiter::for('login')` in `AppServiceProvider`.
- Public testimonial submission is rate limited to **5 per hour** per IP and space — `RateLimiter::for('testimonial-submission')`.

### Policies

Owner-based authorisation lives in `app/Policies/`. `SpacePolicy`, `TestimonialPolicy` and `WidgetPolicy` compare the account id against the owning space.

### The 404 rule — important

**A resource belonging to another account returns 404, never 403.** A 403 would confirm that the id refers to a real record, letting an account probe ids it does not own. Apply this in controllers (`abort_unless(..., 404)`), in form requests (`failedAuthorization()` → `abort(404)`), and on public routes. Every failure mode of a public photo route returns the same 404, so the response never reveals which ids exist.

### Public surfaces

Public routes re-check their rules on **every request**. Nothing is trusted because it was true when the page was rendered: a testimonial that is hidden, unapproved, or has consent withdrawn has to stop being shown at once. Widget pages are sent with `Cache-Control: no-store` for the same reason.

---

## 7. Validation and security conventions

### Form requests

Validation lives in `app/Http/Requests/`, not in controllers.

- `authorize()` checks ownership **before** the payload is validated, so a validation failure cannot confirm that an id exists.
- `rules()` holds the rules; `prepareForValidation()` canonicalises the input first so `validated()` returns canonical values.
- `messages()` and `attributes()` give readable field names.
- `failedValidation()` moves an internal validation attribute's error onto the visible field and removes the internal attribute from the error bag. See `SpaceRequest::failedValidation()` moving the `name_key` uniqueness error onto `name`.
- Use `Rule::enum()` for enum-validated fields, and `Rule::unique()` scoped to the owner.

### The visibility rule

Public visibility is **three independent booleans** — approved, not hidden, consented — expressed once in `App\Enums\TestimonialVisibility`. The Wall additionally requires the heart.

The PHP method and the SQL scope both exist because a query cannot call a PHP method. A test keeps them in step; **keep that test passing** if you touch either side.

### Other security rules

- **CSRF** is on by default. Do not disable it.
- **Rate limiting** on login and on public submission — see above.
- **Uploads** are validated by mime type and size, stored on the **private** disk (`local`) under a generated name, and served only through a dedicated route that re-checks every rule. `TestimonialPhotoStorage` never uses the uploaded filename.
- **Mass assignment** — see §5. Server-generated values stay server-generated.
- **Output** is escaped by Blade. Never build markup in a controller or a service; `WidgetEmbedCode` builds snippets as plain strings and escapes the interpolated title.
- **Credentials** are never hardcoded. Secrets come from `.env` through `config/`.
- **The Stripe webhook fails closed.** Without `STRIPE_WEBHOOK_SECRET` the webhook route is not registered at all (`AppServiceProvider::configureWebhookRoute()`), so unsigned requests cannot reach it. A plan is granted **only** by a verified webhook, never by a browser redirect.
- **A public submission is spam-guarded** by an encrypted single-use token bound to the space, a honeypot field, and the rate limiter (`TestimonialSubmissionGuard`).

---

## 8. Frontend conventions

- **Blade** for pages; **Livewire** for the interactive parts (`WidgetBuilder`, `Navigation`). Do not add a JavaScript framework.
- Reusable Blade components live in `resources/views/components/` — `ui/` (generic), `dashboard/` (dashboard-specific), `layouts/`.
- Livewire components live in `app/Livewire/Dashboard/` with templates in `resources/views/livewire/dashboard/`. Use `#[Locked]` on properties the browser must not change.
- Tailwind v4, with the project palette in `@theme` as `brand-*` OKLCH scales. `@source` directives in `resources/css/app.css` tell Tailwind where to scan, including `app/Livewire/**/*.php`.
- Dark mode uses the custom variant `dark (&:where(.dark, .dark *))`.
- `resources/js/app.js` contains the character counter, which counts **code points** (`Array.from`) so it agrees with PHP's `mb_strlen()`. Keep that agreement.
- `public/embed.js` must stay dependency-free. It takes the application origin from its own `src`, never from the host page, and builds no markup from strings.

---

## 9. Commands

All verified against this repository.

```bash
# First-time setup (composer install → .env → key → migrate → npm install → npm build)
composer setup

# Run the app and the Vite dev server together
composer dev

# Run the test suite
composer test
```

Individual commands:

```bash
php artisan serve            # PHP server on http://127.0.0.1:8000
php artisan dev              # serve + vite together (what `composer dev` runs)
npm run dev                  # Vite dev server only
npm run build                # production build into public/build

php artisan migrate --force
php artisan db:seed --class=PlanSeeder --force
php artisan db:seed --force  # also creates the demo account

php artisan test
php artisan test --filter=TestimonialVisibility
php artisan test tests/Feature/Billing

vendor/bin/pint --test       # check formatting
vendor/bin/pint              # fix formatting
```

`composer dev` runs `php artisan dev`, which starts `artisan serve` and the Vite dev server together.

---

## 10. Testing conventions

- **Pest**, using `test()` closures and `expect()`. PHPUnit assertions are the exception, not the rule.
- `tests/Pest.php` holds the shared helpers: `seedPlans()`, `validSpacePayload()`, `validToken()`, `validSubmission()`, `removeModelEventListener()`. **The test database is migrated but never seeded**, so every Feature test that creates a user must call `seedPlans()` in `beforeEach()`. A missing plan row is the most common cause of a confusing failure.
- `RefreshDatabase` is applied to `Feature` only (`pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature')`). `tests/Unit` is plain PHPUnit — no database.
- Tests run against **in-memory SQLite** (`DB_DATABASE=:memory:`), configured entirely in `phpunit.xml`. No `.env` is needed and no database service is required.
- `phpunit.xml` supplies a fixed `APP_KEY` (deliberately public, test-only — never reuse it) and `STRIPE_WEBHOOK_SECRET`, which is required for the webhook route to exist at all.
- Organise by feature area: `tests/Feature/Auth`, `Authorization`, `Spaces`, `Testimonials`, `Dashboard`, `Widgets`, `Billing`, plus `tests/Unit`.
- Use the model factories and their named states (`Plan::factory()->free()`, `Testimonial::factory()->publiclyVisible()->hidden()`) rather than building attributes by hand.
- Assert on behaviour and status codes. A test that asserts on a rendered string is brittle — `assertSee` also escapes, so a string containing `&` or `<` will not match a raw literal.
- **Every business rule needs a test.** A rule without a test is a rule that can be broken silently.

---

## 11. Formatting and build

- **Pint** with the Laravel preset (no `pint.json` in this repo). Run `vendor/bin/pint --test` before finishing; CI runs it on every push.
- `.editorconfig`: 4 spaces, LF, UTF-8, final newline, trailing whitespace trimmed. Markdown is exempt from the whitespace trim.
- CI also runs `npm run build`, so a change to `resources/` that breaks the Vite build fails the pipeline.

---

## 12. Continuous integration

`.github/workflows/ci.yml` — read-only (`permissions: contents: read`), triggered on push and pull request to `main`, `master` or `develop`.

Steps: checkout → setup PHP 8.4 (`intl`, `mbstring`, `pdo_sqlite`, `sqlite3`) → cache Composer packages → `composer install` → setup Node 20 → `npm ci` → `npm run build` → `vendor/bin/pint --test` → `php artisan test`.

CI tests against SQLite only.

---

## 13. Known limitations and constraints

Read this before assuming a feature works end to end.

- **Real Stripe checkout is unverified.** `STRIPE_PRO_PRICE_ID` is unset in the development environment, so the Pro plan has no Price ID and checkout is unavailable (`BillingController::proCheckoutAvailable()` returns false). The checkout and webhook paths are covered by tests that stub Cashier, but the live end-to-end flow has never been run. `docs/DEPLOYMENT.md` §7 holds the Stripe Test Mode checklist, and every item in it is unticked.
- **`docs/DEPLOYMENT.md` says PHP 8.3 in two places** (the requirements list and the CI description). `composer.json` and the workflow both say 8.4. The workflow is the source of truth; the guide is stale.
- **`composer.lock` still records `platform.php` as `^8.3`** while `composer.json` requires `^8.4`. The lock file was left untouched deliberately, so this is a known warning, not a break.
- **`AGENTS.md` is a copy of this file.** It was the unmodified Laravel Boost bootstrap stub until it was synced. Update `AGENTS.md` alongside `CLAUDE.md` if you change this file.
- **`laravel/boost` is a declared dev dependency but was never initialised.** `boost:install` was never run, so there is no `boost.json` and no `.ai/` directory. Do not assume Boost tooling is available.
- **`docs/DATA_MODEL.md` exists** and documents the schema and ER diagrams. The relationships are also described here and in the models.
- **The Git history is linear** — zero merge commits, no parallel feature branches.
- **No scheduled tasks and no queued jobs.** There is no `app/Jobs/` directory. Plan changes are applied synchronously while the webhook request is handled. A queue worker is only needed if queued work is added.
- **The PRD's 13 acceptance criteria (`PRD.md` §17) are all unchecked.** They are implemented and tested, but they have not been ticked off.

---

## 14. Where to look next

| Question | File |
|---|---|
| What is the product? | `PRD.md` |
| How do I deploy it? | `docs/DEPLOYMENT.md` |
| What does the product do? | `README.md` |
| What are the rules for plan limits? | `app/Services/PlanEntitlement.php` |
| When is a testimonial public? | `app/Enums/TestimonialVisibility.php` |
| How does a plan change? | `app/Services/PlanProvisioner.php` |
| How is a submission protected? | `app/Services/TestimonialSubmissionGuard.php` |
| What are the routes? | `routes/web.php` |
| How are the DB tables shaped? | `database/migrations/` |
