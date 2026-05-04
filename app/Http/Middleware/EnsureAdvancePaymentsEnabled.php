<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rute koje zavise od avansnog modela (Limo, itd.): 404 kada je {@see config('features.advance_payments')} isključen.
 */
final class EnsureAdvancePaymentsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('features.advance_payments')) {
            abort(404);
        }

        return $next($request);
    }
}
