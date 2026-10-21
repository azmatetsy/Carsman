<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_vehicles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->string('vin', 17)->nullable();
            $table->string('nickname', 100)->nullable();
            $table->unsignedInteger('mileage')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestampsTz();

            $table->unique(['user_id', 'vehicle_id', 'vin'], 'customer_vehicles_user_vehicle_vin_unique');
            $table->index(['user_id', 'is_primary'], 'customer_vehicles_user_primary_idx');
        });

        Schema::create('bookings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignUuid('service_id')->constrained('services')->restrictOnDelete();
            $table->uuid('shop_quote_id')->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->string('status', 24)->default(BookingStatus::Requested->value);
            $table->date('preferred_date');
            $table->string('preferred_time_slot', 32)->default('morning'); // morning | afternoon | dropoff
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('alternate_proposed_at')->nullable();
            $table->string('customer_name', 150);
            $table->string('customer_email', 255);
            $table->string('customer_phone', 30);
            $table->text('issue_description')->nullable();
            $table->text('shop_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestampsTz();

            // Composite unique key so booking_status_events can enforce same-shop FK
            $table->unique(['shop_id', 'id'], 'bookings_tenant_id_unique');

            // Composite FK guaranteeing a booking can ONLY reference a quote belonging to the exact same shop
            $table->foreign(['shop_id', 'shop_quote_id'], 'bookings_shop_quote_tenant_fk')
                ->references(['shop_id', 'id'])
                ->on('shop_quotes')
                ->nullOnDelete();

            $table->index(['shop_id', 'status', 'preferred_date'], 'bookings_shop_status_date_idx');
            $table->index(['customer_id', 'created_at'], 'bookings_customer_created_idx');
        });

        Schema::create('booking_status_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->uuid('booking_id');
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->text('note')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            // Composite tenant FK ensuring status events cannot cross shop boundaries
            $table->foreign(['shop_id', 'booking_id'], 'booking_events_shop_booking_tenant_fk')
                ->references(['shop_id', 'id'])
                ->on('bookings')
                ->cascadeOnDelete();

            $table->index(['booking_id', 'created_at'], 'booking_events_booking_created_idx');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            $allowedStatuses = implode("', '", BookingStatus::values());
            DB::statement(<<<SQL
                ALTER TABLE bookings
                ADD CONSTRAINT bookings_status_check
                    CHECK (status IN ('{$allowedStatuses}')),
                ADD CONSTRAINT bookings_time_slot_check
                    CHECK (preferred_time_slot IN ('morning', 'afternoon', 'dropoff'))
            SQL);

            DB::statement('ALTER TABLE bookings ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE bookings FORCE ROW LEVEL SECURITY');
            DB::statement(<<<'SQL'
                CREATE POLICY bookings_tenant_isolation_policy ON bookings
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

            DB::statement('ALTER TABLE booking_status_events ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE booking_status_events FORCE ROW LEVEL SECURITY');
            DB::statement(<<<'SQL'
                CREATE POLICY booking_events_tenant_isolation_policy ON booking_status_events
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
        Schema::dropIfExists('booking_status_events');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('customer_vehicles');
    }
};
