# QA Verification & Acceptance Report — Phases `AUTO-01`, `AUTO-02`, and `AUTO-03`

> **Baseline Compliance:** This report follows the frozen QA gate format specified in `docs/automotive_platform_master_plan.html`. All code is strictly typed (`declare(strict_types=1);`) and committed on branch `main` (`d7ee837` and `617a409`).

---

## 1. Executive Release & Commit Ledger

| Field | Value |
| :--- | :--- |
| **Project** | RepairPal-Style US Automotive Repair Marketplace |
| **Target Repository** | `https://github.com/azmatetsy/Carsman.git` |
| **Branch** | `main` |
| **Commit 1 (`AUTO-01`)** | `d7ee837` — *feat(auto-01): scaffold Laravel/Inertia/Filament panels and PostgreSQL core migrations (AUTO-01-T01..T03)* |
| **Commit 2 (`AUTO-02` & `AUTO-03`)** | `617a409` — *feat(auto-02,auto-03): implement Filament Admin Panel resources and Multi-Tenant Provider Panel* |
| **Scope Covered** | `AUTO-01` (Foundation & Tenant Isolation), `AUTO-02` (`/admin` Panel), `AUTO-03` (`/provider` Multi-Tenant Panel) |

---

## 2. Multi-Layer Security & Tenant Isolation Architecture

1. **Layer 1 — Panel Guard & Role Gate (`User::canAccessPanel()`)**:
   - Denies access immediately if `$user->is_active === false`.
   - Restricts `/admin` strictly to `UserRole::Admin`.
   - Restricts `/provider` strictly to `UserRole::ShopOwner` and `UserRole::ShopStaff`.
2. **Layer 2 — Tenant Boundary Gate (`User::canAccessTenant()`)**:
   - Verifies both the `User` and target `Shop` are `is_active === true` and that an active pivot row exists in `shop_members`.
3. **Layer 3 — Eloquent Global Scope & Lifecycle Guard (`BelongsToShop` & `ShopTenantScope`)**:
   - Automatically filters queries by `shop_id`.
   - Throws `RuntimeException` on creation if `shop_id` is missing, and blocks any `shop_id` reassignment (`isDirty('shop_id')`) on update.
4. **Layer 4 — PostgreSQL Row-Level Security (RLS) & Composite Keys (`EnforcePostgresShopTenantContext`)**:
   - Sets PostgreSQL session configuration `app.current_shop_id` on every tenant request.
   - Enforces `ENABLE ROW LEVEL SECURITY` policies on `shop_members`, `shop_services`, `shop_availability_slots`, and `shop_verification_documents`.
   - Enforces composite unique keys `(shop_id, id)` on tenant tables so downstream foreign keys cannot reference records across different shops.

---

## 3. Database Schema & Constraint Audit (`AUTO-01-T03` & `AUTO-03`)

| Migration File | Table(s) | Key Constraints, RLS & Spatial Indexes |
| :--- | :--- | :--- |
| `0001_01_01_000000_enable_postgres_extensions.php` | PostgreSQL Extensions | Enables `pgcrypto`, `citext`, and conditional `postgis` extension. |
| `0001_01_01_000001_create_users_table.php` | `users`, `password_reset_tokens`, `sessions` | UUID PK, `users_role_check` (`customer`, `shop_owner`, `shop_staff`, `admin`), composite index `(role, is_active)`. |
| `0001_01_01_000002_create_shops_table.php` | `shops` | UUID PK, `slug` unique, PostGIS `geography(Point, 4326)` generated column + `GiST` index (`shops_coordinates_gist_idx`) with fallback native `GiST` point index + B-tree `(latitude, longitude)`, US state (`^[A-Z]{2}$`), ZIP (`^[0-9]{5}(-[0-9]{4})?$`), lat/lng & labor rate checks. |
| `0001_01_01_000003_create_shop_members_table.php` | `shop_members` | UUID PK, unique `(shop_id, user_id)`, composite unique `(shop_id, id)`, `shop_members_role_check` (`owner`, `manager`, `technician`), PostgreSQL RLS policy `shop_members_tenant_isolation_policy`. |
| `0001_01_01_000004_create_services_table.php` | `services` | UUID PK, unique `slug`, unique `(category, name)`, `services_difficulty_level_check` (`easy`, `moderate`, `complex`, `master`), labor hour bounds `(0.00, 200.00]`. |
| `0001_01_01_000005_create_shop_services_table.php` | `shop_services` | UUID PK, unique `(shop_id, service_id)`, composite unique `(shop_id, id)`, `shop_services_pricing_consistency_check` (`hourly` / `fixed` / `quote_only`), PostgreSQL RLS policy `shop_services_tenant_isolation_policy`. |
| `0001_01_01_000006_create_vehicles_table.php` | `vehicles` | UUID PK, unique `(year, make, model, submodel, engine)`, `vehicles_year_range_check` (`1980..2035`), `GIN` index on `spec_payload`, cascading selector indexes on `(year, make, model, is_active)`. |
| `0001_01_01_000007_create_provider_operations_tables.php` | `shop_availability_slots`, `shop_verification_documents` | UUID PKs, unique `(shop_id, day_of_week)`, `closes_at > opens_at` check, document status check (`pending`, `approved`, `rejected`), PostgreSQL RLS policies on both tables. |

---

## 4. Independent QA Test Cases (Positive & Negative Scenarios)

### 4.1 Authorization & Panel Isolation Tests
- **QA-AUTH-01**: User with `role = customer`, `shop_owner`, or `shop_staff` attempting `GET /admin` receives `403 Forbidden` via `User::canAccessPanel()`.
- **QA-AUTH-02**: User with `role = admin` and `is_active = false` attempting `GET /admin` receives `403 Forbidden`.
- **QA-AUTH-03**: Signed-in Admin editing their own record in `UserResource` cannot demote, suspend, or delete their own account.
- **QA-AUTH-04**: User with `role = customer` attempting `GET /provider` receives `403 Forbidden`.

### 4.2 Multi-Tenant Isolation & Cross-Shop Negative Tests
- **QA-TENANT-01**: Owner of `Shop A` navigating to `/provider/shops/{shop-b-slug}` is rejected by `User::canAccessTenant()`.
- **QA-TENANT-02**: `Shop A` querying `ShopService` or `ShopMember` inside `/provider/shops/{shop-a-slug}` returns only `Shop A` rows via both `ShopTenantScope` and PostgreSQL RLS.
- **QA-TENANT-03**: Attempting to mutate `shop_id` on an existing tenant record throws `RuntimeException` in `BelongsToShop`.
- **QA-TENANT-04**: Technician attempting to open `EditShopProfile` is denied by `EditShopProfile::canView()`.

### 4.3 Database Constraint & Data Integrity Tests
- **QA-DB-01**: Inserting `shops` row with out-of-range coordinates or invalid US state/ZIP is rejected by PostgreSQL `CHECK` constraints.
- **QA-DB-02**: Inserting `shop_services` row with `pricing_type = 'fixed'` and `fixed_price = NULL` is rejected by `shop_services_pricing_consistency_check`.
- **QA-DB-03**: Inserting `shop_services` row with `pricing_type = 'quote_only'` and `fixed_price = 150.00` is rejected by `shop_services_pricing_consistency_check`.
- **QA-DB-04**: Admin clicking `"Verify Shop"` in `ShopResource` opens confirmation modal and toggles `is_certified`.
