<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$this->requiresVerification($user) || $user->hasVerifiedEmail()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Your email address is not verified.');
        }

        $tenantSlug = \App\Core\TenantContext::get()?->slug;

        return redirect()->guest(
            $tenantSlug
                ? route('tenant.verification.notice', ['subdomain' => $tenantSlug])
                : route('verification.notice')
        );
    }

    private function requiresVerification(object $user): bool
    {
        return !empty($user->tenant_id)
            && in_array($user->role, [UserRole::ADMIN, UserRole::CLIENT], true);
    }
}
