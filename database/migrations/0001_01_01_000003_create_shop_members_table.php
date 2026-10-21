<?php

declare(strict_types=1);

use App\Enums\ShopMemberRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 32)->default(ShopMemberRole::Technician->value);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('joined_at')->useCurrent();
            $table->timestampsTz();

            // Prevent duplicate membership rows for the same user inside a single shop tenant
            $table->unique(['shop_id', 'user_id'], 'shop_members_shop_user_unique');

            // Composite key enabling downstream tables to enforce same-tenant FK references (shop_id, member_id)
            $table->unique(['shop_id', 'id'], 'shop_members_tenant_id_unique');

            $table->index(['user_id', 'is_active'], 'shop_members_user_active_idx');
            $table->index(['shop_id', 'role', 'is_active'], 'shop_members_shop_role_active_idx');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            $allowedRoles = implode("', '", ShopMemberRole::values());
            DB::statement("ALTER TABLE shop_members ADD CONSTRAINT shop_members_role_check CHECK (role IN ('{$allowedRoles}'))");

            // Enable PostgreSQL Row-Level Security (RLS) to prevent cross-shop membership leakage
            DB::statement('ALTER TABLE shop_members ENABLE ROW LEVEL SECURITY');
            DB::statement(<<<'SQL'
                CREATE POLICY shop_members_tenant_isolation_policy ON shop_members
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
        Schema::dropIfExists('shop_members');
    }
};
