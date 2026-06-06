<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('attendance_daily')) {
            return;
        }

        Schema::table('attendance_daily', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_daily', 'is_special_leave_excused')) {
                $table->boolean('is_special_leave_excused')->default(false)->after('is_sick_doctor_excused');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('attendance_daily')) {
            return;
        }

        Schema::table('attendance_daily', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_daily', 'is_special_leave_excused')) {
                $table->dropColumn('is_special_leave_excused');
            }
        });
    }
};

