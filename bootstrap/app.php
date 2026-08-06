<?php

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
        // Behind Caddy/any reverse proxy: trust X-Forwarded-* so the app knows the
        // request is HTTPS and generates https:// URLs (no mixed-content on assets).
        $middleware->trustProxies(at: '*');

        // NOTE: authentication is applied to the route group in routes/web.php,
        // NOT here. Livewire registers its /livewire/update endpoint with the
        // `web` group, so gating this group blocks the sign-in and sign-up forms
        // themselves — their submissions ARE Livewire requests — and every login
        // attempt just bounces back to /login.

        // Resolve the active project (per session) and repoint the vault at it.
        $middleware->web(append: [\App\Http\Middleware\SetActiveProject::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
