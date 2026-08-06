<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP Basic gate over the WHOLE app.
 *
 * There are no user accounts here — this is a single-tenant dashboard — but it
 * holds the API keys (Definições → Chaves) and every generated asset, so it must
 * never be world-readable. One shared password, checked on every request.
 *
 * The password comes from APP_PASSWORD. In Docker the entrypoint generates and
 * persists one on the storage volume (same trick as APP_KEY), so a deploy is
 * never unprotected by accident.
 *
 * Fails CLOSED: no password configured outside local dev = nobody gets in. A
 * misconfigured deploy must break loudly, not quietly serve the keys to the
 * internet (which is exactly how they leaked before).
 */
class RequireDashboardAuth
{
    /** Paths that stay open: container/uptime health probes only (no data). */
    private const ABERTAS = ['up'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is(...self::ABERTAS)) {
            return $next($request);
        }

        $senha = $this->senhaConfigurada();

        if ($senha === '') {
            // No password set. Local dev and the test suite run open for
            // convenience; anything else is a broken deploy and is refused
            // rather than exposed.
            if (app()->environment('local', 'testing')) {
                return $next($request);
            }

            return response('Dashboard password not configured (APP_PASSWORD).', 503);
        }

        if ($this->autenticado($request, $senha)) {
            return $next($request);
        }

        return response('Unauthorized.', 401, [
            'WWW-Authenticate' => 'Basic realm="Content Machine", charset="UTF-8"',
        ]);
    }

    /**
     * The configured password: APP_PASSWORD, or the file the entrypoint persists.
     *
     * The file fallback is NOT optional. The container serves through
     * `php artisan serve`, and ServeCommand only forwards a fixed allowlist of
     * variables to the built-in server it spawns (APP_ENV, PATH, XDEBUG_*…) —
     * APP_PASSWORD is not on it, so an exported value never reaches the process
     * handling requests. Without this fallback the gate would see no password and
     * 503 the whole app in production.
     */
    private function senhaConfigurada(): string
    {
        $senha = trim((string) config('contentmachine.dashboard.password', ''));
        if ($senha !== '') {
            return $senha;
        }

        $ficheiro = storage_path('app/app_password');

        return is_file($ficheiro) ? trim((string) file_get_contents($ficheiro)) : '';
    }

    /** Constant-time check of the Basic credentials (any username is accepted). */
    private function autenticado(Request $request, string $senha): bool
    {
        $fornecida = (string) ($request->getPassword() ?? '');

        // Production serves through `php artisan serve` (PHP's built-in server),
        // which does not split Authorization into PHP_AUTH_USER/PHP_AUTH_PW the
        // way php-fpm does — getPassword() comes back empty there. Fall back to
        // decoding the raw header so the gate behaves the same on every SAPI.
        if ($fornecida === '') {
            $fornecida = $this->senhaDoCabecalho($request);
        }

        return $fornecida !== '' && hash_equals($senha, $fornecida);
    }

    /** Password out of a raw `Authorization: Basic base64(user:pass)` header. */
    private function senhaDoCabecalho(Request $request): string
    {
        $cabecalho = (string) ($request->header('Authorization') ?? $request->server('HTTP_AUTHORIZATION') ?? '');
        if (stripos($cabecalho, 'basic ') !== 0) {
            return '';
        }

        $decodificado = base64_decode(substr($cabecalho, 6), true);
        if ($decodificado === false || ! str_contains($decodificado, ':')) {
            return '';
        }

        return substr($decodificado, strpos($decodificado, ':') + 1);
    }
}
