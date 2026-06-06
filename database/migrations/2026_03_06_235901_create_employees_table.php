<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees')) {
            return;
        }

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('nik', 50);
            $table->string('name', 150);
            $table->string('npwp', 50)->nullable();
            $table->string('employment_status', 100)->nullable();
            $table->string('employee_type', 50)->nullable();
            $table->string('position', 120)->nullable();
            $table->string('grade', 120)->nullable();
            $table->date('join_date')->nullable();
            $table->date('contract_end')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->string('photo_path', 255)->nullable();
            $table->string('ptkp_status', 10)->default('TK/0');

            $table->index('company_id');
            $table->index(['company_id', 'name']);
            $table->unique(['company_id', 'nik']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
