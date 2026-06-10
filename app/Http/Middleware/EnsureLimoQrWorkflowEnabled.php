<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Legacy Limo QR workflow gate (agency QR generation, evidentičar scan/OCR).
 *
 * When disabled (default), QR-related routes return 404 while informational
 * /panel/limo and admin historical pages remain available.
 *
 * Rollback: set LIMO_QR_WORKFLOW_ENABLED=true in environment.
 */
final class EnsureLimoQrWorkflowEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('features.limo_qr_workflow')) {
            abort(404);
        }

        return $next($request);
    }
}
