# Codex Analysis: Real Rent Car SaaS (Current State)

Date: 2026-02-21  
Project: `real-rent-car-main`

## 1) System Goal

This project is a multi-tenant car-rental SaaS with:
- Base domain: SaaS marketing/landing + super admin area.
- Tenant subdomain: tenant public website + tenant admin/client dashboards.
- Localization: English/Arabic with locale prefixes (`/en`, `/ar`).
- Billing: Stripe checkout flow with plan selection and renewal logic.

---

## 2) Domain + Routing Architecture

### Base domain routing
- Base host is derived from `APP_URL`.
- Base domain routes are in `Route::domain($baseDomain)`:
  - `/` -> SaaS landing (`HomePagesController@index`).
  - Auth routes (`/login`, `/register`, `/tenant/login`, etc).
  - Super admin routes.
  - Settings routes.

Reference:
- `routes/web.php`

### Tenant subdomain routing
- Tenant routes are in `Route::domain('{subdomain}.'.$baseDomain)`.
- Tenant public pages (`/`, `/fleet`, `/about`, `/contact`) are protected by subscription middleware.
- Tenant auth routes are loaded with route name prefix `tenant.`.
- Tenant admin and client routes are loaded from `routes/admin.php` and `routes/client.php`.

Reference:
- `routes/web.php`
- `routes/admin.php`
- `routes/client.php`

### Localization routing
- All major routes are wrapped by:
  - prefix: `LaravelLocalization::setLocale()`
  - middleware: `localeSessionRedirect`, `localizationRedirect`, `localeViewPath`
- This means URLs can be locale-prefixed, e.g.:
  - `http://tenant.real-rent-car-main.test/ar/admin/cars`

Reference:
- `routes/web.php`
- `bootstrap/app.php`

---

## 3) Tenant Identification + Context

Tenant is identified in middleware by:
1. Subdomain slug (`{slug}.base-domain`)
2. Custom domain (`tenants.domain`)

Then tenant is stored in runtime context (`TenantContext`) and shared to Inertia as `current_tenant`.

Important behavior:
- Middleware no longer filters by `is_active` during identification; it resolves tenant first.
- Access control happens later via subscription middleware/auth checks.

Reference:
- `app/Http/Middleware/IdentifyTenant.php`
- `app/Core/TenantContext.php`
- `app/Http/Middleware/HandleInertiaRequests.php`

---

## 4) Auth Entry Points + Login Behavior

### Main auth endpoints
- `/login` -> generic login (`AuthenticatedSessionController@store`)
- `/tenant/login` -> tenant admin-focused login (`storeAdminLogin`)
- `/admin-secret-url` -> alternate admin login endpoint

Reference:
- `routes/auth.php`

### Tenant-aware auth provider
- Custom auth provider `eloquent-tenant-aware` is registered.
- It bypasses global scopes, then reapplies tenant boundary using `TenantContext::id()`.

Reference:
- `config/auth.php`
- `app/Providers/AppServiceProvider.php`
- `app/Auth/TenantAwareUserProvider.php`

### Current login checks
On login, system checks:
1. Role authorization.
2. User trial expiry (`users.trial_ends_at`) for admin/client behavior.
3. Tenant active flag (`tenants.is_active`).
4. Tenant subscription renewal requirement (`plan_id`, tenant trial, plan active).

If admin tenant plan is expired/missing:
- Session is seeded for existing-tenant billing flow.
- User is redirected to plan selection (`tenant.register.plans` on subdomain).

Reference:
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Models/Tenant.php`
- `app/Models/User.php`

---

## 5) Registration + Billing Flow (Stripe)

### Flow overview
1. Register company/user details.
2. Select plan (`plan_id`) + billing cycle (`monthly/yearly/one_time`).
3. Checkout on Stripe.
4. Return success URL -> verify Stripe session.
5. Create tenant/admin OR renew existing tenant.

### Existing tenant renewal mode
- When admin logs in and plan is missing/expired, registration session is seeded in `existing_tenant` mode.
- `register/plans` + `register/checkout` can run under tenant context in this specific mode.

### Plan storage
- Tenant stores `plan_id` (FK to `plans.id`) as primary plan reference.
- Tenant/user trial end dates are updated on successful checkout.

Reference:
- `app/Http/Controllers/Auth/RegisteredUserController.php`
- `database/migrations/2026_02_19_132324_add_plan_id_to_tenants_table.php`
- `database/migrations/2026_02_19_133357_backfill_missing_tenant_plan_ids.php`

---

## 6) Subscription/Access Enforcement

### Middleware gate
`tenant.subscription` middleware blocks tenant public/admin/client pages when:
- Tenant is inactive.
- `plan_id` is missing.
- `trial_ends_at` is missing or past.
- Linked plan is inactive.

Blocked users are redirected to tenant login with flash error.

Reference:
- `app/Http/Middleware/EnsureTenantSubscriptionIsActive.php`
- `bootstrap/app.php`
- `routes/web.php`
- `routes/admin.php`
- `routes/client.php`

### Model-level renewal rule
`Tenant::requiresSubscriptionRenewal()` centralizes renewal condition.

Reference:
- `app/Models/Tenant.php`

---

## 7) Data Model (Important Billing Fields)

### `tenants` table (current usage)
- `plan_id` (active plan FK, nullable historically)
- `trial_ends_at` (access end date used for renewal check)
- `is_active`
- `slug`, `domain`, `settings`
- Legacy `plan` string still exists for backward compatibility (cleanup candidate).

### `users` table
- `tenant_id`
- `role` (`SUPER_ADMIN`, `ADMIN`, `CLIENT`)
- `trial_ends_at` now used in login checks.
- `is_active`

### `plans` table
- Stripe price IDs per cycle:
  - `monthly_price_id`
  - `yearly_price_id`
  - `one_time_price_id`
- `is_active`

---

## 8) Super Admin Tenant Management (Updated)

Tenant CRUD now uses `plan_id` instead of plan name:
- Create/Edit forms submit `plan_id`.
- Controller validates `plan_id` against active plans.
- Tenant list/show/dashboard display related `subscriptionPlan.name`.

Reference:
- `app/Http/Controllers/SuperAdmin/TenantsController.php`
- `app/Http/Controllers/SuperAdmin/DashboardController.php`
- `resources/js/pages/SuperAdmin/Tenants/Create.vue`
- `resources/js/pages/SuperAdmin/Tenants/Edit.vue`
- `resources/js/pages/SuperAdmin/Tenants/Index.vue`
- `resources/js/pages/SuperAdmin/Tenants/Show.vue`
- `resources/js/pages/SuperAdmin/Dashboard.vue`

---

## 9) Landing Page + Tenant Public Site

### Base domain
- `/` renders SaaS landing:
  - pulls dynamic landing settings,
  - active plans for pricing section,
  - active tenant logos.

### Tenant subdomain
- `/` renders tenant welcome page with tenant cars.
- Public routes are protected by subscription middleware.

Reference:
- `app/Http/Controllers/HomePagesController.php`
- `resources/js/pages/SuperAdmin/landing/Landing.vue`

---

## 10) Localization + RTL Status

Implemented:
- LaravelLocalization route prefixing.
- Locale session switching (`/locale/{locale}`).
- Shared props: `locale`, `direction`, `available_locales`, `translations`.
- Sidebar/UI supports RTL side switching in Arabic.
- Dashboard header includes language switch controls.

Reference:
- `app/Http/Middleware/SetLocale.php`
- `app/Http/Controllers/LocalizationController.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `resources/js/app.ts`
- `resources/js/components/AppSidebar.vue`
- `resources/js/components/ui/sidebar/SidebarRail.vue`
- `resources/js/components/AppSidebarHeader.vue`

---

## 11) Stripe Configuration

### Runtime config from DB
- Stripe/Cashier config is loaded from `site_settings` key `stripe_settings` on app boot.
- Super Admin can edit Stripe settings from dashboard page.

Reference:
- `app/Providers/AppServiceProvider.php`
- `app/Core/StripeSettings.php`
- `app/Http/Controllers/SuperAdmin/StripeSettingsController.php`
- `resources/js/pages/SuperAdmin/Settings/Stripe.vue`

---

## 12) Known Technical Debt / Risks

1. Legacy tenant plan column:
- `tenants.plan` still exists and is still fillable, but business logic now uses `plan_id`.
- Recommended: final cleanup migration to remove legacy `plan` usage/column after verification.

2. Trial semantics:
- `tenant.trial_ends_at` is currently used as general access end date (trial/subscription).
- Recommended: split into explicit subscription period fields if needed (`subscription_ends_at`, etc).

3. Automated tests:
- Existing test suite has failures due older assumptions and custom auth flow changes.
- Needs dedicated test refactor for new billing/login flow.

---

## 13) Quick Diagnostics

### Check specific account + tenant billing state
```powershell
php artisan tinker --execute "`$u = App\Models\User::withoutGlobalScope('tenant')->where('email','admin@example.com')->first(); echo 'user_trial=' . (`$u?->trial_ends_at?->toDateTimeString() ?? 'null') . PHP_EOL; `$t = App\Models\Tenant::with('subscriptionPlan')->find(`$u?->tenant_id); if(`$t){ echo 'tenant_trial=' . (`$t->trial_ends_at?->toDateTimeString() ?? 'null') . ',plan_id=' . (`$t->plan_id ?? 'null') . ',plan=' . (`$t->subscriptionPlan->name ?? 'null') . PHP_EOL; }"
```

### Force expired user trial for testing redirect-to-plans
```powershell
php artisan tinker --execute "`$u=App\Models\User::withoutGlobalScope('tenant')->where('email','admin@example.com')->first(); `$u->update(['trial_ends_at' => '2026-02-19 00:00:00']);"
```

### Clear caches after route/auth changes
```powershell
php artisan optimize:clear
```

---

## 14) Current Scenario Summary

This project is now operating as:
- Multi-tenant by subdomain/custom-domain identification.
- Locale-aware by `/en` and `/ar`.
- Plan-driven access using `plan_id` + expiry checks.
- Stripe-based onboarding/renewal with existing-tenant renewal path.
- Tenant dashboards/public pages protected from expired subscriptions without deleting tenant data.

