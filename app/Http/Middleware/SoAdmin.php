<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The studio is the operator's: a signed-in customer is sent to their own area. */
class SoAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->isAdmin()) {
            return $request->expectsJson() ? abort(403) : redirect()->route('conta');
        }

        return $next($request);
    }
}
