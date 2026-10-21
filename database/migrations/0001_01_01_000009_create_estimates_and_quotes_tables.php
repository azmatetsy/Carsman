<?php

declare(strict_types=1);

use App\Enums\QuoteStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimate_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignUuid('service_id')->constrained('services')->restrictOnDelete();
            $table->string('zip_code', 10);
            $table->text('issue_description')->nullable();
            $table->boolean('is_supported_estimate')->default(false);
            $table->string('source_version', 64)->default('RP-US-PILOT-v1.0');
            $table->unsignedSmallInteger('participating_shops_count')->default(0);
            $table->decimal('estimated_labor_hours', 5, 2)->nullable();
            $table->decimal('estimated_labor_low', 10, 2)->nullable();
            $table->decimal('estimated_labor_high', 10, 2)->nullable();
            $table->decimal('estimated_parts_low', 10, 2)->nullable();
            $table->decimal('estimated_parts_high', 10, 2)->nullable();
            $table->decimal('estimated_fees', 10, 2)->nullable();
            $table->decimal('estimated_total_low', 10, 2)->nullable();
            $table->decimal('estimated_total_high', 10, 2)->nullable();
            $table->jsonb('assumptions_payload')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->index(['customer_id', 'created_at'], 'estimate_requests_customer_created_idx');
            $table->index(['vehicle_id', 'service_id', 'zip_code'], 'estimate_requests_lookup_idx');
        });

        Schema::create('shop_quotes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignUuid('estimate_request_id')->nullable()->constrained('estimate_requests')->nullOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignUuid('service_id')->constrained('services')->restrictOnDelete();
            $table->string('status', 24)->default(QuoteStatus::Requested->value);
            $table->string('customer_name', 150);
            $table->string('customer_email', 255);
            $table->string('customer_phone', 30)->nullable();
            $table->text('customer_issue_notes')->nullable();
            $table->decimal('labor_subtotal', 10, 2)->default(0.00);
            $table->decimal('parts_subtotal', 10, 2)->default(0.00);
            $table->decimal('shop_fees', 10, 2)->default(0.00);
            $table->decimal('tax_estimate', 10, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2)->default(0.00);
            $table->text('scope_notes')->nullable();
            $table->unsignedSmallInteger('revision_number')->default(1);
            $table->date('valid_until')->nullable();
            $table->timestampsTz();

            // Composite unique index for cross-table tenant isolation in bookings
            $table->unique(['shop_id', 'id'], 'shop_quotes_tenant_id_unique');
            $table->index(['shop_id', 'status', 'created_at'], 'shop_quotes_shop_status_idx');
            $table->index(['customer_id', 'created_at'], 'shop_quotes_customer_idx');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE estimate_requests
                ADD CONSTRAINT estimate_requests_range_consistency_check
                CHECK (
                    (is_supported_estimate = false AND estimated_total_low IS NULL AND estimated_total_high IS NULL)
                    OR (
                        is_supported_estimate = true
                        AND estimated_total_low IS NOT NULL
                        AND estimated_total_high IS NOT NULL
                        AND estimated_total_low >= 0.00
                        AND estimated_total_high >= estimated_total_low
                    )
                )
            SQL);

            $allowedQuoteStatuses = implode("', '", QuoteStatus::values());
            DB::statement(<<<SQL
                ALTER TABLE shop_quotes
                ADD CONSTRAINT shop_quotes_status_check
                    CHECK (status IN ('{$allowedQuoteStatuses}')),
                ADD CONSTRAINT shop_quotes_amounts_non_negative_check
                    CHECK (
                        labor_subtotal >= 0.00
                        AND parts_subtotal >= 0.00
                        AND shop_fees >= 0.00
                        AND tax_estimate >= 0.00
                        AND total_amount >= 0.00
                    ),
                ADD CONSTRAINT shop_quotes_revision_positive_check
                    CHECK (revision_number >= 1)
            SQL);

            DB::statement('ALTER TABLE shop_quotes ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE shop_quotes FORCE ROW LEVEL SECURITY');
            DB::statement(<<<'SQL'
                CREATE POLICY shop_quotes_tenant_isolation_policy ON shop_quotes
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
        Schema::dropIfExists('shop_quotes');
        Schema::dropIfExists('estimate_requests');
    }
};
