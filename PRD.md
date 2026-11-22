# Product Requirements Document (PRD)
# Kudosly — Testimonial Collection SaaS

**Version:** 1.1  
**Status:** Approved for phased implementation  
**Framework:** Laravel 13  
**Project:** testimonial-dashboard  
**Product Inspiration:** Testimonial.to

---

## 1. Product Overview

Kudosly is a minimal SaaS platform for collecting, managing, and displaying customer testimonials.

Registered users can create branded testimonial collection pages called Spaces, share public links, manage customer submissions, and publish approved testimonials using embeddable widgets.

## 2. Technology Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13, PHP |
| Frontend | Blade, Livewire, Tailwind CSS |
| Database | MySQL |
| Authentication | Laravel-compatible authentication |
| Payments | Stripe Checkout + Laravel Cashier |
| Charts | Chart.js |
| Storage | Laravel Filesystem |
| Background Jobs | Laravel Queues |

Use the existing Laravel project. Do not rebuild it in another framework.

## 3. Authentication and Accounts

Users can register, log in, log out, reset passwords, and manage their profiles.

Every new account starts on the Free plan.

Each account owns its own Spaces, testimonials, widgets, and billing information. Apply authorization policies to prevent cross-account access.

Customers submitting testimonials do not need accounts.

## 4. Space Management

Users can create, edit, view, and delete Spaces.

Each Space includes:

- Name and unique public slug
- Page title, subtitle, and testimonial request
- Optional 1–5 star rating toggle
- Collection form fields
- Predefined page theme

**Default fields:** Name and Email, both always required.

**Additional predefined fields:** Company Name, Social Profile URL, and Profile Photo. Each can be independently enabled and marked required.

**Themes:** Minimal Light, Modern Dark, and Soft Gradient, with previews.

After creating a Space, show a success page with a copyable public URL: `/s/{slug}`.

## 5. Public Testimonial Collection

Customers can open a Space URL and submit testimonials without logging in.

Show the Space title, subtitle, request, configured fields, testimonial text area, optional rating, and an unchecked public-sharing consent checkbox.

Testimonial text, name, and email are required. Apply all configured validation server-side.

Submissions are initially pending and private. A customer may submit without consenting to public display; such testimonials must remain private.

After submission, show:

"You're officially awesome! 🎉 Your kind words just made someone's day."

Apply rate limiting, file upload validation, and spam prevention.

## 6. Dashboard and Analytics

Display:

- Total Spaces
- Total Testimonials
- Unique Customers
- Testimonials This Month
- Average Rating, when available
- Current Plan and Usage
- Recent Submissions

Show daily submissions using a chart with 7-day, 30-day, and 90-day filters.

Free users see a contextual upgrade banner.

## 7. Testimonial Inbox

Provide a searchable, paginated inbox showing customer information, testimonial text, rating, date, Space, and moderation status.

Supported actions:

- Approve
- Hide / Unhide
- Favorite / Unfavorite
- Add / Remove from Wall of Love
- Edit
- Delete with confirmation

Favorites appear first in the inbox.

Public visibility requires approval, a nonhidden state, and explicit sharing consent.

## 8. Wall of Love

Each Space has a public Wall of Love page at `/wall/{slug}`.

Show testimonials marked with a heart icon, provided they satisfy public visibility requirements.

Display customer name, photo when available, testimonial text, and optional rating.

Never reveal customer email addresses.

## 9. Embeddable Widgets

Provide a widget builder with:

- Masonry and Carousel layouts
- Live preview
- Light / Dark mode
- Background color
- Animation toggle
- Testimonial display limit

Generate copyable iframe and JavaScript embed snippets.

Widgets must work on external websites, remain responsive, and isolate their styling from the host page.

Only approved, consented, visible testimonials may be rendered.

## 10. Plans and Pricing

| Feature | Free | Pro |
|---|---|---|
| Price | $0 | **$999/month** |
| Billing | Free | Monthly recurring |
| Spaces | 3 | 25 |
| Testimonials per Space | 100 | 1,000 |
| Dashboard Analytics | Included | Included |
| Wall of Love | Included | Included |
| Embed Widgets | Included | Included |
| Themes | Included | Included |

**Billing currency:** USD  
**Paid plan:** Pro  
**Recurring interval:** Monthly  
**Price:** $999.00 per month, before any applicable taxes  
**Stripe Price:** Recurring monthly USD price, configured via environment or application settings

Enforce plan limits on the server.

When users downgrade or cancel, preserve existing data, but block new Spaces or submissions that would exceed Free plan limits. Do not automatically delete existing records.

## 11. Billing and Stripe

Provide a Billing page with plan comparison, current subscription status, usage, and an Upgrade button.

### Subscription Flow

1. A Free user clicks Upgrade to Pro.
2. Laravel creates a Stripe Checkout session for the **$999/month recurring subscription**.
3. Stripe handles card collection and payment.
4. Stripe redirects to the application.
5. A verified webhook confirms and records subscription status.
6. The user's effective plan becomes Pro after successful verification.
7. The dashboard and Billing page reflect Pro entitlement.

Support subscription renewals, payment failures, cancellations, and subscription management using Stripe Customer Portal.

Use Laravel Cashier-supported subscription records and verified Stripe webhooks. Handle repeated webhook events safely.

Never store raw card details or grant Pro access based only on a successful browser redirect.

## 12. Core Data Models

Implement migrations, relationships, and appropriate indexes for:

- **User:** account identity, authentication, and Stripe customer reference
- **Plan:** name, pricing, Stripe price identifier, limits
- **Space:** owner, slug, title, subtitle, request, theme, rating setting
- **SpaceField:** configured predefined fields, enabled/required flags, order
- **Testimonial:** customer details, text, rating, consent, moderation, favorite, heart
- **Widget:** Space, public ID, layout, appearance settings
- **Subscription:** Laravel Cashier subscription records

Use safe storage paths for uploaded profile photos.

## 13. Design Requirements

Build a premium, responsive SaaS dashboard featuring:

- Sidebar navigation
- Overview cards and charts
- Spaces listing and creation workflow
- Testimonial inbox
- Wall of Love management
- Widget builder
- Billing
- Account settings

Use clean typography, subtle shadows, clear empty states, responsive layouts, and consistent components.

The design may be inspired by Testimonial.to, but must use original branding and UI assets.

## 14. Security Requirements

- Authenticated dashboard routes
- Owner-based authorization
- CSRF protection
- Input and upload validation
- Rate limiting
- Safe output escaping
- Private customer data protection
- Verified Stripe webhook signatures
- Secure environment variables
- Automated tests for plan limits and public visibility

## 15. Out of Scope

Exclude video recording, AI analysis, team collaboration, advanced CRM integrations, custom domains, native mobile apps, and additional subscription tiers.

The MVP focuses on text testimonials.

## 16. Implementation Phases

**Phase 1 — Foundation:** Audit the existing Laravel project; configure database, authentication, base UI, account ownership, and initial dashboard.

**Phase 2 — Spaces:** Space CRUD, custom fields, rating setting, themes, and public URLs.

**Phase 3 — Collection and Inbox:** Anonymous testimonial submissions, validation, consent, moderation, favorites, and Wall of Love.

**Phase 4 — Widgets and Analytics:** Embeddable masonry/carousel widgets, live preview, statistics, and charts.

**Phase 5 — Billing:** Free/Pro limits, $999/month Stripe subscriptions, webhooks, customer portal, and upgrades.

**Phase 6 — Quality:** Feature tests, authorization tests, payment flow tests, accessibility, responsive polish, and deployment readiness.

## 17. Acceptance Criteria

- [ ] Users can register and securely access their own dashboard.
- [ ] Users can create Spaces with configurable fields, themes, and rating options.
- [ ] Public customers can submit testimonials without accounts.
- [ ] Consent and moderation control public visibility.
- [ ] Owners can manage testimonials and Wall of Love selections.
- [ ] External websites can embed approved testimonials.
- [ ] Analytics display accurate database-driven counts and trends.
- [ ] Free users are limited to 3 Spaces and 100 testimonials per Space.
- [ ] Pro users are limited to 25 Spaces and 1,000 testimonials per Space.
- [ ] Pro costs exactly $999/month in the configured Stripe recurring price.
- [ ] Verified Stripe events update subscription entitlements.
- [ ] Cancellations and payment failures are handled correctly.
- [ ] Critical application flows have automated tests.

## 18. AI Coding Agent Rules

- Work only inside the existing Laravel project.
- Inspect existing code before changing files.
- Preserve Laravel 13 compatibility.
- Prefer Blade, Livewire, and standard Laravel conventions.
- Implement one approved phase at a time.
- Do not hardcode credentials.
- Request approval before installing new packages, changing environment configuration, or performing destructive operations.
- Write tests for implemented business rules.
- Report changed files, commands run, test results, and remaining issues after each phase.

**Delivery Goal:** A functional, minimal Laravel testimonial SaaS with public collection pages, dashboard management, embeds, analytics, and monthly Stripe subscriptions.