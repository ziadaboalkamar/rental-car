<?php

namespace App\Http\Middleware;

use App\Core\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $permission
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!Auth::check() || !Auth::user()->hasPermission($permission)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $message = "You do not have permission to access this page ($permission).";

            $routeName = (string) optional($request->route())->getName();
            $tenantSlug = TenantContext::get()?->slug;

            if (str_starts_with($routeName, 'admin.') && $tenantSlug) {
                $url = route('tenant.home', ['subdomain' => $tenantSlug]);
                $request->session()->flash('restricted_action', $message);

                if ($request->header('X-Inertia')) {
                    return Inertia::location($url);
                }

                return redirect()->to($url);
            }

            return redirect()
                ->route('superadmin.dashboard')
                ->with('restricted_action', $message);
        }

        return $next($request);
    }
}
