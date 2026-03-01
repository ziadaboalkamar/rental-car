<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlansController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Plans/Index', [
            'plans' => Plan::latest()->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('SuperAdmin/Plans/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'features' => 'nullable|array',
            'monthly_price' => 'required|numeric|min:0',
            'monthly_price_id' => 'nullable|string|max:255',
            'yearly_price' => 'required|numeric|min:0',
            'yearly_price_id' => 'nullable|string|max:255',
            'one_time_price' => 'nullable|numeric|min:0',
            'one_time_price_id' => 'nullable|string|max:255',
            'is_active' => 'required|boolean',
        ]);

        Plan::create($validated);

        return redirect()->route('superadmin.plans.index')
            ->with('success', 'Plan created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Plan $plan): Response
    {
        return Inertia::render('SuperAdmin/Plans/Edit', [
            'plan' => $plan,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'features' => 'nullable|array',
            'monthly_price' => 'required|numeric|min:0',
            'monthly_price_id' => 'nullable|string|max:255',
            'yearly_price' => 'required|numeric|min:0',
            'yearly_price_id' => 'nullable|string|max:255',
            'one_time_price' => 'nullable|numeric|min:0',
            'one_time_price_id' => 'nullable|string|max:255',
            'is_active' => 'required|boolean',
        ]);

        $plan->update($validated);

        return redirect()->route('superadmin.plans.index')
            ->with('success', 'Plan updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Plan $plan)
    {
        $plan->delete();

        return redirect()->route('superadmin.plans.index')
            ->with('success', 'Plan deleted successfully.');
    }
}
