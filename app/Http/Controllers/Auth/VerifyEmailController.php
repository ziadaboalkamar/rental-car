<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->to($this->redirectUrlFor($request->user()).'?verified=1');
        }

        $request->fulfill();

        return redirect()->to($this->redirectUrlFor($request->user()).'?verified=1');
    }

    private function redirectUrlFor(object $user): string
    {
        if (($user->role ?? null) === UserRole::SUPER_ADMIN) {
            return route('superadmin.dashboard');
        }

        if (in_array($user->role ?? null, [UserRole::ADMIN, UserRole::CLIENT], true)) {
            $tenantSlug = Tenant::query()
                ->whereKey((int) ($user->tenant_id ?? 0))
                ->value('slug');

            if (is_string($tenantSlug) && $tenantSlug !== '') {
                return $user->role === UserRole::ADMIN
                    ? route('admin.home', ['subdomain' => $tenantSlug])
                    : route('client.home', ['subdomain' => $tenantSlug]);
            }
        }

        return route('dashboard');
    }
}
