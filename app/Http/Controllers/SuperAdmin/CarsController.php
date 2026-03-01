<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Enums\CarStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CarsController extends Controller
{
    public function index(Request $request)
    {
        $statusValues = [
            'available', 'reserved', 'rented', 'maintenance', 
            'cleaning', 'unavailable', 'retired'
        ];

        $cars = Car::with('tenant')
            ->when($request->search, function ($query, $search) {
                $query->where(function($q) use ($search) {
                    $q->where('make', 'like', "%{$search}%")
                      ->orWhere('model', 'like', "%{$search}%")
                      ->orWhere('license_plate', 'like', "%{$search}%");
                });
            })
            ->when($request->status && in_array($request->status, $statusValues), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        // Get status counts for the filter
        $statuses = collect($statusValues)->mapWithKeys(function($status) {
            return [
                $status => [
                    'label' => ucfirst($status),
                    'count' => Car::where('status', $status)->count(),
                    'color' => CarStatus::from($status)->color()
                ]
            ];
        });

        return Inertia::render('SuperAdmin/Cars/Index', [
            'cars' => $cars,
            'filters' => $request->only(['search', 'status']),
            'statuses' => $statuses
        ]);
    }
}
