<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApproved
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->guest('/login')->with('error', 'Please log in to access this page.');
        }

        $user = auth()->user();

        // System admins and employers are always approved
        if ($user->role === 'system_admin' || $user->role === 'employer') {
            return $next($request);
        }

        if (!$user->approved) {
            return redirect('/')->with('warning', 'Your account is pending system administrator approval before you can issue documents.');
        }

        return $next($request);
    }
}
