<?php

namespace App\Http\Controllers;

use App\Enums\CarStatus;
use App\Core\TenantContext;
use App\Core\LandingPageSettings;
use App\Models\Car;
use App\Models\Plan;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\Ticket;
use Illuminate\Http\Request;

class HomePagesController extends Controller
{
    /**
     * @return array<int, string>
     */
    private function publicFleetStatuses(): array
    {
        return [
            CarStatus::AVAILABLE->value,
            CarStatus::RESERVED->value,
            CarStatus::RENTED->value,
        ];
    }

    public function index()
    {
        if (!TenantContext::get()) {
            $stored = SiteSetting::query()
                ->where('key', LandingPageSettings::KEY)
                ->value('value');

            $landingSettings = LandingPageSettings::normalize(is_array($stored) ? $stored : null);

            $plans = Plan::query()
                ->where('is_active', true)
                ->orderBy('monthly_price')
                ->get([
                    'id',
                    'name',
                    'description',
                    'features',
                    'monthly_price',
                    'yearly_price',
                    'one_time_price',
                ]);

            $tenantLogos = Tenant::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(12)
                ->get(['id', 'name', 'slug', 'settings'])
                ->map(static function (Tenant $tenant) {
                    $settings = is_array($tenant->settings) ? $tenant->settings : [];

                    return [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'slug' => $tenant->slug,
                        'logo_url' => data_get($settings, 'branding.logo_url')
                            ?? data_get($settings, 'logo_url')
                            ?? data_get($settings, 'logo'),
                    ];
                })
                ->values();

            return inertia('SuperAdmin/landing/Landing', compact('landingSettings', 'plans', 'tenantLogos'));
        }

        $homeCars = Car::whereIn('status', $this->publicFleetStatuses())
            ->select('id', 'make', 'model', 'year', 'price_per_day', 'description', 'fuel_type')
            ->orderByRaw("CASE WHEN status = ? THEN 0 WHEN status = ? THEN 1 WHEN status = ? THEN 2 ELSE 3 END", [
                CarStatus::AVAILABLE->value,
                CarStatus::RESERVED->value,
                CarStatus::RENTED->value,
            ])
            ->orderByDesc('year')
            ->limit(6)
            ->get();

        return inertia('Welcome', compact('homeCars'));
    }

    public function fleet(Request $request)
    {
        $query = Car::whereIn('status', $this->publicFleetStatuses())
            ->select('id', 'make', 'model', 'year', 'price_per_day', 'description', 'fuel_type');

        // Search functionality
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('make', 'like', "%{$searchTerm}%")
                    ->orWhere('model', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        // Make filter
        if ($request->filled('make')) {
            $query->where('make', $request->make);
        }

        // Fuel type filter
        if ($request->filled('fuel_type')) {
            $query->where('fuel_type', $request->fuel_type);
        }

        // Year filter
        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }

        // Price range filter
        if ($request->filled('min_price')) {
            $query->where('price_per_day', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price_per_day', '<=', $request->max_price);
        }

        $cars = $query
            ->orderByRaw("CASE WHEN status = ? THEN 0 WHEN status = ? THEN 1 WHEN status = ? THEN 2 ELSE 3 END", [
                CarStatus::AVAILABLE->value,
                CarStatus::RESERVED->value,
                CarStatus::RENTED->value,
            ])
            ->paginate(10)
            ->withQueryString();

        // Get filter options
        $makes = Car::whereIn('status', $this->publicFleetStatuses())
            ->distinct()
            ->pluck('make')
            ->toArray();

        $fuelTypes = Car::whereIn('status', $this->publicFleetStatuses())
            ->distinct()
            ->pluck('fuel_type')
            ->toArray();

        $years = Car::whereIn('status', $this->publicFleetStatuses())
            ->distinct()
            ->pluck('year')
            ->toArray();

        $filters = $request->only(['search', 'make', 'fuel_type', 'min_price', 'max_price', 'year']);

        return inertia('Fleet', compact('cars', 'makes', 'fuelTypes', 'years', 'filters'));
    }

    public function about()
    {
        return inertia('About');
    }

    public function contact()
    {
        return inertia('Contact');
    }

    public function guestContact(Request $request)
    {
        $tenantSlug = TenantContext::get()?->slug;
        $tenantId = TenantContext::id();
        if (!$tenantId) {
            return redirect()->back()->with('error', 'Contact form is only available on tenant websites.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $ticket = Ticket::create([
            'tenant_id' => $tenantId,
            'channel' => 'guest',
            'guest_name' => $request->name,
            'guest_email' => $request->email,
            'subject' => $request->subject,
        ]);

        $ticket->messages()->create([
            'tenant_id' => $ticket->tenant_id,
            'message' => $request->message,
        ]);

        return redirect()->route('tenant.contact', ['subdomain' => $tenantSlug])->with('success', 'Message sent successfully!');
    }
}
