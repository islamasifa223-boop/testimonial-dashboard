# Deployment Guide

This guide covers deploying Kudosly to a production server.

Every command in this guide is written for a Unix-like server running a production PHP application. Commands that only apply to your local machine are marked as such.

---

## 1. Requirements

- PHP 8.3 or newer
- Composer 2
- Node.js 20 and npm 10 (only needed to build the frontend assets)
- A MySQL server (recommended) or SQLite
- A Stripe account with a product and a recurring price

---

## 2. Environment configuration

Copy the example environment file and generate an application key:

```bash
cp .env.example .env
php artisan key:generate
```

The `.env.example` file contains **local development defaults only**. It must never be used as-is in production. Set the production values on the server:

```dotenv
APP_NAME=Kudosly
APP_ENV=production
APP_DEBUG=false
APP_KEY=
APP_URL=https://your-domain.com

APP_PREVIOUS_KEYS=
```

`APP_DEBUG=false` is important. With debug mode enabled, Laravel exposes environment values and stack traces to anyone who triggers an error.

If the application was already running under a previous key, add the old key to `APP_PREVIOUS_KEYS` so existing sessions and cookies keep working:

```dotenv
APP_PREVIOUS_KEYS=base64:old-key-here
```

Set the database connection:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kudosly
DB_USERNAME=kudosly
DB_PASSWORD=your-password
```

Set the mail and Stripe values:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="hello@your-domain.com"
MAIL_FROM_NAME="${APP_NAME}"

STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...

CASHIER_CURRENCY=usd
CASHIER_CURRENCY_LOCALE=en
```

`STRIPE_KEY` is the Stripe **publishable** key (`pk_...`) and `STRIPE_SECRET` is the Stripe **secret** key (`sk_...`). The upgrade flow redirects the customer to Stripe-hosted Checkout, so the publishable key is not used by any inline script on the site, but it is still part of the standard Cashier configuration and must be set to the key for the matching mode.

### Cache, session, and queue

```dotenv
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

The default configuration works with the `database` driver for all three, so no additional services (Redis, Memcached) are required.

---

## 3. Installation

```bash
git clone <your-repository-url> /var/www/kudosly
cd /var/www/kudosly

composer install --prefer-dist --no-interaction --no-progress --optimize-autoloader
npm ci
npm run build
```

`npm ci` installs the exact dependency versions locked in `package-lock.json`. `npm run build` compiles the CSS and JavaScript into `public/build`. Once the build has run, Node is not needed on the server.

Set the document root of the site to the `public` directory. The rest of the project must not be web-accessible.

### Permissions

Keep the project owned by the deployment user and give the web-server group write access only to the directories that must be writable at runtime. Do not give the web server ownership of the source code, the `.env` file, or `vendor/`.

Replace the placeholders below with the actual values for your server:

- `deploy` is the username that runs the deployment commands.
- `www-data` is the group the web server runs as. On Debian and Ubuntu that is `www-data`; on RHEL, AlmaLinux, and Rocky Linux it is usually `nginx` or `apache`. Check with `ps -eo group,comm | grep -E 'nginx|apache|php-fpm'` or look at the `group` in your web server's service file.

```bash
sudo chown -R deploy:www-data /var/www/kudosly
sudo find /var/www/kudosly/storage /var/www/kudosly/bootstrap/cache -type d -exec chmod 775 {} \;
sudo find /var/www/kudosly/storage /var/www/kudosly/bootstrap/cache -type f -exec chmod 664 {} \;
sudo usermod -aG www-data deploy
```

The `chown` makes the deployment user the owner of every file, with the web-server group as the group owner. The two `find` commands give the directories `775` and the files `664`, so the web server can create and write files inside `storage` and `bootstrap/cache` while the rest of the project stays writable only by the deployment user.

The `usermod` command adds the deployment user to the web-server group, so that user can still write to the project after the group ownership changes. The deployment user must log out and log back in for the new group membership to take effect.

---

## 4. Database

### MySQL

Create the database and a dedicated user:

```sql
CREATE DATABASE kudosly CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'kudosly'@'localhost' IDENTIFIED BY 'your-password';
GRANT ALL PRIVILEGES ON kudosly.* TO 'kudosly'@'localhost';
FLUSH PRIVILEGES;
```

### SQLite

Set the connection instead:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/kudosly/database/database.sqlite
```

Create the file first:

```bash
touch database/database.sqlite
```

Both the MySQL and SQLite drivers are configured and supported by the application. The CI workflow currently tests against SQLite only, so if you deploy on MySQL, run the migrations against the exact MySQL version your production server uses and confirm they complete cleanly before going live.

### Run the migrations

```bash
php artisan migrate --force
```

### Seed the plans

```bash
php artisan db:seed --class=PlanSeeder --force
```

This ensures the Free and Pro plan records exist on a fresh deployment. The seeder is safe to rerun: it creates the two plans only if they are missing and never overwrites an already stored Stripe Price ID, so running it after the Price IDs have been configured will not break anything.

---

## 5. Stripe configuration

### Create the product and price

1. Sign in to the Stripe dashboard.
2. Create a recurring **monthly** product named "Pro".
3. Set the price. The application reads the amount from the `plans` table, so the Stripe price amount and the database amount must match.
4. Copy the Price ID, which looks like `price_1AbCdEfGhIjKlMnOpQrStUvW`.

### Attach the Price ID to the Pro plan

The Pro plan row holds the Price ID that the application trusts. Set it with:

```bash
php artisan tinker
```

Then run:

```php
App\Models\Plan::where('name', App\Enums\PlanName::Pro)->first()->update([
    'stripe_price_id' => 'price_1AbCdEfGhIjKlMnOpQrStUvW',
]);
```

The `name` column is cast to the `PlanName` enum, so the query works with the enum (`App\Enums\PlanName::Pro`), with the backing value (`'pro'`), and with `App\Enums\PlanName::Pro->value`. The `stripe_price_id` column is read back through the model's `stripePriceId()` accessor, which returns `null` when the column is empty.

Never run the `PlanSeeder` expecting it to fill the Price ID in. It leaves the column alone on purpose, so that redeploying the application cannot silently break live subscriptions by pointing them at a different Price.

### Configure the Cashier keys

Put the Stripe keys in `.env`:

```dotenv
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
CASHIER_CURRENCY=usd
```

---

## 6. Webhooks

### Which events Cashier handles

Cashier registers a handler for eight event types. The application relies on the subscription events; the others keep Cashier's own records in sync.

**`customer.subscription.created`**

Creates the local `subscriptions` row for the customer, along with its `subscription_items` rows, recording the status, Price ID, quantity, and trial end date.

**`customer.subscription.updated`**

Updates the local `subscriptions` row: status, Price ID, quantity, trial end date, and cancellation date. If the status is `incomplete_expired` it deletes the local subscription and its items instead.

**`customer.subscription.deleted`**

Marks the matching local `subscriptions` row as cancelled.

**`customer.updated`**

Refreshes the customer's default payment method on the `users` row (`pm_type`, `pm_last_four`) from Stripe.

**`customer.deleted`**

Marks all of the customer's local subscriptions as cancelled and clears `stripe_id`, `trial_ends_at`, `pm_type`, and `pm_last_four` on the `users` row.

**`payment_method.automatically_updated`**

Refreshes the customer's default payment method on the `users` row, same as `customer.updated`.

**`invoice.payment_succeeded`**

Only acts on invoices that belong to a Stripe-hosted session checkout. It clears the `is_on_session_checkout` metadata flag on the Stripe subscription. It does not write any invoice record, because the application does not have an invoice table.

**`invoice.payment_action_required`**

Only acts when `CASHIER_PAYMENT_NOTIFICATION` is configured, and only for invoices that are not part of a session checkout. It then notifies the customer that payment requires action. This project does not configure that notification, so the handler is effectively a no-op here.

The last two handlers do not touch plan entitlements and, in this project, do not change any local record either. The custom listener that changes a user's plan only reacts to the `customer.subscription.*` events.

### Which events grant or revoke the Pro plan

The application's plan changes are driven by the subscription status and by the Price the subscription is billed against.

A subscription only ever affects a plan when **both** of these hold:

1. The subscription's first item is billed against the Price ID stored in the Pro plan's `stripe_price_id` column. A subscription on any other Price never changes anybody's plan.
2. The account that owns the subscription is found by matching the subscription's Stripe customer ID against the `stripe_id` column on users.

The status then decides the outcome:

| Subscription status | Plan granted |
|---------------------|--------------|
| `active`, `trialing`, `past_due`, `unpaid` | Pro |
| `canceled`, `incomplete`, `incomplete_expired` | Free |
| `paused`, or any status not listed above | Unchanged |

A few details that matter in practice:

- `past_due` and `unpaid` mean a *renewal* failed on a subscription that was already paid for, so the account keeps Pro until Stripe cancels it.
- `incomplete` means the *first* payment attempt failed, so the account has never paid. It is granted no plan.
- `customer.subscription.deleted` is authoritative on its own. The status carried by that event still describes the subscription as it was before the deletion, so the status is ignored and the account returns to Free.

### Register the webhook endpoint

In the Stripe dashboard, add a webhook endpoint pointing at:

```text
https://your-domain.com/stripe/webhook
```

Subscribe it to these events:

- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `customer.updated`
- `customer.deleted`
- `payment_method.automatically_updated`
- `invoice.payment_succeeded`
- `invoice.payment_action_required`

The same event list applies in Test Mode and in Live Mode. Only the keys and the endpoint URL differ.

Copy the signing secret that Stripe shows after creating the endpoint and put it in `.env`:

```dotenv
STRIPE_WEBHOOK_SECRET=whsec_...
```

The application refuses to register the webhook route at all when no webhook secret is configured, so the endpoint only exists once this value is set.

### Local development with the Stripe CLI

```bash
stripe listen --forward-to localhost:8000/stripe/webhook
```

The CLI prints a `whsec_...` secret for the session. Put it in your local `.env` as `STRIPE_WEBHOOK_SECRET` and restart the local server.

---

## 7. Stripe Test Mode setup and verification

Do all payment-flow and test-card scenarios in this section using **Test Mode** keys. Use **Live Mode** only for verifying the production API keys, the production Price ID, the webhook endpoint, and the webhook signing secret. Never use test cards with Live Mode keys.

Test Mode and Live Mode are completely separate: they have separate prices, separate customers, separate subscriptions, and separate webhook endpoints. Nothing you do in Test Mode affects a live subscription.

### Test Mode keys

1. Toggle **Test mode** on in the Stripe dashboard.
2. Open **Developers → API keys**.
3. Copy the **Test Mode** publishable key (`pk_test_...`) and secret key (`sk_test_...`).
4. Put them in your local `.env`:

```dotenv
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
```

5. Copy the **Test Mode** webhook signing secret from the endpoint you created in Test Mode, and put it in your local `.env`:

```dotenv
STRIPE_WEBHOOK_SECRET=whsec_...
```

Use the Test Mode Price ID in your local database. The Test Mode and Live Mode products have different Price IDs, and the application only recognises the one stored in the `plans` table.

### Live keys

The Live Mode keys are only ever set on the production server, and only after the deployment is otherwise complete:

```dotenv
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

`STRIPE_WEBHOOK_SECRET` for production must come from the **Live Mode** webhook endpoint. A Test Mode secret on a production server will fail signature verification and every webhook will be rejected.

### Test card numbers

These are Stripe Test Mode cards. They never work against live keys.

| Scenario | Card number | Result |
|----------|-------------|--------|
| Successful payment | `4242 4242 4242 4242` | The payment succeeds and the subscription becomes `active` |
| Declined payment | `4000 0000 0000 9995` | The payment is declined and the subscription stays `incomplete` |
| 3-D Secure authentication required | `4000 0025 0000 3155` | Stripe shows a test authentication window. The subscription stays `incomplete` until the challenge succeeds |

Use any future expiry date, any CVC, and any postal code with these cards.

### Verification checklist

Run through this list before you consider the deployment done. Use the right mode for each part of it:

- Run every test-card scenario, including the declined-payment and 3-D Secure checks, in **Test Mode only**. Test cards never work against live keys.
- In **Live Mode**, verify only the live API keys, the live Price ID, the webhook endpoint, and the webhook signing secret.
- Do **not** run the test-card scenarios in Live Mode. If a live payment-flow check is necessary, use a controlled real transaction and refund it afterwards.

- [ ] The Stripe keys in `.env` are the ones for the environment you are testing. `pk_test_`/`sk_test_` on local and staging, `pk_live_`/`sk_live_` on production.
- [ ] The webhook endpoint exists in the same Stripe mode as the keys and subscribes to the eight events listed above.
- [ ] `STRIPE_WEBHOOK_SECRET` in `.env` came from that endpoint, not from the other mode.
- [ ] The Price ID stored on the Pro plan is the one from the same mode as the keys.
- [ ] A **new Free account** can open the upgrade checkout and reaches the Stripe Checkout page.
- [ ] A successful payment with `4242 4242 4242 4242` completes the checkout, Stripe emits `customer.subscription.updated` with status `active`, and the account is on the Pro plan. This check is a Test Mode scenario.
- [ ] A declined card on a **new Free account** leaves the account on the Free plan. Use a fresh account for this check: the upgrade action refuses to open a checkout for an account that is already on Pro, so an account that has already upgraded never reaches Stripe.

  Use the declined card `4000 0000 0000 9995`. This check is a Test Mode scenario.

  Stripe still creates the subscription. With `payment_behavior` left at the Cashier default, a failed first payment produces a subscription in the `incomplete` status, and Stripe emits `customer.subscription.created` for it. The application grants no plan for `incomplete`, so the account stays on Free until the first invoice is actually paid.

  To confirm the upgrade path end to end, retry with a card that succeeds (or complete the 3-D Secure authentication on the first attempt). Stripe then moves the subscription to `active`, emits `customer.subscription.updated`, and the account is granted the Pro plan at that point.
- [ ] A card that requires authentication, `4000 0025 0000 3155`, also leaves the account on the Free plan while the subscription is `incomplete`. This check is a Test Mode scenario.

  Stripe shows a test authentication window. Failing the challenge keeps the subscription `incomplete` and the account on Free. Completing the challenge makes Stripe move the subscription to `active`, emit `customer.subscription.updated`, and the account is granted the Pro plan at that point.
- [ ] Cancelling the subscription in the Stripe dashboard moves the account back to the Free plan after the `customer.subscription.deleted` event is delivered.
- [ ] The webhook endpoint's delivery log in the Stripe dashboard shows the events arriving with a `200` response.
- [ ] Only the Free plan and the Pro plan exist in the `plans` table, and the Pro plan has the expected `stripe_price_id`.

---

## 8. Scheduled tasks and background jobs

The application defines **no scheduled tasks** and **no queued jobs**. There is no scheduler entry to register and no queue worker to keep running. `Plan` changes are applied synchronously while the Stripe webhook request is being handled, so the plan is correct as soon as the webhook returns.

A queue worker is only needed if queued work is added later. If you add it, run:

```bash
php artisan queue:work --tries=3
```

under a process supervisor.

---

## 9. Deployment checklist

Run these in order on the server:

```bash
git pull
composer install --prefer-dist --no-interaction --no-progress --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --class=PlanSeeder --force
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize
```

The `db:seed --class=PlanSeeder --force` step runs immediately after the migrations on purpose. It ensures the Free and Pro plan records exist on a fresh deployment. The seeder is safe to rerun and does not overwrite an already stored Stripe Price ID, so it will not undo the Stripe configuration you did in step 5.

If the server has no Node.js installed, build the assets locally and upload `public/build` with the rest of the release instead.

---

## 10. Web server configuration

### Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/kudosly/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Apache

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/kudosly/public

    <Directory /var/www/kudosly/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Uploads and other user files are stored on the **private** disk under `storage/app/private` and are served through authorized application routes. They are never exposed directly by the web server, so no symlink into `public/storage` is required.

---

## 11. TLS

Install TLS with Certbot and redirect all traffic to HTTPS:

```bash
sudo certbot --nginx -d your-domain.com
```

Afterwards set:

```dotenv
APP_URL=https://your-domain.com
```

and clear the cached config:

```bash
php artisan config:clear
```

---

## 12. Continuous integration

The repository contains a GitHub Actions workflow at `.github/workflows/ci.yml`. It runs on every push and pull request that targets the `main`, `master`, or `develop` branches, and it only reads the repository.

The workflow:

1. Sets up PHP 8.3 with the `intl`, `mbstring`, `pdo_sqlite`, and `sqlite3` extensions.
2. Sets up Node.js 20.
3. Installs the Composer and npm dependencies.
4. Builds the frontend assets with `npm run build`.
5. Checks the code style with `vendor/bin/pint --test`.
6. Runs the full test suite with `php artisan test`.

The test suite runs against an in-memory SQLite database, so no database service is needed in the workflow.

---

## 13. Backups and maintenance

Back up the database and the private storage directory.

### MySQL

```bash
mysqldump --single-transaction --routines --triggers kudosly > kudosly-$(date +%F).sql
```

### SQLite

Use the `sqlite3` backup command. It takes a consistent snapshot while the application is running, which a plain file copy does not guarantee:

```bash
sqlite3 /var/www/kudosly/database/database.sqlite ".backup '/var/backups/kudosly-$(date +%F).sqlite'"
```

### Private storage

In both cases, back up the private storage directory as well:

```bash
tar czf private-$(date +%F).tar.gz /var/www/kudosly/storage/app/private
```

---

## 14. Troubleshooting

### Webhooks are not being received

- Check the delivery log on the webhook endpoint in the Stripe dashboard.
- A `401` response means the signing secret in `STRIPE_WEBHOOK_SECRET` does not match the endpoint. Test Mode and Live Mode secrets are different.
- A `404` response means the route is not reachable, usually because the document root is not the `public` directory, or because no webhook secret is configured and the route was never registered.

### The plan does not change after a payment

- The subscription must be billed against the Price ID stored on the Pro plan. Check `stripe_price_id` in the `plans` table.
- The account must be found by `stripe_id`. Check that the user row has the Stripe customer ID on it.
- Check the application log for the webhook request:

```bash
tail -f storage/logs/laravel.log
```

### Everything looks broken after an environment change

Laravel caches configuration and routes. After editing `.env`:

```bash
php artisan optimize:clear
```

---