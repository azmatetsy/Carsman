<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_services', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained('services')->restrictOnDelete();
            $table->string('pricing_type', 24)->default('hourly'); // hourly | fixed | quote_only
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->decimal('fixed_price', 10, 2)->nullable();
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            // Prevent duplicate service assignment per shop
            $table->unique(['shop_id', 'service_id'], 'shop_services_shop_service_unique');

            // Composite unique index so quotes/bookings can reference (shop_id, shop_service_id)
            // to guarantee at the DB constraint level that a booking cannot reference another shop's service row
            $table->unique(['shop_id', 'id'], 'shop_services_tenant_id_unique');

            $table->index(['service_id', 'is_active'], 'shop_services_service_active_idx');
            $table->index(['shop_id', 'is_active'], 'shop_services_shop_active_idx');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE shop_services
                ADD CONSTRAINT shop_services_pricing_type_check
                    CHECK (pricing_type IN ('hourly', 'fixed', 'quote_only')),
                ADD CONSTRAINT shop_services_hourly_rate_non_negative_check
                    CHECK (hourly_rate IS NULL OR (hourly_rate >= 0.00 AND hourly_rate <= 9999.99)),
                ADD CONSTRAINT shop_services_fixed_price_non_negative_check
                    CHECK (fixed_price IS NULL OR (fixed_price >= 0.00 AND fixed_price <= 999999.99)),
                ADD CONSTRAINT shop_services_estimated_minutes_check
                    CHECK (estimated_minutes IS NULL OR (estimated_minutes > 0 AND estimated_minutes <= 14400)),
                ADD CONSTRAINT shop_services_pricing_consistency_check
                    CHECK (
                        (pricing_type = 'hourly' AND fixed_price IS NULL)
                        OR (pricing_type = 'fixed' AND fixed_price IS NOT NULL)
                        OR (pricing_type = 'quote_only' AND fixed_price IS NULL AND hourly_rate IS NULL)
                    )
            SQL);

            // Enable PostgreSQL Row-Level Security (RLS) for strict tenant isolation
            DB::statement('ALTER TABLE shop_services ENABLE ROW LEVEL SECURITY');
            DB::statement(<<<'SQL'
                CREATE POLICY shop_services_tenant_isolation_policy ON shop_services
                USING (
                    current_setting('app.current_shop_id', true) IS NULL
                    OR current_setting('app.current_shop_id', true) = ''
                    OR shop_id = current_setting('app.current_shop_id', true)::uuid
                )
                WITH CHECK (
                    current_setting('app.current_shop_id', true) IS NULL
                    OR current_setting('app.current_shop_id', true) = ''
                    OR shop_id = current_setting('app.current_shop_id', true)::uuid
                )
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_services');
    }
};
