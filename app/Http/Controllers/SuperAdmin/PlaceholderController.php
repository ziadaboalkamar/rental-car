<?php

namespace App\Http\Controllers\SuperAdmin;

use Inertia\Inertia;
use Inertia\Response;

class PlaceholderController
{
    private function placeholder(string $title): Response
    {
        return Inertia::render('SuperAdmin/Placeholder', ['title' => $title]);
    }

    public function subscription(): Response { return $this->placeholder('Subscription'); }
    public function transactions(): Response { return $this->placeholder('Transactions'); }
    public function users(): Response { return $this->placeholder('Users'); }
    public function roles(): Response { return $this->placeholder('Roles'); }
    public function plans(): Response { return $this->placeholder('Plans'); }
    public function discounts(): Response { return $this->placeholder('Discounts'); }
    public function cars(): Response { return $this->placeholder('Cars'); }
    public function reservations(): Response { return $this->placeholder('Reservations'); }
    public function settingsGeneral(): Response { return $this->placeholder('General Settings'); }
}
