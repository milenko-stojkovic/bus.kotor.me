<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limo API/modul (`/limo/*`): zahtijeva isti guard kao Admin login (`panel_admin`), ali ovlašćenje preko {@see Admin::$limo_access},
 * ne preko {@see EnsureAdminPanelAccess} (`admin_access`).
 */
final class EnsureLimoAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('panel_admin')->user();
        if (! $user instanceof Admin || ! $user->limo_access) {
            abort(403, 'Limo access required.');
        }

        return $next($request);
    }
}
