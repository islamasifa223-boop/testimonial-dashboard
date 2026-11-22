<?php

namespace App\Providers;

use App\Listeners\HandleStripeSubscriptionEvent;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookHandled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->configureWebhookRoute();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->string('email')->lower()->toString().'|'.$request->ip());
        });

        RateLimiter::for('testimonial-submission', function (Request $request) {
            return Limit::perHour(5)->by($request->ip().'|'.$request->route('slug'));
        });

        Event::listen(WebhookHandled::class, HandleStripeSubscriptionEvent::class);
    }

    /**
     * Fail closed when no Stripe webhook secret is configured.
     *
     * Cashier only applies its signature verification when a webhook secret
     * is present. Without one it would accept unsigned requests, so the
     * webhook route is not registered at all and the endpoint does not exist.
     *
     * This runs during registration so the decision is made before Cashier
     * registers its own routes.
     */
    protected function configureWebhookRoute(): void
    {
        if (blank(config('cashier.webhook.secret'))) {
            Cashier::ignoreRoutes();

            return;
        }

        // The flag is static, so it is set explicitly in both directions to
        // keep this method idempotent and free of state left behind by an
        // earlier call.
        Cashier::$registersRoutes = true;
    }
}
