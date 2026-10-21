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
        Schema::create('shop_availability_slots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0 = Sunday .. 6 = Saturday
            $table->time('opens_at');
            $table->time('closes_at');
            $table->unsignedSmallInteger('max_concurrent_bookings')->default(2);
            $table->boolean('is_closed')->default(false);
            $table->timestampsTz();

            $table->unique(['shop_id', 'day_of_week'], 'shop_availability_shop_day_unique');
            $table->unique(['shop_id', 'id'], 'shop_availability_tenant_id_unique');
        });

        Schema::create('shop_verification_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignUuid('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_type', 60); // business_license | garage_liability_insurance | ase_certification | epa_permit
            $table->string('file_path', 500);
            $table->string('status', 24)->default('pending'); // pending | approved | rejected
            $table->text('rejection_reason')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['shop_id', 'id'], 'shop_verification_docs_tenant_id_unique');
            $table->index(['shop_id', 'status'], 'shop_verification_docs_shop_status_idx');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE shop_availability_slots
                ADD CONSTRAINT shop_availability_day_check CHECK (day_of_week BETWEEN 0 AND 6),
                ADD CONSTRAINT shop_availability_capacity_check CHECK (max_concurrent_bookings BETWEEN 1 AND 100),
                ADD CONSTRAINT shop_availability_hours_check CHECK (is_closed = true OR closes_at > opens_at)
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE shop_verification_documents
                ADD CONSTRAINT shop_verification_docs_status_check
                CHECK (status IN ('pending', 'approved', 'rejected'))
            SQL);

            DB::statement('ALTER TABLE shop_availability_slots ENABLE ROW LEVEL SECURITY');
            DB::statement(<<<'SQL'
                CREATE POLICY shop_availability_tenant_isolation_policy ON shop_availability_slots
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

            DB::statement('ALTER TABLE shop_verification_documents ENABLE ROW LEVEL SECURITY');
            DB::statement(<<<'SQL'
                CREATE POLICY shop_verification_docs_tenant_isolation_policy ON shop_verification_documents
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
        Schema::dropIfExists('shop_verification_documents');
        Schema::dropIfExists('shop_availability_slots');
    }
};
