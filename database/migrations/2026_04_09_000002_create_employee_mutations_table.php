<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_mutations')) {
            Schema::create('employee_mutations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('from_company_id');
                $table->unsignedBigInteger('to_company_id');
                $table->string('from_nik', 50)->nullable();
                $table->string('to_nik', 50)->nullable();
                $table->dateTime('mutated_at')->nullable();
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->string('note', 255)->nullable();

                $table->index(['from_company_id', 'employee_id'], 'idx_employee_mutations_from_company_employee');
                $table->index(['to_company_id', 'employee_id'], 'idx_employee_mutations_to_company_employee');
                $table->index(['employee_id', 'id'], 'idx_employee_mutations_employee_id_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_mutations');
    }
};

