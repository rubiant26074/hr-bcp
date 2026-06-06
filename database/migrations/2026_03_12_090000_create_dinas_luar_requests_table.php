<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dinas_luar_requests')) {
            Schema::create('dinas_luar_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('requester_user_id');
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->string('request_type', 10)->default('DLK'); // DLK / DLN
                $table->string('doc_no', 50)->nullable();
                $table->date('request_date')->nullable();
                $table->date('work_start')->nullable();
                $table->date('work_end')->nullable();
                $table->unsignedInteger('extension_no')->default(0);
                $table->string('customer', 150)->nullable();
                $table->string('work_order_no', 80)->nullable();
                $table->string('project', 150)->nullable();
                $table->string('pekerjaan', 150)->nullable();
                $table->string('lokasi', 150)->nullable();
                $table->string('country', 100)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('passport_no', 50)->nullable();
                $table->date('passport_expiry')->nullable();
                $table->string('currency', 10)->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 40)->default('Draft');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->unsignedBigInteger('rejected_by')->nullable();
                $table->dateTime('rejected_at')->nullable();
                $table->string('rejected_note', 255)->nullable();
                $table->timestamps();

                $table->index(['company_id', 'request_type']);
                $table->index(['company_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dinas_luar_requests');
    }
};
