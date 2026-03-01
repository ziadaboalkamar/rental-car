<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchesController extends Controller
{
    /**
     * Display a listing of branches.
     */
    public function index(Request $request): Response
    {
        $branches = Branch::query()
            ->when($request->string('search')->toString(), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Branches/Index', [
            'branches' => $branches,
            'filters' => [
                'search' => $request->string('search')->toString(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new branch.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Branches/Edit', [
            'branch' => null,
        ]);
    }

    /**
     * Store a newly created branch in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        if (config('app.demo_mode')) {
            return redirect()
                ->back()
                ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        Branch::create($validated);

        return redirect()
            ->route('admin.branches.index')
            ->with('success', 'Branch created successfully.');
    }

    /**
     * Show the form for editing the specified branch.
     */
    public function edit(Branch $branch): Response
    {
        return Inertia::render('Admin/Branches/Edit', [
            'branch' => $branch,
        ]);
    }

    /**
     * Update the specified branch in storage.
     */
    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        if (config('app.demo_mode')) {
            return redirect()
                ->back()
                ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $branch->update($validated);

        return redirect()
            ->route('admin.branches.index')
            ->with('success', 'Branch updated successfully.');
    }

    /**
     * Remove the specified branch from storage.
     */
    public function destroy(Branch $branch)
    {
        if (config('app.demo_mode')) {
            return redirect()
                ->back()
                ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $branch->delete();

        return redirect()
            ->back()
            ->with('success', 'Branch deleted successfully.');
    }
}
