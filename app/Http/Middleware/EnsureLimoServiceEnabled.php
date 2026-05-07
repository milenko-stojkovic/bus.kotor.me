<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limo service feature gate.
 *
 * Limo must be unavailable unless BOTH:
 * - advance payments are enabled
 * - limo service is enabled
 *
 * Unavailable => 404 (hide feature surface).
 */
final class EnsureLimoServiceEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $advance = (bool) config('features.advance_payments');
        $limo = (bool) config('features.limo_service');

        if (! ($advance && $limo)) {
            abort(404);
        }

        return $next($request);
    }
}

