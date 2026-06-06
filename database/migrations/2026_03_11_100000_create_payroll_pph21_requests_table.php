<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_pph21_requests')) {
            return;
        }

        Schema::create('payroll_pph21_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('period_id');
            $table->unsignedBigInteger('requester_user_id')->nullable();
            $table->string('status', 40)->default('Pending Approval 1');
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejected_note', 255)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'period_id'], 'uniq_payroll_pph21_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_pph21_requests');
    }
};
