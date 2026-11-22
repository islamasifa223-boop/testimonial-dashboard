<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Dashboard\BillingController;
use App\Http\Controllers\Dashboard\OverviewController;
use App\Http\Controllers\Dashboard\PageController;
use App\Http\Controllers\Dashboard\SpaceController;
use App\Http\Controllers\Dashboard\TestimonialController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\SpaceController as PublicSpaceController;
use App\Http\Controllers\Public\TestimonialController as PublicTestimonialController;
use App\Http\Controllers\Public\WallController as PublicWallController;
use App\Http\Controllers\Public\WidgetController as PublicWidgetController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => redirect()->route(Auth::check() ? 'dashboard.overview' : 'login'));

Route::get('s/{slug}', [PublicSpaceController::class, 'show'])->name('spaces.show');

Route::post('s/{slug}/testimonials', [PublicTestimonialController::class, 'store'])
    ->middleware('throttle:testimonial-submission')
    ->name('spaces.testimonials.store');

Route::get('s/{slug}/testimonials/thanks', [PublicTestimonialController::class, 'thanks'])
    ->name('spaces.testimonials.thanks');

Route::get('wall/{slug}', [PublicWallController::class, 'show'])->name('walls.show');

Route::get('wall/{slug}/photo/{testimonial}', [PublicWallController::class, 'photo'])
    ->whereNumber('testimonial')
    ->name('walls.photos.show');

Route::get('widget/{publicId}', [PublicWidgetController::class, 'show'])->name('widgets.show');

Route::get('widget/{publicId}/photo/{testimonial}', [PublicWidgetController::class, 'photo'])
    ->whereNumber('testimonial')
    ->name('widgets.photos.show');

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function (): void {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::prefix('dashboard')->name('dashboard.')->group(function (): void {
        Route::get('/', OverviewController::class)->name('overview');

        Route::get('spaces', [SpaceController::class, 'index'])->name('spaces.index');
        Route::get('spaces/create', [SpaceController::class, 'create'])->name('spaces.create');
        Route::post('spaces', [SpaceController::class, 'store'])->name('spaces.store');
        Route::get('spaces/created/{space}', [SpaceController::class, 'created'])->name('spaces.created');
        Route::get('spaces/{space}', [SpaceController::class, 'show'])->name('spaces.show');
        Route::get('spaces/{space}/edit', [SpaceController::class, 'edit'])->name('spaces.edit');
        Route::put('spaces/{space}', [SpaceController::class, 'update'])->name('spaces.update');
        Route::delete('spaces/{space}', [SpaceController::class, 'destroy'])->name('spaces.destroy');

        Route::get('inbox', [TestimonialController::class, 'index'])->name('inbox');

        Route::get('testimonials/{testimonial}/edit', [TestimonialController::class, 'edit'])
            ->whereNumber('testimonial')
            ->name('testimonials.edit');
        Route::put('testimonials/{testimonial}', [TestimonialController::class, 'update'])
            ->whereNumber('testimonial')
            ->name('testimonials.update');
        Route::delete('testimonials/{testimonial}', [TestimonialController::class, 'destroy'])
            ->whereNumber('testimonial')
            ->name('testimonials.destroy');

        Route::post('testimonials/{testimonial}/approve', [TestimonialController::class, 'approve'])
            ->whereNumber('testimonial')
            ->name('testimonials.approve');
        Route::post('testimonials/{testimonial}/hide', [TestimonialController::class, 'hide'])
            ->whereNumber('testimonial')
            ->name('testimonials.hide');
        Route::post('testimonials/{testimonial}/favorite', [TestimonialController::class, 'favorite'])
            ->whereNumber('testimonial')
            ->name('testimonials.favorite');
        Route::post('testimonials/{testimonial}/heart', [TestimonialController::class, 'heart'])
            ->whereNumber('testimonial')
            ->name('testimonials.heart');

        Route::get('wall', [PageController::class, 'wall'])->name('wall');
        Route::get('widgets', [PageController::class, 'widgets'])->name('widgets');

        Route::get('billing', BillingController::class)->name('billing');

        Route::post('billing/upgrade', [BillingController::class, 'upgrade'])
            ->name('billing.upgrade');

        Route::get('billing/portal', [BillingController::class, 'portal'])
            ->name('billing.portal');

        Route::get('settings', [ProfileController::class, 'edit'])->name('settings');
        Route::patch('settings/profile', [ProfileController::class, 'update'])->name('settings.profile.update');
        Route::put('settings/password', [ProfileController::class, 'updatePassword'])->name('settings.password.update');
    });
});
