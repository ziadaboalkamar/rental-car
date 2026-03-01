<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Reservation;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    /**
     * Display the Super Admin dashboard with system-wide statistics.
     */
    public function index(): Response
    {
        // Get all tenants with their counts (bypass tenant scope)
        $tenants = Tenant::query()
            ->with('subscriptionPlan:id,name')
            ->withCount(['users', 'cars', 'reservations'])
            ->get();

        // System-wide statistics
        $stats = [
            'total_tenants' => $tenants->count(),
            'active_tenants' => $tenants->where('is_active', true)->count(),
            'total_users' => User::withoutGlobalScope('tenant')->count(),
            'total_reservations' => Reservation::withoutGlobalScope('tenant')->count(),
            'total_revenue' => (float) DB::table('subscriptions')
                ->whereNotNull('amount_paid')
                ->sum('amount_paid'),
        ];

        // Recent tenants
        $recentTenants = Tenant::query()
            ->with('subscriptionPlan:id,name')
            ->latest()
            ->take(5)
            ->get();

        // Monthly revenue chart data (last 6 months)
        $monthlyRevenue = Payment::withoutGlobalScope('tenant')
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subMonths(6))
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('SUM(amount) as revenue')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $recentSubscriptions = DB::table('subscriptions as subscriptions')
            ->join('users as users', 'users.id', '=', 'subscriptions.user_id')
            ->leftJoin('tenants as tenants', 'tenants.id', '=', 'users.tenant_id')
            ->select([
                'subscriptions.id',
                'subscriptions.type',
                'subscriptions.stripe_status',
                'subscriptions.payment_method',
                'subscriptions.amount_paid',
                'subscriptions.currency',
                DB::raw('COALESCE(subscriptions.paid_at, subscriptions.updated_at, subscriptions.created_at) as paid_at'),
                'subscriptions.trial_ends_at',
                'users.name as user_name',
                'tenants.name as tenant_name',
            ])
            ->orderByRaw('COALESCE(subscriptions.paid_at, subscriptions.updated_at, subscriptions.created_at) DESC')
            ->limit(10)
            ->get();

        return Inertia::render('SuperAdmin/Dashboard', [
            'stats' => $stats,
            'recentTenants' => $recentTenants,
            'monthlyRevenue' => $monthlyRevenue,
            'recentSubscriptions' => $recentSubscriptions,
            'tenants' => $tenants,
        ]);
    }
}
