<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('approval_settings')) {
            return;
        }

        Schema::create('approval_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('module_key', 50);
            $table->string('step1_type', 20)->default('atasan');
            $table->string('step1_role', 50)->nullable();
            $table->string('step2_role', 50)->default('HR');
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['company_id', 'module_key'], 'uniq_company_module');
            $table->index(['company_id', 'module_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_settings');
    }
};
