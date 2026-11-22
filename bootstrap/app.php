<?php

use App\Exceptions\PlanLimitExceeded;
use App\Exceptions\SlugGenerationFailed;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'guest' => RedirectIfAuthenticated::class,
            'auth' => Authenticate::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // The account has used every space allowed by its plan. The owner
        // is sent back to the spaces list with an explanation rather than
        // being shown an error page.
        $exceptions->render(function (PlanLimitExceeded $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 422);
            }

            return redirect()
                ->route('dashboard.spaces.index')
                ->with('error', $exception->getMessage());
        });

        // A unique slug could not be generated for the space. The name is
        // almost certainly the cause, so the owner is sent back to the
        // form with the error attached to it.
        $exceptions->render(function (SlugGenerationFailed $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 422);
            }

            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        });
    })->create();
