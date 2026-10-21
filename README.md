# RepairPal US Automotive Repair Marketplace

Modular monolith built with **Laravel (PHP 8.2+)**, **FilamentPHP v3** (`/admin` and multi-tenant `/provider` panels), **React + TypeScript + Inertia.js + Tailwind CSS**, and **PostgreSQL / PostGIS + Redis**.

## Architecture & Tenant Isolation Baseline (`AUTO-01-T01` – `AUTO-01-T03`)

- **Customer UI**: Inertia.js + React (`/`, `/account`)
- **Platform Super-Admin Panel**: Filament v3 isolated at `/admin` (`AdminPanelProvider`)
- **Shop Provider Multi-Tenant Panel**: Filament v3 isolated at `/provider/shops/{tenant:slug}` (`ProviderPanelProvider`) scoped to the `Shop` model
- **Defense-in-Depth Tenant Isolation**:
  1. **Panel & Guard Gate**: `User::canAccessPanel()` and `User::canAccessTenant()` restrict `/provider` to active `shop_owner` / `shop_staff` members of active shops.
  2. **Eloquent Global Scope & Lifecycle Guard**: `BelongsToShop` trait (`ShopTenantScope`) automatically scopes queries and blocks `shop_id` mutation.
  3. **PostgreSQL Row-Level Security (RLS)**: `EnforcePostgresShopTenantContext` middleware binds `app.current_shop_id` into PostgreSQL session configuration, backed by `ENABLE ROW LEVEL SECURITY` policies on `shop_members` and `shop_services`.
  4. **Composite Tenant Foreign Keys**: Unique `(shop_id, id)` indexes on `shop_members` and `shop_services` allow downstream transactional tables (`quotes`, `bookings`) to enforce same-shop foreign keys at the database engine level.
