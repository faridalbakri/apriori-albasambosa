<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdminRole
{
    /**
     * Deny access unless authenticated user has admin role.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (! $request->user() || $request->user()->role !== 'admin') {
            abort(403);
        }

        return $next($request);
    }
}
