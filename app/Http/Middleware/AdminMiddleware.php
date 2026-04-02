<?php

namespace App\Http\Middleware;

use App\Core\TenantContext;
use App\Enums\UserRole;
use App\Support\TenantAdminAccessSync;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function __construct(
        private readonly TenantAdminAccessSync $tenantAdminAccessSync,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check() || Auth::user()->role !== UserRole::ADMIN) {
            abort(403, 'Unauthorized action.');
        }

        $tenantId = TenantContext::id();
        $userTenantId = (int) (Auth::user()->tenant_id ?? 0);

        if ($tenantId && $userTenantId !== (int) $tenantId) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, 'Unauthorized action.');
        }

        $tenant = TenantContext::get();

        if ($tenant) {
            $this->tenantAdminAccessSync->syncUser(Auth::user(), $tenant);
            Auth::setUser(Auth::user()->fresh(['roles.permissions']));
        }

        return $next($request);
    }
}
