<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_device_user_maps')) {
            return;
        }

        Schema::create('attendance_device_user_maps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('device_user_id', 100);
            $table->unsignedBigInteger('employee_id');
            $table->timestamps();

            $table->unique(['company_id', 'device_user_id'], 'attendance_device_user_maps_unique');
            $table->index(['company_id', 'employee_id'], 'attendance_device_user_maps_company_employee_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_device_user_maps');
    }
};

