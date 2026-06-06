<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('security_shift_requests')) {
            return;
        }

        Schema::create('security_shift_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('request_type', 20); // SWAP, REPLACE
            $table->unsignedBigInteger('from_employee_id')->nullable();
            $table->unsignedBigInteger('to_employee_id')->nullable();
            $table->date('work_date');
            $table->string('from_shift_code', 10)->nullable();
            $table->string('to_shift_code', 10)->nullable();
            $table->string('status', 20)->default('DRAFT'); // DRAFT, APPLIED, CANCELLED
            $table->string('reason', 255)->nullable();
            $table->string('instruction_ref', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('applied_by')->nullable();
            $table->dateTime('applied_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable()->useCurrentOnUpdate();

            $table->index(['company_id', 'work_date']);
            $table->index('status');
            $table->index('request_type');
            $table->index('from_employee_id');
            $table->index('to_employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_shift_requests');
    }
};
