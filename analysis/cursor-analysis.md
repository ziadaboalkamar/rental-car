## Real Rent Car – Cursor Analysis

### 1. Current Architecture (Post Multi-Tenant Upgrade)

- **Framework**: Laravel 12 + Inertia.js + Vue 3 + Tailwind.
- **Auth**:
  - Uses Laravel Fortify and the default `web` guard with the `users` provider.
  - Login is handled by `AuthenticatedSessionController` using a custom `LoginRequest::validateCredentials()`.
  - Super Admin has a dedicated controller under `App\Http\Controllers\SuperAdmin\Auth`.
- **Tenancy**:
  - Implemented manually (no package) via:
    - `tenants` table and `App\Models\Tenant`.
    - `tenant_id` column on `users`, `cars`, `reservations`, `payments`, `tickets`, and `messages`.
    - `App\Traits\BelongsToTenant` trait that:
      - Sets `tenant_id` automatically on `creating` when `auth()->check()` and user has a tenant.
      - Adds a **named global scope** `'tenant'` that:
        - Returns early when there is **no authenticated user**.
        - Returns early for **Super Admin** users.
        - Applies `whereRaw('1 = 0')` when `tenant_id` is empty (fail-closed).
        - Otherwise applies `where tenant_id = auth()->user()->tenant_id`.
      - Provides a helper scope `withoutTenantScope()` to remove the global scope when needed.

### 2. Login Flow (Where the Error Appears)

- **Routes**: `routes/auth.php`
  - `GET /login` → `AuthenticatedSessionController@create`.
  - `POST /login` → `AuthenticatedSessionController@store` (Client login).
  - `GET /admin-secret-url` → `AuthenticatedSessionController@adminLogin`.
  - `POST /admin-secret-url` → `AuthenticatedSessionController@storeAdminLogin` (Admin login).
- **Controller**: `AuthenticatedSessionController`
  - Both `store()` and `storeAdminLogin()` call:
    - `$user = $request->validateCredentials();`
  - Then they:
    - Enforce role (`CLIENT` vs `ADMIN`).
    - Optionally handle 2FA via Fortify.
    - Call `Auth::login($user, $remember)` and redirect to the dashboard.
- **Credential Validation**: `App\Http\Requests\Auth\LoginRequest`
  - `validateCredentials()`:
    - Uses rate limiting.
    - Calls `Auth::getProvider()->retrieveByCredentials($this->only('email', 'password'))`.
    - Uses the provider again to `validateCredentials($user, ...)`.
    - Throws `ValidationException` with `auth.failed` when lookup or password fails.
- **Auth Config**: `config/auth.php`
  - `defaults.guard = web`.
  - `guards.web.driver = session`, `provider = users`.
  - `providers.users.driver = eloquent`, `model = App\Models\User`.

### 3. Why the Multi-Tenant Upgrade Broke Login

1. **User model uses `BelongsToTenant`**
   - `App\Models\User` imports `App\Traits\BelongsToTenant`.
   - This means **all** queries on `User` go through the tenant global scope named `'tenant'`.

2. **Auth uses the Eloquent provider**
   - The default Eloquent provider builds its queries using `User::query()`, which **includes global scopes**.
   - During `retrieveByCredentials`, the tenant scope can run and filter results based on the current authenticated user’s tenant.

3. **Subtle interaction that can cause login failures**
   - In theory, for a fresh guest session:
     - `auth()->check()` is false → `BelongsToTenant` scope returns early → no tenant filtering → login works.
   - But in practice, after adding multi-tenancy:
     - There may be cases where:
       - A session/remember-me cookie is restored **before** the login request, or
       - Some middleware/call triggers `auth()->user()` earlier in the request lifecycle.
     - In those situations:
       - `auth()->check()` becomes true for some user.
       - The tenant scope becomes active (and potentially fail-closed if `tenant_id` is empty).
       - `retrieveByCredentials` can no longer see the intended user row, so:
         - `$user` is `null` → `auth.failed` → login error, even with correct credentials.

4. **Additional tenant-data issues**
   - `DemoUsersSeeder` creates admin/client demo users **without** setting `tenant_id`.
   - A backfill migration (not shown here but implied) sets `tenant_id` for existing users, but **seeders may run later**, leaving seeded demo users with `tenant_id = null`.
   - The `BelongsToTenant` scope is explicitly fail-closed when `tenant_id` is empty:
     - `whereRaw('1 = 0')` → all tenant-scoped queries for that user return empty after login.

### 4. Concrete Root Causes (Most Probable)

- **Root Cause A – Auth lookups are tenant-scoped**
  - The login credential lookup uses `Auth::getProvider()->retrieveByCredentials()` which queries `User` with the tenant global scope.
  - If `auth()->check()` is true or the current authenticated user has an incompatible tenant, the intended user cannot be found.
  - Result: **“These credentials do not match our records.”** (or similar login error) even for valid users.

- **Root Cause B – Demo users or some accounts have `tenant_id = null`**
  - `DemoUsersSeeder` does not set `tenant_id` at all.
  - After login, such users hit the fail-closed branch in the scope (`whereRaw('1 = 0')`) and effectively cannot see any tenant-scoped data.
  - This may be perceived as a “login problem” because the dashboard appears broken/empty immediately after logging in.

### 5. Fixes Implemented / Recommended

#### 5.1 Tenant-aware `User` provider (main login fix)

- **Goal**: Authentication lookups should **ignore** the tenant global scope while still keeping tenant scoping everywhere else.
- **Approach**:
  - Create `App\Auth\TenantAwareUserProvider` that extends Laravel’s `EloquentUserProvider`.
  - Override:
    - `retrieveById($identifier)`
    - `retrieveByCredentials(array $credentials)`
  - In both, use `User::withoutGlobalScope('tenant')` when querying, so the tenant scope does **not** interfere with:
    - Loading the user from the session.
    - Looking up the user by email during login.
- **Wiring**:
  - Register the provider in `AppServiceProvider::boot()` via:
    - `Auth::provider('eloquent-tenant-aware', ...)`.
  - Change `config/auth.php`:
    - `providers.users.driver` from `eloquent` → `eloquent-tenant-aware`.

Result:  
- Login (`/login` and `/admin-secret-url`) now uses a **tenant-agnostic** provider while the rest of the app keeps tenant scoping via `BelongsToTenant`.

#### 5.2 Ensure seeded users get a `tenant_id`

- Update `Database\Seeders\DemoUsersSeeder`:
  - Resolve a default active tenant:
    - `$tenantId = Tenant::query()->where('is_active', true)->value('id');`
  - Set `'tenant_id' => $tenantId` for both admin and client demo users (when a tenant exists).
- This prevents:
  - Seeded admin/client users from having `tenant_id = null`.
  - The fail-closed branch of the tenant scope from triggering for them after login.

#### 5.3 Safer registration tenant assignment

- `RegisteredUserController@store()` currently does:
  - `'tenant_id' => Tenant::query()->where('is_active', true)->value('id')`
- Recommendation:
  - Guard against the case where there is **no active tenant** and either:
    - Block registration with a clear validation error, or
    - Create/assign a default tenant explicitly.

### 6. Summary for Future Work

- **Auth & Tenancy Boundary**:
  - Keep authentication lookups (finding user by email/ID) **outside** tenant scoping.
  - Apply tenant scoping at the model/query level only for business data (cars, reservations, payments, tickets, messages, and users when listing/managing them as an admin).
- **Key files touched for the fix**:
  - `config/auth.php` – switch provider driver to `eloquent-tenant-aware`.
  - `app/Providers/AppServiceProvider.php` – register the custom provider.
  - `app/Auth/TenantAwareUserProvider.php` – new class handling scope-free auth lookups.
  - `database/seeders/DemoUsersSeeder.php` – ensure seeded users are tied to a tenant.

With these changes, login should behave like the original single-tenant version (no unexpected “credentials failed” errors), while the rest of the system continues to respect tenant boundaries.

