# Project Analysis: Real Rent Car

## 1. Executive Summary
This project is a Car Rental Management System built with **Laravel 12** and **Vue 3** (via Inertia.js). It currently operates as a single-tenant application with two distinct user roles: **Admin** and **Client**.

## 2. Technology Stack
-   **Backend**: Laravel 12 (PHP 8.2+)
-   **Frontend**: Vue 3 (Composition API), TypeScript, Tailwind CSS v4
-   **Glue**: Inertia.js (Monolithic feel with SPA experience)
-   **Database**: MySQL (Assumed based on typical Laravel stack, though SQLite is dev default)
-   **Authentication**: Laravel Fortify (likely with a custom UI or Inertia wrappers)

## 3. Architecture Overview

### 3.1 Directory Structure
-   **`app/Models`**: Contains the core business entities.
    -   `User`: The central authentication entity.
    -   `Car`: Represents the fleet.
    -   `Reservation`: Links Users (Clients) to Cars.
    -   `Payment`, `Ticket`, `Message`: Support and transaction records.
-   **`routes`**: Clear separation of concerns.
    -   `web.php`: Public facing pages (Home, Fleet, Contact).
    -   `admin.php`: Protected routes for Admins (Car management, Reservations, Reports).
    -   `client.php`: Protected routes for Clients (My Reservations, Support).
    -   `auth.php`: Authentication routes.

### 3.2 User Roles & Permissions
The system uses a simple Role-Based Access Control (RBAC) system defined in `App\Enums\UserRole`.
-   **Admin**: Full access to the `/admin` dashboard.
-   **Client**: Restricted access to the `/client` dashboard.

### 3.3 Key Workflows
1.  **Public Booking**: Guests can view fleet and make bookings (likely requires login/registration first).
2.  **Admin Management**: Admins add cars, manage reservations, and handle support tickets.
3.  **Client Dashboard**: Clients check their reservation status and communicate with support.

## 4. Current Database Schema (Inferred)
-   `users`: `id`, `name`, `email`, `role`, `is_active`, ...
-   `cars`: `id`, `make`, `model`, `details`, ...
-   `reservations`: `id`, `user_id`, `car_id`, `status`, ...
-   `payments`: `id`, `user_id`, `reservation_id`, ...
