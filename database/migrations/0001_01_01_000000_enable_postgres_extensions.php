<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "citext"');

        // Enable PostGIS when installed on the PostgreSQL server; falls back gracefully
        // to standard composite coordinate indexing if PostGIS binaries are absent in CI.
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_available_extensions WHERE name = 'postgis') THEN
                    CREATE EXTENSION IF NOT EXISTS postgis;
                END IF;
            END
            $$;
        SQL);
    }

    public function down(): void
    {
        // Extensions are shared cluster/database primitives and are intentionally kept on rollback.
    }
};
