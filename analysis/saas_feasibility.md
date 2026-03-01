# SaaS Feasibility Analysis

## 1. Feasibility Assessment
**Verdict: High Feasibility**

The current architecture (Laravel + Inertia) is well-suited for conversion to a SaaS model. The clean separation of Admin and Client routes provides a solid foundation. The main challenge will be rewriting the database queries to be "Tenant Aware" and introducing a Super Admin layer.

## 2. SaaS Terminology for this Project
-   **Super Admin**: The SaaS owner. Manages the *Rental Companies* (Tenants).
-   **Tenant (Rental Company)**: A subscriber to your SaaS. They have their own generic "Admin" dashboard.
-   **Tenant User (Client)**: A customer of a specific Rental Company.

## 3. Required Architectural Changes

### 3.1 Database Strategy: Single Database, Multi-Tenant
We will likely use a **Single Database with Tenant ID** approach. This is the most cost-effective and easiest to maintain for this scale.

**New Schema Requirements:**
1.  **`tenants` table**:
    -   `id` (UUID or BigInt)
    -   `name` (Company Name)
    -   `domain` (optional, for custom domains)
    -   `plan` (access level)
2.  **Modifications to Existing Tables**:
    -   `users`: Add `tenant_id` (User belongs to a Tenant).
    -   `cars`, `reservations`, `payments`: Add `tenant_id`.

### 3.2 Authentication & Scope
-   **Tenant Scope**: A Global Scope in Laravel will be essential. It automatically filters queries to only show data belonging to the current tenant.
    ```php
    // Example Scope
    static::addGlobalScope('tenant', function (Builder $builder) {
        $builder->where('tenant_id', session('tenant_id'));
    });
    ```
-   **Super Admin Dashboard**: A new space (e.g., `routes/super_admin.php`) to view all tenants, revenue, and system stats.

### 3.3 Routing
-   **Subdomains**: `companyA.yoursas.com`, `companyB.yoursas.com`.
-   **Path-based** (Simpler): `yoursaas.com/app/companyA/...` (Less common for SaaS, subdomains preferred).
-   **Middleware**: A `IdentifyTenant` middleware will run on every request to determine which company is being accessed.

## 4. Implementation Roadmap

### Phase 1: Foundation
1.  Create `Tenant` model and migration.
2.  Add `tenant_id` to all relevant tables.
3.  Create `TenantScope` trait.

### Phase 2: Refactoring
1.  Apply `TenantScope` to all Models (`Car`, `Reservation`, `Client`, `Payment`).
2.  Update Registration flow to assign new users to a tenant (or create a new tenant).

### Phase 3: Super Admin
1.  Create Super Admin role.
2.  Build Super Admin Dashboard to manage Tenants.

### Phase 4: Billing (Optional but key for SaaS)
1.  Integrate Stripe/Paddle for Tenant subscriptions.
