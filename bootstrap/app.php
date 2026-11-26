<?php

use App\Http\Controllers\RelatorioInterno;
use App\Http\Middleware\GuardarOrigem;
use App\Http\Middleware\SetActiveProject;
use App\Http\Middleware\SoAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // The protected progress report: stateless JSON (no session, no visit counted), its own token.
        then: fn () => Route::middleware('throttle:60,1')->prefix('interno')->group(function () {
            Route::get('resumo', [RelatorioInterno::class, 'resumo'])->name('interno.resumo');
            Route::get('dias', [RelatorioInterno::class, 'dias'])->name('interno.dias');
        }),
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
        $middleware->web(append: [SetActiveProject::class]);
        // First-touch attribution (UTM, click ids, referrer) kept in the session.
        $middleware->web(append: [GuardarOrigem::class]);
        // Set by the cookie banner and by Meta's pixel in the browser: read as they are.
        $middleware->encryptCookies(except: ['imocena_cookies', '_fbp', '_fbc']);

        // The studio is the operator's; customers (buyers, signed in by email link) get /conta.
        $middleware->alias(['admin' => SoAdmin::class]);
        // Someone already signed in who opens /login goes to their own area, not the landing
        // (which looked like the sign-in had silently failed).
        $middleware->redirectUsersTo(fn () => auth()->user()?->isAdmin() ? route('imoveis') : route('conta'));

        // One-click unsubscribe (RFC 8058): the mail provider POSTs without a CSRF
        // token. The route is signed, so the signature does that job.
        $middleware->validateCsrfTokens(except: ['emails/cancelar/*', 'stripe/webhook']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'interno/*'),
        );
    })->create();
