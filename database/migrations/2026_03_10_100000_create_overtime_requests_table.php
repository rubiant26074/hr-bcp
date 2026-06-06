<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('overtime_requests')) {
            Schema::create('overtime_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->unsignedBigInteger('requester_user_id');
                $table->date('date');
                $table->time('time_start');
                $table->time('time_end');
                $table->string('reason', 255)->nullable();
                $table->string('status', 30)->default('Pending Approval 1');
                $table->unsignedBigInteger('atasan_approved_by')->nullable();
                $table->dateTime('atasan_approved_at')->nullable();
                $table->string('atasan_signature', 120)->nullable();
                $table->unsignedBigInteger('hrd_approved_by')->nullable();
                $table->dateTime('hrd_approved_at')->nullable();
                $table->string('hrd_signature', 120)->nullable();
                $table->unsignedBigInteger('rejected_by')->nullable();
                $table->dateTime('rejected_at')->nullable();
                $table->string('rejected_note', 255)->nullable();
                $table->timestamps();

                $table->index(['company_id', 'employee_id']);
                $table->index(['company_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};
