<?php

namespace App\Http\Responses;

use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request): RedirectResponse
    {
        $user = auth()->user();

        // Redirect based on user role
        return match ($user->role) {
            UserRole::SUPER_ADMIN => redirect()->intended('/superadmin'),
            UserRole::ADMIN => redirect()->intended('/admin/cars'),
            UserRole::CLIENT => redirect()->intended('/client/reservations'),
            default => redirect()->intended('/'),
        };
    }
}
