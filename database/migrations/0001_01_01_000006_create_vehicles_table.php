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
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('year');
            $table->string('make', 100);
            $table->string('model', 120);
            $table->string('submodel', 120)->default('');
            $table->string('engine', 150)->default('');
            $table->string('transmission', 100)->nullable();
            $table->string('drivetrain', 60)->nullable();
            $table->string('fuel_type', 60)->nullable();
            $table->unsignedInteger('nhtsa_make_id')->nullable();
            $table->unsignedInteger('nhtsa_model_id')->nullable();
            $table->jsonb('spec_payload')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            // Deterministic uniqueness for YMM + Submodel + Engine cache
            $table->unique(
                ['year', 'make', 'model', 'submodel', 'engine'],
                'vehicles_ymm_submodel_engine_unique',
            );

            // Fast cascading selector indexes (Year -> Make -> Model -> Submodel/Engine)
            $table->index(['year', 'make', 'model', 'is_active'], 'vehicles_year_make_model_active_idx');
            $table->index(['make', 'model', 'year'], 'vehicles_make_model_year_idx');
            $table->index(['nhtsa_make_id', 'nhtsa_model_id'], 'vehicles_nhtsa_ids_idx');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE vehicles
                ADD CONSTRAINT vehicles_year_range_check
                CHECK (year BETWEEN 1980 AND 2035)
            SQL);

            DB::statement('CREATE INDEX vehicles_spec_payload_gin_idx ON vehicles USING GIN (spec_payload)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
