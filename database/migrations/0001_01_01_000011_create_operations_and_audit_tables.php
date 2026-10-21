<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->string('action', 120);
            $table->string('auditable_type', 150);
            $table->uuid('auditable_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['shop_id', 'created_at'], 'audit_logs_shop_created_idx');
            $table->index(['auditable_type', 'auditable_id'], 'audit_logs_target_idx');
        });

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 120)->unique();
            $table->jsonb('value');
            $table->boolean('is_public')->default(false);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('audit_logs');
    }
};
