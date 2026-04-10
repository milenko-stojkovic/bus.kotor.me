<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zaštita glavnog Admin panela (`/admin`): samo `admins` sa admin_access=1 i control_access=0.
 */
class EnsureAdminPanelAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('panel_admin')->user();
        if (! $user instanceof Admin) {
            abort(403);
        }

        if (! $user->admin_access || $user->control_access) {
            abort(403, 'Admin panel access required.');
        }

        return $next($request);
    }
}
