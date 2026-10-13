<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth()->check()) {
            return redirect()->guest('/login')->with('error', 'Please log in to access this page.');
        }

        $user = auth()->user();

        // If system_admin, allow full administrative oversight across roles
        if ($user->role === 'system_admin') {
            return $next($request);
        }

        if (!in_array($user->role, $roles)) {
            abort(403, 'Unauthorized. Access restricted to: ' . implode(', ', $roles));
        }

        return $next($request);
    }
}
