<?php

namespace App\Http\Controllers\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RevenueSubscriptionController
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $billingType = trim((string) $request->query('billing_type', ''));

        $subscriptions = DB::table('subscriptions as subscriptions')
            ->join('users as users', 'users.id', '=', 'subscriptions.user_id')
            ->leftJoin('tenants as tenants', 'tenants.id', '=', 'users.tenant_id')
            ->leftJoin('plans as plans', 'plans.id', '=', 'tenants.plan_id')
            ->select([
                'subscriptions.id',
                'subscriptions.type',
                'subscriptions.stripe_status',
                'subscriptions.stripe_id',
                'subscriptions.payment_method',
                'subscriptions.amount_paid',
                'subscriptions.currency',
                DB::raw('COALESCE(subscriptions.paid_at, subscriptions.updated_at, subscriptions.created_at) as paid_at'),
                'subscriptions.trial_ends_at',
                'subscriptions.ends_at',
                'users.name as user_name',
                'users.email as user_email',
                'tenants.name as tenant_name',
                'tenants.slug as tenant_slug',
                'plans.name as plan_name',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function ($nested) use ($like) {
                    $nested
                        ->orWhere('tenants.name', 'like', $like)
                        ->orWhere('tenants.slug', 'like', $like)
                        ->orWhere('users.name', 'like', $like)
                        ->orWhere('users.email', 'like', $like)
                        ->orWhere('subscriptions.payment_method', 'like', $like)
                        ->orWhere('subscriptions.stripe_id', 'like', $like)
                        ->orWhere('plans.name', 'like', $like);
                });
            })
            ->when($status !== '', fn ($query) => $query->where('subscriptions.stripe_status', $status))
            ->when($billingType !== '', fn ($query) => $query->where('subscriptions.type', $billingType))
            ->orderByRaw('COALESCE(subscriptions.paid_at, subscriptions.updated_at, subscriptions.created_at) DESC')
            ->paginate(20)
            ->withQueryString();

        $statuses = DB::table('subscriptions')
            ->whereNotNull('stripe_status')
            ->select('stripe_status')
            ->distinct()
            ->orderBy('stripe_status')
            ->pluck('stripe_status')
            ->values();

        $billingTypes = DB::table('subscriptions')
            ->whereNotNull('type')
            ->select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->values();

        return Inertia::render('SuperAdmin/Revenue/Subscriptions', [
            'subscriptions' => $subscriptions,
            'statuses' => $statuses,
            'billingTypes' => $billingTypes,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'billing_type' => $billingType,
            ],
        ]);
    }
}
