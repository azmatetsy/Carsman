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
        Schema::create('shops', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 180);
            $table->string('slug', 200)->unique();
            $table->string('email', 255);
            $table->string('phone', 30);
            $table->string('street', 255);
            $table->string('city', 120);
            $table->char('state', 2);
            $table->string('zip_code', 10);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('is_certified')->default(false);
            $table->boolean('is_active')->default(false);
            $table->decimal('base_labor_rate', 8, 2);
            $table->timestampsTz();
            $table->softDeletesTz();

            // Standard composite B-Tree spatial & directory lookup indexes
            $table->index(['latitude', 'longitude'], 'shops_lat_lng_idx');
            $table->index(['state', 'city', 'is_active'], 'shops_state_city_active_idx');
            $table->index(['zip_code', 'is_active'], 'shops_zip_active_idx');
            $table->index(['is_active', 'is_certified'], 'shops_active_certified_idx');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE shops
                ADD CONSTRAINT shops_latitude_range_check CHECK (latitude BETWEEN -90.0000000 AND 90.0000000),
                ADD CONSTRAINT shops_longitude_range_check CHECK (longitude BETWEEN -180.0000000 AND 180.0000000),
                ADD CONSTRAINT shops_base_labor_rate_check CHECK (base_labor_rate >= 0.00 AND base_labor_rate <= 9999.99),
                ADD CONSTRAINT shops_state_format_check CHECK (state ~ '^[A-Z]{2}$'),
                ADD CONSTRAINT shops_zip_code_format_check CHECK (zip_code ~ '^[0-9]{5}(-[0-9]{4})?$')
            SQL);

            // Add PostGIS geography(Point, 4326) column + GiST spatial index when PostGIS is enabled,
            // otherwise create a native PostgreSQL point GiST functional index for radius queries.
            DB::statement(<<<'SQL'
                DO $$
                BEGIN
                    IF EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'postgis') THEN
                        ALTER TABLE shops
                        ADD COLUMN coordinates geography(Point, 4326)
                        GENERATED ALWAYS AS (
                            ST_SetSRID(ST_MakePoint(longitude::double precision, latitude::double precision), 4326)::geography
                        ) STORED;

                        CREATE INDEX shops_coordinates_gist_idx ON shops USING GIST (coordinates);
                    ELSE
                        CREATE INDEX shops_point_gist_idx ON shops USING GIST (point(longitude, latitude));
                    END IF;
                END
                $$;
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
