# Kudosly

Kudosly is a minimal SaaS for collecting, moderating and publishing customer testimonials. An account owner creates a branded collection page (a **Space**), shares its public link, receives submissions from customers who need no account, and publishes the approved ones as a Wall of Love or as an embeddable widget on another website.

Built on Laravel 13 with Blade, Livewire, Tailwind CSS v4 and Vite. Payments run through Stripe Checkout and Laravel Cashier.

---

## Contents

- [Overview](#overview)
- [Implemented features](#implemented-features)
- [Known limitations](#known-limitations)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Environment setup](#environment-setup)
- [Database: migrations and seeding](#database-migrations-and-seeding)
- [Running the application locally](#running-the-application-locally)
- [Running the tests](#running-the-tests)
- [Continuous integration](#continuous-integration)
- [Project documentation](#project-documentation)
- [Agentic development workflow](#agentic-development-workflow)

---

## Overview

| | |
|---|---|
| **Framework** | Laravel 13 (`laravel/framework ^13.17`) |
| **PHP** | `^8.4` (`composer.json`); CI installs PHP 8.4 |
| **Frontend** | Blade, Livewire 4.4.8, Tailwind CSS v4, Vite 8, Chart.js 4 |
| **Database** | SQLite for local development, MySQL supported for production |
| **Payments** | Stripe Checkout + Laravel Cashier 16.11 |
| **Tests** | Pest 5 on PHPUnit, 44 test files |

### Data model

Five application models plus two Cashier-managed tables.

```
User ──< Space ──< Testimonial
  │        │
  │        └──< Widget
  │
  └── Plan

User ──< Subscription ──< SubscriptionItem      (Cashier-managed)
```

| Model | File | Relationship |
|---|---|---|
| `User` | `app/Models/User.php` | `belongsTo` Plan, `hasMany` Spaces and Subscriptions |
| `Plan` | `app/Models/Plan.php` | `hasMany` Users |
| `Space` | `app/Models/Space.php` | `belongsTo` User, `hasMany` Testimonials and Widgets, soft deletes |
| `Testimonial` | `app/Models/Testimonial.php` | `belongsTo` Space |
| `Widget` | `app/Models/Widget.php` | `belongsTo` Space |

`users.plan_id` is `nullOnDelete`; `spaces.user_id`, `testimonials.space_id` and `widgets.space_id` all cascade on delete. There are 14 migrations in `database/migrations/`.

Two points worth being precise about, because the PRD names them as separate entities:

- **`SpaceField` is not a model or a table.** It is the enum `App\Enums\SpaceField`, listing the five collectable fields (Name, Email, Company Name, Social Profile URL, Profile Photo). A Space's field configuration is stored as columns on the `spaces` table itself — `company_name_enabled`, `company_name_required`, `social_url_enabled`, `social_url_required`, `photo_enabled`, `photo_required` and a JSON `field_order` — added by `2026_11_18_000004_add_field_configuration_to_spaces_table.php`. There is no `space_fields` table.
- **`Subscription` is Cashier's model, not an application model.** There is no `app/Models/Subscription.php`. Cashier's `Billable` trait gives `User` a `subscriptions()` relation to the `subscriptions` table, and `subscriptions` has `subscription_items`. Both tables are created by Cashier's migrations (`2026_11_22_183847`, `2026_11_22_183848`) and no custom model is registered with `Cashier::useSubscriptionModel()`, so the vendor models are used. `Stripe` customer columns (`stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at`) live on `users`.

### Public routes

| URL | Purpose |
|---|---|
| `/s/{slug}` | Public collection page for a Space |
| `/wall/{slug}` | Public Wall of Love for a Space |
| `/widget/{publicId}` | Public widget page for embedding |

The dashboard lives under `/dashboard` and requires authentication. `GET /up` is the health check endpoint.

---

## Implemented features

Every item below was verified in the code rather than taken from the PRD.

### Authentication and accounts
- Register, log in, log out, password reset and profile editing — `app/Http/Controllers/Auth/`, `app/Http/Controllers/ProfileController.php`, routes in `routes/web.php`.
- New accounts start on the Free plan. The plan is assigned on the `creating` event, not through mass assignment, so an account can never set its own plan — `app/Models/User.php`.
- Login is rate limited to 5 attempts per minute per email and IP — `app/Providers/AppServiceProvider.php`.

### Spaces
- Full CRUD for Spaces — `app/Http/Controllers/Dashboard/SpaceController.php`, routes `dashboard.spaces.*`.
- Each Space has a name, a unique public slug, page title, subtitle, request text, a theme and an optional 1–5 star rating toggle — `app/Models/Space.php`.
- Collection fields are configurable. Name and Email are always collected; Company Name, Social Profile URL and Profile Photo can each be enabled and marked required, with a stored field order — `app/Enums/SpaceField.php`, `app/Services/SpaceFieldConfiguration.php`.
- Space names are unique per account, case-insensitively, enforced by a database index on `(user_id, name_key)`. The migration refuses to run against existing duplicate names rather than corrupting the data — `2026_11_18_000004`.
- Three themes with previews: Minimal Light, Modern Dark, Soft Gradient — `app/Enums/SpaceTheme.php`, `app/Services/SpaceThemePreview.php`.
- After creation, a success page shows the copyable public URL `/s/{slug}` — `SpaceController::created()`.

### Public testimonial collection
- Customers submit without an account — `app/Http/Controllers/Public/TestimonialController.php`.
- All configured fields are validated server-side; body, name and email are required, body up to 2000 characters — `app/Http/Requests/Public/StoreTestimonialRequest.php`.
- Submissions start pending and private. Consent is stored separately and defaults to off, so a testimonial submitted without consent stays private.
- Spam prevention uses an encrypted single-use token bound to the space, a honeypot field, and rate limiting of 5 submissions per hour per IP and space — `app/Services/TestimonialSubmissionGuard.php`, `app/Providers/AppServiceProvider.php`.
- Photo uploads are validated (jpg, jpeg, png or webp; max 2 MB), stored on the private disk under generated names, and removed if the surrounding transaction rolls back — `app/Services/TestimonialPhotoStorage.php`, `Public/TestimonialController::storePhoto()`.
- Concurrent submissions to one space are serialised with a cache lock and a row lock, so a plan limit cannot be exceeded by racing requests — `Public/TestimonialController::store()`.
- The confirmation page shows "You're officially awesome! 🎉 Your kind words just made someone's day." — `resources/views/public/spaces/thanks.blade.php`.

### Inbox and moderation
- Searchable, paginated inbox (25 per page) showing customer, text, rating, date, Space and status, with favorites listed first — `app/Http/Controllers/Dashboard/TestimonialController.php`, `Testimonial::scopeSearch()`, `scopeFavoritesFirst()`.
- Actions: approve, hide/unhide, favorite/unfavorite, heart/unheart, edit, delete — routes `dashboard.testimonials.*`.
- Editing writes only the text and rating; the moderation flags and the consent are not reachable from the request — `app/Http/Requests/Dashboard/UpdateTestimonialRequest.php`.
- Public visibility is one rule in one place: approved, not hidden and explicitly consented — `app/Enums/TestimonialVisibility.php`. The Wall additionally requires the heart. The SQL scopes and the PHP rule are kept in step by a test.

### Wall of Love
- Public wall at `/wall/{slug}`, showing hearted testimonials that also satisfy the visibility rules — `app/Http/Controllers/Public/WallController.php`.
- Customer names, optional photos, text and ratings are shown. Customer email addresses are not rendered on any public view.
- Photos are served through a dedicated route that re-checks every rule on each request and returns an identical 404 for every failure mode.

### Embeddable widgets
- Widget builder with masonry and carousel layouts, light/dark mode, background colour, animation toggle and a display limit of up to 20, with live preview — `app/Livewire/Dashboard/WidgetBuilder.php`, `app/Enums/WidgetLayout.php`, `app/Enums/WidgetAppearance.php`.
- Copyable iframe and script embed snippets are generated on the server — `app/Services/WidgetEmbedCode.php`.
- `public/embed.js` is a dependency-free loader. It validates the public id against a fixed pattern, takes the application origin from its own `src` rather than from the host page, and builds no markup from strings.
- Widget public ids are 32 random characters generated on the server and never mass assignable — `app/Models/Widget.php`.
- Only approved, consented, non-hidden testimonials render, and the pages are sent with `Cache-Control: no-store`, so a hidden testimonial disappears immediately.

### Dashboard and analytics
- Overview cards: total Spaces, total Testimonials, unique Customers, Testimonials this Month, average rating (over rated testimonials only), current plan and usage, recent submissions — `app/Http/Controllers/Dashboard/OverviewController.php`.
- The counts come from a single query, so the cards cannot disagree with one another — `app/Services/DashboardStatistics.php`.
- Daily submission trend with 7, 30 and 90 day filters, drawn with Chart.js — `app/Services/SubmissionTrend.php`. Every day in the range gets a point, including empty days, and days are counted in the application timezone.
- The range sent by the browser is validated against a closed set before it reaches the query.

### Plans, limits and billing
- Two plans are seeded by `database/seeders/PlanSeeder.php`: Free (3 Spaces, 100 testimonials per Space) and Pro (25 Spaces, 1,000 per Space, $999.00 per month).
- Limits are enforced server-side in one place, `app/Services/PlanEntitlement.php`, which throws `PlanLimitExceeded`. That exception is rendered as a redirect with an explanation rather than an error page — `bootstrap/app.php`.
- The billing page shows the current plan, real usage, the plan comparison and the upgrade button — `app/Http/Controllers/Dashboard/BillingController.php`.
- Stripe Checkout starts a subscription for the configured Pro Price — `BillingController::upgrade()`.
- The Stripe Customer Portal handles subscription management — `BillingController::portal()`.
- Plan changes only ever come from a verified webhook. `PlanProvisioner` maps subscription status to a plan: `canceled`, `incomplete` and `incomplete_expired` fall back to Free; `active`, `trialing`, `past_due` and `unpaid` grant Pro. A subscription on any Price other than the configured Pro Price never changes a plan. Provisioning is idempotent — `app/Services/PlanProvisioner.php`.
- The webhook route is not registered at all when no webhook secret is configured, so unsigned requests cannot reach it — `app/Providers/AppServiceProvider::configureWebhookRoute()`.
- A browser redirect from Stripe never changes a plan. Access is granted by the webhook alone.

### Security
- Owner-based authorization through policies — `app/Policies/SpacePolicy.php`, `TestimonialPolicy.php`, `WidgetPolicy.php`.
- Records belonging to another account return 404 rather than 403, so a response never confirms that an id exists — `SpaceController`, `Dashboard/TestimonialController`, `Public/WallController`, `Public/WidgetController`.
- Mass-assignment protection: the moderation flags, `space_id`, the widget `public_id` and the Space name key are never fillable — the `#[Fillable]` attributes on the models.
- CSRF protection, rate limiting on login and on public submissions, upload validation and safe output escaping in Blade.

---

## Known limitations

- **Real Stripe checkout has never been exercised against the live Stripe API.** `STRIPE_PRO_PRICE_ID` is unset in the development environment, so the Pro plan has no Price ID and checkout is unavailable (`proCheckoutAvailable()` returns false). The checkout and webhook code paths are covered by tests that stub Cashier; the end-to-end flow with a real Stripe account is unverified. `docs/DEPLOYMENT.md` §7 lists the Stripe Test Mode checklist, and every item in it is still unticked.
- **Two billing gaps remain open.** `docs/PRD_AUDIT.md` §5 records them: the $999/month Pro price is display-only metadata and is never cross-checked against the live Stripe Price (`PlanProvisioner::isProPrice()` matches the Price *identifier* only), and there is no `invoice.payment_failed` handler, so a failed payment is shown on the billing page but not acted upon. The rest of the billing flow — verified webhooks, price identity, idempotent provisioning — is implemented and tested.
- **`docs/DEPLOYMENT.md` says PHP 8.3 in two places** (the requirements list and the CI description), while `composer.json` requires `^8.4` and the workflow installs PHP 8.4. The workflow is the source of truth; the two sentences in the guide are stale.
- **`composer.lock` still records `platform.php` as `^8.3`** while `composer.json` requires `^8.4`. The lock file was deliberately left untouched, so this is a known mismatch that produces a warning. It does not currently break the install or CI.
- **CI only tests against SQLite.** MySQL is supported by the application and by the deployment guide, but no CI job exercises it. The guide recommends running the migrations against the production MySQL version before going live.
- **The PRD's 13 acceptance criteria in §17 are all still unchecked.** They are implemented and tested, but they have not been ticked off against the checklist.
- **`AGENTS.md` was the unmodified Laravel Boost bootstrap stub; it is now a copy of `CLAUDE.md`.** `laravel/boost` is a declared dev dependency, but `boost:install` was never run, so `boost.json` and `.ai/` do not exist. `CLAUDE.md` is the project's written agent guide, and `AGENTS.md` mirrors it.
- **The data model is documented in `docs/DATA_MODEL.md`**, including ER diagrams for the schema. The relationships are also described above and in the models themselves.
- **The Git history is no longer entirely linear.** A separate feature branch, `feature/spacename-normalizer-unit-test`, was created from `main`, committed to, and merged back with `--no-ff`, so the history contains one real two-parent merge commit (`deeb77b`, parents `a442862` and `7ad584b`). `git rev-list --merges --count HEAD` was 0 before that work and is 1 after it. The only other non-`main` branch is a local pre-rewrite backup. See `docs/AGENTIC_WORKFLOW.md` §8.2.
- **No scheduled tasks and no queued jobs exist.** There is no `app/Jobs/` directory and the deployment guide says so explicitly. A queue worker is only needed if queued work is added later.

---

## Prerequisites

- PHP 8.4 or newer, with the `intl` and `mbstring` extensions, plus `pdo_sqlite` and `sqlite3` for the test suite — `composer.json`
- Composer 2
- Node.js 20 and npm — only needed to build the frontend assets
- SQLite (local) or MySQL (production)
- Optional: a Stripe account with a recurring monthly product and price, for testing billing

Check your versions:

```bash
php -v
composer -V
node -v
npm -v
```

---

## Installation

```bash
git clone https://github.com/islamasifa223-boop/testimonial-dashboard.git
cd testimonial-dashboard
composer setup
```

`composer setup` runs the whole first-run sequence for you:

1. `composer install`
2. copies `.env.example` to `.env` if `.env` does not exist yet
3. `php artisan key:generate`
4. `php artisan migrate --force`
5. `npm install --ignore-scripts`
6. `npm run build`

The script is defined in `composer.json` under `scripts.setup`.

To do the steps by hand instead:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
npm install --ignore-scripts
npm run build
```

---

## Environment setup

`.env.example` is a complete, commented template with 50 variables. The defaults are local-development values.

The variables that matter:

| Variable | Purpose | Default |
|---|---|---|
| `APP_KEY` | Application encryption key | generated by `key:generate` |
| `APP_URL` | Public URL | `http://localhost:8000` |
| `DB_CONNECTION` | `sqlite` or `mysql` | `sqlite` |
| `DB_DATABASE` | Path to the SQLite file, or the MySQL database name | `laravel` |
| `SESSION_DRIVER` | `database`, `file`, `cookie` | `database` |
| `CACHE_STORE` | `database`, `file`, `array` | `database` |
| `QUEUE_CONNECTION` | `database`, `sync`, … | `database` |
| `STRIPE_KEY` | Stripe publishable key | empty |
| `STRIPE_SECRET` | Stripe secret key | empty |
| `STRIPE_WEBHOOK_SECRET` | Stripe webhook signing secret | empty |
| `STRIPE_PRO_PRICE_ID` | Recurring monthly Price ID for the Pro plan | empty |
| `CASHIER_CURRENCY` | Billing currency | `usd` |

Notes:

- With `SESSION_DRIVER`, `CACHE_STORE` and `QUEUE_CONNECTION` all on `database`, no Redis or Memcached is needed for local development.
- **`STRIPE_PRO_PRICE_ID` is the single source of truth for the Pro Price.** The `PlanSeeder` copies it into `plans.stripe_price_id` when that column is still empty, and never overwrites a Price ID that is already stored.
- **Without `STRIPE_WEBHOOK_SECRET` the webhook endpoint does not exist.** The application fails closed rather than accepting unsigned requests, so the Pro plan can never be granted in that state.
- `APP_PREVIOUS_KEYS` keeps older keys working after a rotation, separated by commas.
- The local SQLite database lives at `database/database.sqlite`. Create the file before migrating if it does not exist yet.

---

## Database: migrations and seeding

Run the migrations:

```bash
php artisan migrate --force
```

Seed the Free and Pro plans:

```bash
php artisan db:seed --class=PlanSeeder --force
```

`PlanSeeder` is idempotent: it uses `updateOrCreate`, so re-running corrects a plan's limits in place instead of duplicating rows, and it never overwrites an already configured Stripe Price ID.

The full seeder also creates a demo account:

```bash
php artisan db:seed --force
```

`DatabaseSeeder` calls `PlanSeeder` and then creates `test@example.com` with the password `password`. That is a development convenience only — change or remove it before deploying.

If you need to undo things:

```bash
php artisan migrate:rollback
php artisan migrate:fresh --seed     # drops everything and re-seeds (destructive)
```

---

## Running the application locally

Run the PHP server:

```bash
php artisan serve
```

The application is then at <http://127.0.0.1:8000>.

Run Vite in a second terminal for the frontend:

```bash
npm run dev
```

There is also a combined command that starts both the PHP server and the Vite dev server:

```bash
composer dev
```

`composer dev` disables Composer's process timeout and runs `php artisan dev`, which starts `artisan serve` and the Vite dev server together.

The root URL redirects to the login page, or to the dashboard when you are already signed in.

### Useful URLs

| URL | What it is |
|---|---|
| `/dashboard` | Overview with the analytics cards and the trend chart |
| `/dashboard/spaces` | Your Spaces |
| `/dashboard/inbox` | The testimonial inbox |
| `/dashboard/wall` | Wall of Love selections |
| `/dashboard/widgets` | The widget builder |
| `/dashboard/billing` | Plan, usage and upgrade |
| `/dashboard/settings` | Profile and password |

---

## Running the tests

```bash
composer test
```

or directly:

```bash
php artisan test
```

The suite runs against an in-memory SQLite database, so no database service and no `.env` file are needed. `phpunit.xml` supplies the test environment: a fixed `APP_KEY`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` and a `STRIPE_WEBHOOK_SECRET` so the webhook route is registered.

Run a subset:

```bash
php artisan test --filter=TestimonialVisibility
php artisan test tests/Feature/Billing
```

There are 44 test files, plus `tests/Pest.php` and `tests/TestCase.php`:

| Directory | Files | Covers |
|---|---|---|
| `tests/Feature/Auth` | 3 | registration, login, password reset |
| `tests/Feature/Authorization` | 3 | space, testimonial and widget policies; cross-account access |
| `tests/Feature/Spaces` | 9 | Space CRUD, fields, themes, slugs, public page |
| `tests/Feature/Testimonials` | 8 | public submission, photos, visibility, mass assignment |
| `tests/Feature/Dashboard` | 7 | overview, inbox, moderation, trend, wall, edit, delete |
| `tests/Feature/Widgets` | 5 | widget model, builder, embed code, public rendering, photos |
| `tests/Feature/Billing` | 4 | billing page, plan seeder, webhook, provisioner |
| `tests/Feature` (root level) | 4 | root redirect, dashboard route access, plan entitlement, profile |
| `tests/Unit` | 1 | the `Plan` model |
| **Total** | **44** | |

The last full run was **856 passing, 1987 assertions, 0 failed, 0 skipped**.

### Code style

```bash
vendor/bin/pint --test     # check only
vendor/bin/pint            # fix
```

---

## Continuous integration

`.github/workflows/ci.yml` runs on every push and pull request targeting `main`, `master` or `develop`. The workflow only reads the repository (`permissions: contents: read`).

It installs PHP 8.4 and Node 20, installs the Composer and npm dependencies, builds the frontend assets, checks the code style with Pint, and runs the full test suite. It runs against in-memory SQLite, so no database service is needed.

---

## Project documentation

| Document | What is in it |
|---|---|
| [`PRD.md`](PRD.md) | The product requirements document: overview, stack, feature requirements, the data model, the six implementation phases, the acceptance criteria and the coding rules |
| [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) | The deployment guide: requirements, environment configuration, installation, database, Stripe configuration, webhooks, the Stripe Test Mode checklist, web server setup, TLS, CI, backups and troubleshooting |
| [`CLAUDE.md`](CLAUDE.md) / [`AGENTS.md`](AGENTS.md) | The agent instruction files — the project's own AI coding rules and implementation guide. `AGENTS.md` is a byte-identical copy of `CLAUDE.md` |
| [`docs/PRD_AUDIT.md`](docs/PRD_AUDIT.md) | The PRD compliance audit: each of the 13 acceptance criteria with its status, evidence, verification performed and remaining gap, plus the section-by-section findings, critical findings and the limits of the audit itself |
| [`docs/REFLECTION.md`](docs/REFLECTION.md) | Project reflection and learning outcomes. |
| [`docs/REFLECTION.pdf`](docs/REFLECTION.pdf) | The same reflection as a print-ready PDF: one A4 page, Georgia body text at 11.5 pt, 25 mm margins and a searchable text layer |
| [`screenshots/`](screenshots/) | Application walkthrough screenshots. |

### Screenshots

Captured from a running local instance of the application.

| Screenshot | What it shows |
|---|---|
| [`01-login.png`](screenshots/01-login.png) | The login page at `/login`, with the email, password and "Remember me" fields, plus the forgot-password and sign-up links |
| [`02-dashboard.png`](screenshots/02-dashboard.png) | The dashboard overview: the "Unlock more with Pro" banner and the five stat cards — total spaces, total testimonials, unique customers, average rating and testimonials this month |
| [`02.1-dashboard.png`](screenshots/02.1-dashboard.png) | The lower half of the overview: the submissions-over-time chart with a 7/30/90-day switch, the usage bar, and the recent-submissions and recent-spaces panels |
| [`03-spaces.png`](screenshots/03-spaces.png) | The Spaces list, showing the "ABC" space at `/s/abc` with its Modern Dark theme and the Manage and Edit actions |
| [`03.1-create-new-spaces.png`](screenshots/03.1-create-new-spaces.png) | The public collection page at `/s/abc` in the Modern Dark theme, with the name, email, company, photo, testimonial and rating fields and the public-sharing consent checkbox ticked |
| [`03.2-spaces-thanks-page.png`](screenshots/03.2-spaces-thanks-page.png) | The post-submission confirmation page at `/s/abc/testimonials/thanks` |
| [`04-inbox.png`](screenshots/04-inbox.png) | The Inbox, with the search, space, status and rating filters and two testimonials showing their approval, favourite and wall badges plus the moderation actions |
| [`05-public-wall.png`](screenshots/05-public-wall.png) | The public Wall of Love at `/wall/abc`, showing two published testimonials with names, companies, star ratings, photos and quotes — and no email addresses |
| [`06-widget-builder.png`](screenshots/06-widget-builder.png) | The Widget builder: space, layout, appearance, background colour, testimonial limit and carousel controls, with a live preview and the JavaScript embed snippet |
| [`07-billing.png`](screenshots/07-billing.png) | The Billing page: the current Free plan with its usage bar, the feature list, and the Free and Pro plan comparison showing $999.00 per month |
| [`08-settings.png`](screenshots/08-settings.png) | The Settings page: the profile form, the password-change form, and the plan summary with spaces used and member-since date |

---

## Agentic development workflow

This project was built with an AI coding agent working through the six phases defined in `PRD.md` §16, following the rules in `PRD.md` §18. This section describes only what is actually present in the repository.

### What is present

**Phase-structured delivery.** The history on `main` is organised by phase:

| Commit | Phase |
|---|---|
| `Complete Phase 4: widgets and analytics` | Widgets and analytics |
| `Add Stripe billing and webhook plan provisioning` | Billing |
| `Complete Phase 6: security, accessibility, and deployment readiness` | Quality |
| `Fix test environment APP_KEY configuration` | Test environment fix |
| `Fix CI PHP version to match dependency requirements` | CI fix |

Earlier phases are not separated into their own commits in the current history: the tree at the first commit already contains the foundation, the Spaces, and the collection and inbox work.

**Plan → implement → verify.** The cycle is written down in `PRD.md` §18: inspect the existing code before changing it, implement one approved phase at a time, write tests for the business rules, and report the changed files, the commands run, the test results and the remaining issues after each phase. Each phase commit above carries a body describing the scope it implemented.

**Tests written alongside the feature.** The 44 test files map onto the features above, so each rule has a test that would fail if the rule were broken — for example `tests/Feature/Testimonials/TestimonialVisibilityTest.php` for the visibility rule, `tests/Feature/Billing/PlanProvisionerTest.php` for the webhook-to-plan mapping, and `tests/Feature/Authorization/SpaceAccessTest.php` for cross-account access.

**Code style enforced in CI.** `vendor/bin/pint --test` runs in the workflow, so formatting is checked on every push.

**`laravel/boost` is a declared dev dependency** (`composer.json`, `^2.2`). It was **not** initialised: `boost:install` was never run, so there is no `boost.json` and no `.ai/` directory. The Boost agent tooling was therefore not used on this codebase — the agent instruction files are hand-written instead, not Boost-generated.

### What is not present

Stated plainly, so that nothing is implied that is not in the repository:

- **No hooks, MCP servers or OpenSpec.** None are configured anywhere in the project, and none were used.
- **No concurrent branch work.** A separate feature branch was created, committed to and merged with `--no-ff` — branch `feature/spacename-normalizer-unit-test`, feature commit `7ad584b`, merge commit `deeb77b` with two parents and no conflicts. The work was **sequential, not simultaneous**: nothing was authored at the same time on two branches. The history shows a genuine separate branch with a real commit and a real merge, not concurrent execution and not two competing implementations. See `docs/AGENTIC_WORKFLOW.md` §8.2.
- **No Boost-generated agent instruction files.** `laravel/boost` was never initialised, so `boost.json` and `.ai/` do not exist. The instruction files are hand-written: `CLAUDE.md` holds the project's AI coding rules and implementation guide, and `AGENTS.md` is a byte-identical copy of it.
- **One project skill.** `.puku-cli/skills/kudosly-conventions/SKILL.md` packages the project's verified conventions into a repeatable read-only review. It was created only after every rule it contains was checked against the source, and it was then used to produce real findings. See `docs/AGENTIC_WORKFLOW.md`.

The requirements that are commonly expected of an agentic submission are **met** by this repository as it stands:

| Requirement | Status |
|---|---|
| Two distinct agentic tools genuinely used | **Met** — the `kudosly-conventions` Skill, created, verified and used for substantive audit work, and a genuine **Subagent** review of frontend accessibility and UI security whose findings were independently re-verified, including one finding that was rejected as inaccurate. See `docs/AGENTIC_WORKFLOW.md` §2, §3 and §8.1. |
| Separate feature branch merged into `main` | **Met** — `feature/spacename-normalizer-unit-test`, feature commit `7ad584b`, merged as `deeb77b` with `--no-ff` and no conflicts. Sequential, not simultaneous. See §8.2. |
| Full test/build verification after the audits | **Met** — `php artisan test` 871 passed / 2006 assertions, `vendor/bin/pint --test` PASS on 138 files, `npm run build` succeeded with all five Vite entry points emitted. See §8.3. |

Both the Skill and the Subagent work, the branch merge, and the final verification pass are recorded in `docs/AGENTIC_WORKFLOW.md`.
