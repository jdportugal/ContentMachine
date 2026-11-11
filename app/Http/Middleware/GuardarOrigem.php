<?php

namespace App\Http\Middleware;

use App\Services\Marketing\Canal;
use App\Services\Marketing\Origem;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Remembers the first campaign / referrer a visitor arrived with, for the rest of the
 * session, and counts the visit (once per session) for the campaign funnel.
 */
class GuardarOrigem
{
    private const ROBOS = '/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor|uptime|curl|wget|python|headless|lighthouse|scan/i';

    public function handle(Request $request, Closure $next)
    {
        $pagina = $request->isMethod('GET') && ! $request->ajax() && ! $request->hasHeader('X-Livewire');
        if ($pagina && ! session()->has('origem') && ($o = Origem::daPedido($request))) {
            session(['origem' => $o]);
        }
        $response = $next($request);

        if ($pagina && ! session()->has('visita') && $this->contaComoVisita($request, $response)) {
            session(['visita' => true]);
            rescue(fn () => DB::table('visitas')->insert([
                'canal' => Canal::de(Origem::atual()),
                'pagina' => mb_substr('/'.ltrim($request->path(), '/'), 0, 190),
                'origem' => Origem::json(),
                'created_at' => now(),
            ]), report: false);
        }

        return $response;
    }

    /** A person opening a page of the public site: not a robot, not the operator, not a file. */
    private function contaComoVisita(Request $request, $response): bool
    {
        $ua = (string) $request->userAgent();

        return $response->getStatusCode() === 200
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            && $ua !== '' && ! preg_match(self::ROBOS, $ua)
            && ! $request->user()?->isAdmin()
            && ! $request->routeIs('login');
    }
}
