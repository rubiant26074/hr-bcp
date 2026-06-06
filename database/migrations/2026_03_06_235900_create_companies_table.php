<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('companies')) {
            return;
        }

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('company_name', 255);
            $table->string('company_code', 20)->unique();
            $table->string('address', 255)->nullable();
            $table->string('npwp', 50)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->decimal('bpjs_health_pct', 5, 2)->default(1);
            $table->decimal('bpjs_jht_pct', 5, 2)->default(2);
            $table->decimal('bpjs_jp_pct', 5, 2)->default(1);
            $table->unsignedTinyInteger('payroll_day')->default(25);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
