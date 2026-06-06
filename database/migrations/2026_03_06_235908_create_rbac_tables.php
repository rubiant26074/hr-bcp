<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rbac_permissions')) {
            Schema::create('rbac_permissions', function (Blueprint $table) {
                $table->string('permission_key', 64)->primary();
                $table->string('label', 150);
                $table->string('path', 255);
                $table->string('section_name', 100);
                $table->integer('sort_order')->default(0);
                $table->unique('path');
            });
        }

        if (!Schema::hasTable('rbac_role_permissions')) {
            Schema::create('rbac_role_permissions', function (Blueprint $table) {
                $table->string('role_name', 20);
                $table->string('permission_key', 64);
                $table->boolean('is_allowed')->default(true);

                $table->primary(['role_name', 'permission_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rbac_role_permissions');
        Schema::dropIfExists('rbac_permissions');
    }
};
