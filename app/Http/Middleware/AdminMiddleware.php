<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $hasRole = $user->roles()->where('name', 'admin')->exists();
        $listedInAdminsTable = DB::table('admins')->where('email', $user->email)->exists();

        if (! $hasRole && ! $listedInAdminsTable) {
            abort(403, 'Admin access required.');
        }

        return $next($request);
    }
}
