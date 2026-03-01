<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CanManageRoles
{
    /**
     * Allow only users who can manage roles.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user || !$user->hasPermission('manage-roles')) {
            return redirect()
                ->route('superadmin.dashboard')
                ->with('restricted_action', 'You do not have permission to manage roles.');
        }

        return $next($request);
    }
}
