# Kudosly — PRD Compliance Audit

This document audits the Kudosly application against the requirements in `PRD.md` at the repository root.

Every criterion was checked against the actual implementation, not against the existence of a route, a view, or a test. Where a requirement could not be confirmed from source or from read-only runtime observation, it is marked **Cannot verify** rather than guessed.

**Audit date:** 2026-11-24
**Application version:** Laravel 13.42.0 · PHP 8.5.12 · Cashier 16.11 · Livewire 4.4.8 · SQLite (development)
**Audited commit:** `deeb77b` ("Merge branch 'feature/spacename-normalizer-unit-test'")

---

## Table of contents

1. [Method](#1-method)
2. [Summary](#2-summary)
3. [Acceptance criteria (§17)](#3-acceptance-criteria-17)
4. [Section-by-section findings](#4-section-by-section-findings)
5. [Critical findings](#5-critical-findings)
6. [Observations](#6-observations)
7. [Limitations of this audit](#7-limitations-of-this-audit)
8. [Items still requiring manual verification](#8-items-still-requiring-manual-verification)
9. [Overall assessment](#9-overall-assessment)

---

## 1. Method

### 1.1 Requirements document

The requirements document is `PRD.md` at the repository root. The location was unambiguous: a search of the repository for `*prd*`, `*requirement*`, and `*spec*` filenames returned only `./PRD.md`.

The PRD has 18 sections. Section 17 holds 13 acceptance criteria, all of which are still unchecked (`[ ]`) in the file itself — the PRD was not updated as implementation progressed.

### 1.2 Source-code inspection

The following were read in full or in relevant part:

- `routes/web.php` — the complete route map
- `bootstrap/app.php` — middleware aliases and exception rendering
- `app/Http/Controllers/` — all 15 controllers (Auth ×4, Dashboard ×6, Public ×5)
- `app/Http/Requests/` — all 6 form requests
- `app/Models/` — all 5 models (`User`, `Plan`, `Space`, `Testimonial`, `Widget`)
- `app/Policies/` — all 3 policies
- `app/Services/` — all 9 services
- `app/Livewire/Dashboard/` — both Livewire components (`Navigation`, `WidgetBuilder`)
- `app/Enums/`, `app/Listeners/`, `app/Providers/AppServiceProvider.php`
- `database/migrations/` — all 14 migrations
- `resources/views/` — the layout, dashboard, public, and auth views
- `public/embed.js`

### 1.3 Runtime verification

The application was served locally with `php artisan serve` on `127.0.0.1:8765` and probed with **22 read-only HTTP GET requests**.

Explicitly **not** performed:

- No POST request of any kind was issued.
- No login or logout was attempted.
- No real user credentials were used.
- No domain data was created, updated, deleted, approved, hidden, favorited, or otherwise mutated.
- No file upload was submitted.

Record counts were taken before and after the runtime phase and are identical:

```
users=5 spaces=3 testimonials=2 widgets=2 plans=2
```

The development server was stopped after verification, and the port was confirmed closed.

### 1.4 Automated tests

```
php artisan test
Tests:    871 passed (2006 assertions)
Duration: 30.22s
```

`RefreshDatabase` is applied to the `Feature` suite only, so this run used the test database and did not touch the development SQLite file.

---

## 2. Summary

| Status | Count |
|---|---|
| Verified | 11 |
| Partially implemented | 2 |
| Not implemented | 0 |
| Cannot verify | 0 |

The two partially implemented criteria are both in the billing and Stripe area (§17 criteria 10 and 12). They are narrow, well-localized gaps rather than structural omissions.

No critical security, authorization, or data-model defect was found.

---

## 3. Acceptance criteria (§17)

### 3.1 Criterion 1 — Users can register and securely access their own dashboard

**Status:** Verified (source inspection + guest runtime checks)

**Evidence:**

- Registration — `app/Http/Controllers/Auth/RegisteredUserController.php:30-47`
- Login — `app/Http/Controllers/Auth/AuthenticatedSessionController.php:25-32`
- Logout — `app/Http/Controllers/Auth/AuthenticatedSessionController.php:37-45`
- Password reset link — `app/Http/Controllers/Auth/PasswordResetLinkController.php:24-38`
- Password reset — `app/Http/Controllers/Auth/NewPasswordController.php:34-61`
- Profile and password change — `resources/views/dashboard/settings.blade.php:11-75`
- The `auth` middleware group begins at `routes/web.php:75`; all public routes are declared before it
- Ownership is resolved through the space — `Testimonial::scopeOwnedBy()` at `app/Models/Testimonial.php:75-78`, `Widget::scopeOwnedBy()` at `app/Models/Widget.php:100-102`
- New accounts default to the Free plan — `User::booted()` `creating` hook

**Runtime verification:** all seven dashboard routes returned `302` with a `Location` of `http://127.0.0.1:8765/login` for a guest:

```
/dashboard               302
/dashboard/spaces        302
/dashboard/inbox         302
/dashboard/wall          302
/dashboard/widgets       302
/dashboard/billing       302
/dashboard/settings      302
```

**Remaining gap:** session-based authentication was not exercised at runtime, because no login was performed. Password reset is verified in source only.

---

### 3.2 Criterion 2 — Users can create Spaces with configurable fields, themes, and rating options

**Status:** Verified (source inspection + runtime field rendering)

**Evidence:**

- Create / edit / view / delete — `app/Http/Controllers/Dashboard/SpaceController.php:53-148`
- Unique slug generation with a 10-candidate collision loop — `app/Services/SpaceSlugGenerator.php:62-84`
- Theme picker with three previews — `resources/views/components/dashboard/theme-picker.blade.php:11-50`, backed by `app/Services/SpaceThemePreview.php:32-54`
- Field toggles and field order — `resources/views/components/dashboard/space-field-toggles.blade.php`, `resources/views/components/dashboard/field-order-list.blade.php`
- Rating toggle — `resources/views/components/dashboard/space-form.blade.php:95-108`
- Per-field enabled/required resolution — `Space::isFieldEnabled()` / `isFieldRequired()` at `app/Models/Space.php:124-144`
- `Name` and `Email` always enabled and always required — `app/Models/Space.php:125-144`
- Theme cast — `app/Models/Space.php:44-55`

**Runtime verification:** field rendering differs correctly per space.

| Space slug | `rating` input | `company_name` input |
|---|---|---|
| `abc` | present | present |
| `ducimus-quae-vijc1` | absent | present |

**Remaining gap:** none material. The `company_name` input is present on both spaces because both have it enabled; the rating toggle difference is the discriminating case and it behaved correctly.

---

### 3.3 Criterion 3 — Public customers can submit testimonials without accounts

**Status:** Verified (source inspection + runtime form rendering)

**Evidence:**

- Public routes are declared outside the `auth` group — `routes/web.php:20-41`
- Anonymous submission is enforced in the request — `app/Http/Requests/Public/StoreTestimonialRequest.php:31-34`
- Validation lives in the form request — `StoreTestimonialRequest.php:46-52`
- Anti-spam: honeypot `website` plus the encrypted single-use `kudosly_submission_token` — `app/Services/TestimonialSubmissionGuard.php`
- Rate limiting — `app/Providers/AppServiceProvider.php:33-35` (`Limit::perHour(5)->by($request->ip().'|'.$request->route('slug'))`)

**Runtime verification:** `GET /s/abc` returned `200` and rendered the complete form:

```
name, email, body, rating, consent, company_name, social_url, photo,
website (honeypot), kudosly_submission_token, _token
```

**Remaining gap:** the POST itself was not exercised, because it would create a testimonial. The success page was therefore verified in source only.

---

### 3.4 Criterion 4 — Consent and moderation control public visibility

**Status:** Verified (source inspection + runtime wall rendering)

**Evidence:**

- Wall scope requires all four flags — `app/Models/Testimonial.php:184-190`
- Widget scope requires three flags (no heart) — `app/Models/Testimonial.php:212-217`
- The rule is held once in `TestimonialVisibility`; the model methods delegate to it — `app/Models/Testimonial.php:227-281`
- Moderation flags are absent from `#[Fillable]` — `app/Models/Testimonial.php:21-29`
- Owner edits may change only `body` and `rating` — `app/Http/Requests/Dashboard/UpdateTestimonialRequest.php:31-33`
- Flags are written by direct attribute assignment in the controller, never from input — `app/Http/Controllers/Dashboard/TestimonialController.php:109-110, 125-126, 141-142, 157-158`
- Flags default to `false` in the migration — `database/migrations/2026_11_20_000005_create_testimonials_table.php`

**Runtime verification:** `GET /wall/abc` returned `200` and rendered "Test Customer" — the seeded testimonial with `approved=1, hidden=0, favorite=1, hearted=1, consented=1`. The other seeded testimonial (`approved=0, consented=0`) was not rendered.

**Remaining gap:** the unconsented-but-approved case (which must remain private) is covered by source and the test suite, not by a runtime observation.

---

### 3.5 Criterion 5 — Owners can manage testimonials and Wall of Love selections

**Status:** Verified (source inspection)

**Evidence:**

All six required actions exist, each with its own route, controller method, and policy ability:

| Action | Location |
|---|---|
| Approve | `app/Http/Controllers/Dashboard/TestimonialController.php:105-116` |
| Hide / Unhide | `TestimonialController.php:121-132` |
| Favorite / Unfavorite | `TestimonialController.php:137-148` |
| Add / Remove from Wall of Love | `TestimonialController.php:153-164` |
| Edit | `TestimonialController.php:91-100` |
| Delete with confirmation | `TestimonialController.php:174-190`; confirmation dialog at `resources/views/dashboard/inbox.blade.php:231-245` |

Supporting behaviour:

- Searchable — `Testimonial::scopeSearch()` at `app/Models/Testimonial.php:85-100` (name, company, body)
- Paginated — `TestimonialController::PER_PAGE = 25` at `:22`, applied at `:60`
- Favorites first — `Testimonial::scopeFavoritesFirst()` at `app/Models/Testimonial.php:170-173`, applied at `TestimonialController.php:54`
- Filters — status, rating, space; `Testimonial::scopeWithStatus()` `:129-139`, `scopeWithRating()` `:149-158`, `scopeForSpace()` `:111-118`
- A space filter naming a space the account does not own empties the inbox rather than falling back to all testimonials — `TestimonialController.php:56-58`
- Cross-account access returns 404, not 403 — `TestimonialController.php:213`, `UpdateTestimonialRequest::failedAuthorization()` at `:78-81`
- Delete removes the private photo, swallowing a failure with `report()` — `TestimonialController.php:182-186`

**Remaining gap:** the authenticated inbox was not visited at runtime, so the moderation actions and the confirmation dialog were not exercised in a browser. Source and tests only.

---

### 3.6 Criterion 6 — External websites can embed approved testimonials

**Status:** Verified (source inspection + runtime widget rendering)

**Evidence:**

- Widget routes — `routes/web.php:43-47`
- All six PRD §9 features in `app/Livewire/Dashboard/WidgetBuilder.php`:
  - Masonry and Carousel layouts — `:67`, enum `WidgetLayout`
  - Live preview — `:212`, `resources/views/dashboard/widgets/partials/preview-card.blade.php`
  - Light / Dark mode — `:69`, enum `WidgetAppearance`
  - Background colour — `:77`, validated as a hex colour at `:301-306`
  - Animation toggle — `:89`
  - Display limit — `:100`, bounded to 1–20 at `:264-270`
- Copyable iframe and JavaScript embed snippets — `resources/views/dashboard/widgets/`
- Style isolation via iframe plus scoped `.klw-*` CSS (not shadow DOM)
- `public/embed.js` is hand-written and dependency-free — served at 3,205 bytes, `application/javascript`
- Only approved, consented, visible testimonials render — `Widget::scopeVisiblePublicly()` usage in `app/Http/Controllers/Public/WidgetController.php`

**Runtime verification:** both seeded widget public IDs returned `200`:

```
/widget/bNDJcAvhjrIeVU8JyHOpjIvadQH5BW6y   200
/widget/Y2ffg4A9krTG4QZZKONbmYN81kvke8Lg   200
/embed.js                                    200  (3205 bytes)
```

The widget body contained the testimonial name and **no email address**.

**Remaining gap:** embedding into a real external host page was not browser-verified.

---

### 3.7 Criterion 7 — Analytics display accurate database-driven counts and trends

**Status:** Verified (source inspection)

**Evidence:**

All seven PRD §6 elements are present:

| PRD element | Location |
|---|---|
| Total Spaces | `app/Http/Controllers/Dashboard/OverviewController.php:58`; `resources/views/dashboard/overview.blade.php:23-29` |
| Total Testimonials | `OverviewController.php:60`; `overview.blade.php:30-36` |
| Unique Customers | `OverviewController.php:61`; `overview.blade.php:37-43` — counted with `count(distinct email)` in `app/Services/DashboardStatistics.php` |
| Testimonials This Month | `OverviewController.php:62`; `overview.blade.php:44-50` |
| Average Rating | `OverviewController.php:63-64`; `overview.blade.php:44-50` |
| Current Plan and Usage | `OverviewController.php:39,55-56`; `resources/views/components/dashboard/plan-usage-card.blade.php` |
| Recent Submissions | `OverviewController.php:70,111-120`; `overview.blade.php:144` |

Chart and filters:

- Daily submission trend — `app/Services/SubmissionTrend.php`
- 7-day, 30-day, 90-day filters — `overview.blade.php:67-69`
- The requested range is validated against the known ranges before it reaches the query, and falls back to the default — `OverviewController.php:81-94`

Free-plan upgrade banner:

- `resources/views/components/dashboard/upgrade-banner.blade.php` — rendered at `overview.blade.php:7-10`
- It distinguishes "at limit" from "under limit" and names the Pro allowance and price

**Remaining gap:** the chart renders client-side through Chart.js and was not exercised in a browser.

---

### 3.8 Criterion 8 — Free users are limited to 3 Spaces and 100 testimonials per Space

**Status:** Verified (source inspection + seeded plan row)

**Evidence:**

- Enforcement lives in one place — `app/Services/PlanEntitlement.php:50-55` (`canCreateSpace`, `assertCanCreateSpace`, `canSubmitTestimonial`, `assertCanSubmitTestimonial`), throwing `PlanLimitExceeded`
- The space creation path asserts before doing anything — `app/Http/Controllers/Dashboard/SpaceController.php:45,57`
- The limit is rendered from the plan, not hard-coded — `app/Models/User.php` (`spacesLimit()`, `testimonialsLimit()`)
- The limit is read from the plan row, which the seeded `free` row confirms — `database/seeders/PlanSeeder.php`
- Development database row: `free` → `max_spaces = 3`, `max_testimonials_per_space = 100`
- The over-limit exception redirects with an error — `bootstrap/app.php`

**Remaining gap:** the rejection path was not triggered at runtime, because that would require creating spaces past the limit.

---

### 3.9 Criterion 9 — Pro users are limited to 25 Spaces and 1,000 testimonials per Space

**Status:** Verified (source inspection + seeded plan row)

**Evidence:**

- Same single enforcement path — `app/Services/PlanEntitlement.php:50-55`
- Development database row: `pro` → `max_spaces = 25`, `max_testimonials_per_space = 1000`
- `PlanName` has exactly two cases, `Free` and `Pro`; no hard-coded tier check exists outside `PlanEntitlement`

**Remaining gap:** no Pro account exists in the development database, so the Pro entitlement path was not exercised end to end. It is covered by the test suite.

---

### 3.10 Criterion 10 — Pro costs exactly $999/month in the configured Stripe recurring price

**Status:** Partially implemented

**Evidence:**

- The amount is seeded as `99900` cents — `database/seeders/PlanSeeder.php:74`
- Development database row: `pro` → `price = 999.00`
- Display — `Plan::priceMonthly()` at `app/Models/Plan.php:60-63`; `resources/views/dashboard/billing.blade.php:104`
- Plan comparison table — `billing.blade.php:91-128`
- Price identity check — `PlanProvisioner::isProPrice()` at `app/Services/PlanProvisioner.php:195-206`, using `hash_equals` against `Plan::stripePriceId()`

**Remaining gap — the significant one for this criterion:** the `$999` figure is **display-only metadata**. It is never cross-checked against the live Stripe Price. `Plan::stripePriceId()` is `null` in this environment, and the amount a Stripe Price actually carries is never compared to `price_monthly_cents`. A Stripe Price configured at a different amount would still be accepted by `isProPrice()` and would grant Pro while the application displays $999. The criterion asserts an exact price; the code asserts only a Price *identifier*.

---

### 3.11 Criterion 11 — Verified Stripe events update subscription entitlements

**Status:** Verified (source inspection)

**Evidence:**

- `PlanProvisioner` is the only writer of `plan_id` — `app/Services/PlanProvisioner.php:127`
- It is reached only from `app/Listeners/HandleStripeSubscriptionEvent.php`, registered on `WebhookHandled` in `app/Providers/AppServiceProvider.php::boot()`
- The webhook route is fail-closed — `AppServiceProvider::configureWebhookRoute()` runs in `register()`, before Cashier registers its own routes, and calls `Cashier::ignoreRoutes()` when no secret is configured
- The listener independently refuses unverified payloads — `HandleStripeSubscriptionEvent.php:26-30`
- Status-to-plan mapping — `PlanProvisioner.php:149-171`
- Idempotency — the plan is written with `forceFill()` and only when it differs from the current plan — `PlanProvisioner.php:109-128`
- Cashier v16 has no `customers` table; `users.stripe_id` is `nullable()->index()` and deliberately not unique

**Remaining gap:** no live Stripe webhook was replayed, and `stripe_price_id` is `null` on both plan rows in this environment, so the Pro path has never run against a real Price. Covered by the test suite.

---

### 3.12 Criterion 12 — Cancellations and payment failures are handled correctly

**Status:** Partially implemented

**Evidence:**

- Status-to-plan mapping — `PlanProvisioner.php:149-171`:
  - `canceled`, `incomplete`, `incomplete_expired` → Free
  - `active`, `trialing`, `past_due`, `unpaid` → Pro
  - anything else → `null`, leaving the plan unchanged
- Downgrade preserves data; no records are deleted on cancellation
- Subscription states are surfaced to the owner — `billing.blade.php:20-29` (`pastDue()`, `onGracePeriod()`, `ended()`, `onTrial()`)
- Customer Portal route and controller are present — `routes/web.php`, `app/Http/Controllers/Dashboard/BillingController.php`
- Raw card details are never stored; Stripe handles card collection

**Remaining gap:**

- There is **no `invoice.payment_failed` handler**, and `config('cashier.payment_notification')` is unset, so a failed payment produces no notification.
- The `past_due` → Pro mapping keeps Pro entitlement after a failed payment. The billing page states that "Pro access continues until Stripe cancels the subscription", so the intent is deliberate and documented in the UI — but the PRD's "payment failures are handled correctly" is only half-covered: the failure is displayed, not acted upon.
- An unused duplicate `stripe.webhook_secret` remains at `config/services.php:41`.

---

### 3.13 Criterion 13 — Critical application flows have automated tests

**Status:** Verified

**Evidence:**

```
php artisan test
Tests:    871 passed (2006 assertions)
```

The suite covers the criteria named in the PRD: plan limits and entitlement, public visibility and wall eligibility, webhook handling and price identity, space slug generation, submission guarding and spam prevention, ownership isolation, and analytics.

**Remaining gap:** suite breadth was not measured, and the `Feature` suite depends on `seedPlans()` being called in `beforeEach()` — a Feature test that creates a user without it fails on the missing plan.

---

## 4. Section-by-section findings

### §3 Authentication and Accounts — Verified

Register, login, logout, reset password, and profile management are all implemented. Every new account starts on the Free plan via the `User::booted()` `creating` hook. Each account owns its own spaces, testimonials, widgets, and billing information, with ownership resolved through the space. Customers submitting testimonials do not need an account — the public routes sit outside the `auth` group.

### §4 Space Management — Verified

Create, edit, view, and delete are implemented. Each space carries a name, a unique public slug, title, subtitle, request, a rating toggle, field configuration, and one of three themes with previews. `Name` and `Email` are always enabled and always required; `Company Name`, `Social Profile URL`, and `Profile Photo` are each independently enabled and markable required. The success page shows a copyable public URL: `resources/views/dashboard/spaces/created.blade.php:14` renders the `x-dashboard.copyable-url` component with `$space->publicPath()`, which is `/s/{slug}` (`app/Models/Space.php:98-101`).

### §5 Public Testimonial Collection — Verified

The page shows the space title, subtitle, request, configured fields, a testimonial text area, an optional rating, and an unchecked public-sharing consent checkbox. `body`, `name`, and `email` are required, and all configured validation is applied server-side in `StoreTestimonialRequest`. Submissions are initially pending and private; a customer may submit without consenting, and such testimonials remain private.

The success message matches the PRD verbatim — `resources/views/public/spaces/thanks.blade.php:19-21`:

> You're officially awesome! 🎉
> Your kind words just made someone's day.

Rate limiting, file upload validation, and spam prevention are all in place. The consent checkbox was confirmed **unchecked** in the runtime markup.

### §6 Dashboard and Analytics — Verified in source

All seven required elements and the 7/30/90-day chart filters are present, and free users see a contextual upgrade banner. See §3.7 for the full evidence table.

### §7 Testimonial Inbox — Verified in source

Searchable, paginated at 25 per page, showing customer information, testimonial text, rating, date, space, and moderation status. All six actions are supported. Favorites appear first. Public visibility requires approval, a non-hidden state, and explicit sharing consent.

### §8 Wall of Love — Verified

Each space has a public wall at `/wall/{slug}`. Testimonials marked with a heart are shown when they satisfy the visibility requirements. The customer name, photo when available, testimonial text, and optional rating are displayed. Customer email addresses are never revealed — the public attribute list at `app/Models/Testimonial.php:288-300` excludes `email`, and a regex scan of the live `/wall/abc` page body found **zero** email addresses.

### §9 Embeddable Widgets — Verified

All six features are implemented, with copyable iframe and JavaScript snippets. Widgets are served with scoped CSS that isolates them from the host page. Only approved, consented, visible testimonials render.

### §10 Plans and Pricing — Limits verified; price cross-check missing

The Free and Pro limits match the PRD exactly, and the seeded plan rows confirm it. The $999/month figure is displayed but not verified against a live Stripe Price — see §3.10.

### §11 Billing and Stripe — Partially implemented

The billing page carries plan comparison, current subscription status, usage, and an Upgrade button. Pro access is granted only by a verified webhook, never by a browser redirect. Repeated webhook deliveries are safe. Two gaps remain: the missing Stripe Price amount cross-check, and the absent `invoice.payment_failed` handler — see §3.10 and §3.12.

### §12 Core Data Models — Verified

Migrations, relationships, and indexes exist for all seven entities. Uploaded profile photos use safe storage paths on a private disk and are served back through routes that re-check visibility.

### §13 Design Requirements — Verified in source

A responsive dashboard with sidebar navigation covering all seven PRD sections — `app/Livewire/Dashboard/Navigation.php:15-63` provides Overview, Spaces, Inbox, Wall of Love, Widgets, Billing, and Settings. Overview cards and a chart, the spaces listing and creation workflow, the inbox, wall management, the widget builder, billing, and account settings are all present. Typography, shadows, empty states, responsive breakpoints, and consistent `x-ui/*` components are used throughout. Branding is original; no Testimonial.to assets are used.

### §14 Security Requirements — Verified in source, partially at runtime

| Requirement | Status | Evidence |
|---|---|---|
| Authenticated dashboard routes | Verified | `routes/web.php:75`; runtime 302 for all seven |
| Owner-based authorization | Verified | 404-not-403 in `SpaceController.php:159`, `TestimonialController.php:213`, `SpaceRequest`, `UpdateTestimonialRequest` |
| CSRF protection | Verified (source) | Laravel 13 default `VerifyCsrfToken` middleware on the web group |
| Input and upload validation | Verified | `StoreTestimonialRequest.php:46-52`; photo `image`, `mimes:jpg,jpeg,png,webp`, `max:2048` |
| Rate limiting | Verified | `AppServiceProvider.php:29-35` — login 5/min, submission 5/hour |
| Safe output escaping | Verified | Blade `{{ }}` throughout; no `{!! !!}` in the audited views |
| Private customer data protection | Verified | Private disk; no email in wall or widget output (runtime confirmed) |
| Verified Stripe webhook signatures | Verified | Fail-closed at route level and listener level |
| Secure environment variables | Verified | `.env` is gitignored; only non-secret keys were read |
| Automated tests for plan limits and public visibility | Verified | 871 passing tests |

---

## 5. Critical findings

### 5.1 The $999 price is never verified against Stripe

**Severity:** should-fix
**Affects:** §17 criterion 10, §10

`PlanProvisioner::isProPrice()` (`app/Services/PlanProvisioner.php:195-206`) compares only the Stripe Price *identifier* against `Plan::stripePriceId()`. The amount is never compared against `Plan::price_monthly_cents`.

A Stripe Price configured at $499 would grant Pro, while the dashboard and billing page continue to display $999. The PRD criterion — "Pro costs exactly $999/month in the configured Stripe recurring price" — is currently asserted by display rather than enforced.

**Suggested fix:** on webhook receipt, read the Price amount from the subscription and refuse to grant Pro when it does not match `price_monthly_cents`.

### 5.2 No `invoice.payment_failed` handler

**Severity:** should-fix
**Affects:** §17 criterion 12, §11

`config('cashier.payment_notification')` is unset and no listener handles `invoice.payment_failed`, so a failed payment produces no notification. The `past_due` → Pro mapping retains Pro entitlement; the billing page explains that this is deliberate ("Pro access continues until Stripe cancels the subscription"), but a failure is displayed and not acted upon.

**Suggested fix:** handle `invoice.payment_failed`, record the event, and make the entitlement decision for `past_due` explicit rather than incidental.

### 5.3 No Pro account exists in the development environment

**Severity:** observation
**Affects:** §17 criteria 9, 10, 11

All five seeded users are on the Free plan, and `stripe_price_id` is `null` on both plan rows. The Pro entitlement path, the Upgrade button, the checkout session, and the webhook-driven upgrade have therefore never been exercised end to end outside the test suite.

**Suggested fix:** add a local-only seed state that creates a Pro account and a configured Price ID, so the Pro paths can be exercised manually.

---

## 6. Observations

These are not gaps against the PRD. They are recorded for future reference.

- The overview "Current Plan and Usage" card shows spaces usage only, not a testimonial quota. The PRD says "usage" without specifying, so this is a reasonable reading, but a testimonial counter would make the 100-per-space limit visible on the overview.
- `config/services.php:41` carries an unused duplicate `stripe.webhook_secret` alongside the Cashier configuration.
- Delete swallows a photo-deletion failure with `report($exception)` (`TestimonialController.php:182-186`). This is deliberate and documented in the code — an orphaned file is untidy but far less bad than a deleted testimonial still being visible — and is not a defect.
- A space filter naming a space the account does not own empties the inbox rather than falling back to every testimonial (`TestimonialController.php:56-58`). This is correct and worth preserving.

---

## 7. Limitations of this audit

- **No authenticated page was visited.** Per the audit instruction, authenticated behaviour is assessed from source code and the existing test suite only. The dashboard's rendering, the inbox filters, pagination, the moderation actions, and the delete confirmation dialog are unverified at runtime.
- **No POST request was issued at all.** Submission, moderation, and webhook paths are unverified at runtime. The success page was verified in source only; the runtime check confirmed that the form renders, not that a submission redirects to it.
- **The Stripe integration was never contacted.** No live Price, no Customer Portal configuration, and no webhook delivery were exercised.
- **The chart and the external-host embed were not rendered in a browser.** Chart.js and the cross-origin embed are verified by source and asset presence only.
- **The test suite run used the test database.** `RefreshDatabase` applies to the `Feature` suite only, so the 871-passing run did not touch the development SQLite file.
- **Coverage was not measured.** The suite passes, but its breadth against the PRD was not quantified.
- Two intermediate probe scripts were abandoned rather than repaired once they were found to rest on mistaken assumptions; the findings they were meant to produce are covered by the source and runtime evidence above.

---

## 8. Items still requiring manual verification

1. Register → create a space → submit a testimonial publicly → moderate it → confirm it appears on the wall and in the widget. Requires a real account and a submission.
2. Login, logout, and the full password-reset round trip.
3. The Upgrade to Pro flow against a real Stripe Price, including the Customer Portal.
4. A live Stripe webhook delivery granting, downgrading, and cancelling a subscription.
5. The chart rendering at each of the three range filters.
6. A widget embedded in a page on a different origin, checking style isolation and responsiveness.
7. A photo upload at the 2048 KB boundary and with a disallowed MIME type.
8. The Free-plan limit being reached, to confirm the enforcement redirect.

---

## 9. Overall assessment

The application implements the PRD faithfully. Eleven of the thirteen acceptance criteria are verified, including every requirement that touches authorization, data ownership, public visibility, consent handling, or private customer data. The two partially implemented criteria are both in the billing and Stripe area and are narrow, well-localized gaps rather than structural omissions: the $999 price is displayed but not enforced against the live Stripe Price, and a failed payment is surfaced but not acted upon.

The codebase shows deliberate adherence to its own conventions throughout: 404-not-403 authorization, ownership resolved through the space rather than a redundant foreign key, server-generated values that cannot be supplied by the browser, moderation flags that are not mass-assignable, and entitlement enforced in exactly one place. No critical security, authorization, or data-model defect was found.

The highest-value next step is to close the two billing gaps, followed by creating a local Pro state so the Pro paths can be exercised manually rather than only in tests.
