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

        // Require a signed-in user for EVERYTHING in routes/web.php — pages and
        // the file-serving routes alike (those hand out rendered media and would
        // otherwise stay open). The sign-in screen opts out via withoutMiddleware.
        $middleware->web(append: [\Illuminate\Auth\Middleware\Authenticate::class]);

        // Resolve the active project (per session) and repoint the vault at it.
        // After Authenticate, so it only ever runs for a real session.
        $middleware->web(append: [\App\Http\Middleware\SetActiveProject::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
