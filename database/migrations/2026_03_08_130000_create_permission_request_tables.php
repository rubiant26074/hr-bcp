<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('absence_requests')) {
            Schema::create('absence_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->unsignedBigInteger('requester_user_id');
                $table->string('request_type', 20);
                $table->date('date_start');
                $table->date('date_end');
                $table->string('reason', 255)->nullable();
                $table->string('attachment_path', 255)->nullable();
                $table->string('doctor_note_path', 255)->nullable();
                $table->string('status', 30)->default('Pending Atasan');
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

        if (!Schema::hasTable('out_office_requests')) {
            Schema::create('out_office_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->unsignedBigInteger('requester_user_id');
                $table->date('date');
                $table->time('time_start');
                $table->time('time_end');
                $table->string('destination', 150)->nullable();
                $table->string('reason', 255)->nullable();
                $table->string('status', 30)->default('Pending Atasan');
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
        Schema::dropIfExists('out_office_requests');
        Schema::dropIfExists('absence_requests');
    }
};
