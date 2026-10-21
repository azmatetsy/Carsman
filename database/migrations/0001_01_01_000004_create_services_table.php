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
        Schema::create('services', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('category', 100);
            $table->string('name', 180);
            $table->string('slug', 200)->unique();
            $table->text('description')->nullable();
            $table->decimal('default_labor_hours', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['category', 'name'], 'services_category_name_unique');
            $table->index(['category', 'is_active'], 'services_category_active_idx');
            $table->index(['is_active', 'name'], 'services_active_name_idx');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE services
                ADD CONSTRAINT services_default_labor_hours_check
                CHECK (default_labor_hours IS NULL OR (default_labor_hours > 0.00 AND default_labor_hours <= 200.00))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
