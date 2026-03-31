<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->to($this->redirectUrlFor($request->user()));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
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
