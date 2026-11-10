<?php

namespace App\Http\Middleware;

use App\Services\Marketing\Origem;
use Closure;
use Illuminate\Http\Request;

/** Remembers the first campaign / referrer a visitor arrived with, for the rest of the session. */
class GuardarOrigem
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET') && ! $request->ajax() && ! session()->has('origem') && ($o = Origem::daPedido($request))) {
            session(['origem' => $o]);
        }

        return $next($request);
    }
}
