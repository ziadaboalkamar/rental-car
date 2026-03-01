<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use Illuminate\Support\Facades\Auth;

class SuperAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('superadmin.login');
        }

        if (Auth::user()->role !== UserRole::SUPER_ADMIN) {
            Auth::logout();
            return redirect()->route('superadmin.login')->with('status', 'Please login continuously as Super Admin.');
        }

        return $next($request);
    }
}
