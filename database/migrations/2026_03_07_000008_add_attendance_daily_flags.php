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
            if (!Schema::hasColumn('attendance_daily', 'no_overtime_permit')) {
                $table->boolean('no_overtime_permit')->default(false)->after('overtime_hours');
            }
            if (!Schema::hasColumn('attendance_daily', 'is_leave_excused')) {
                $table->boolean('is_leave_excused')->default(false)->after('no_overtime_permit');
            }
            if (!Schema::hasColumn('attendance_daily', 'is_sick_doctor_excused')) {
                $table->boolean('is_sick_doctor_excused')->default(false)->after('is_leave_excused');
            }
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
            $drops = [];
            foreach (['no_overtime_permit', 'is_leave_excused', 'is_sick_doctor_excused', 'is_special_leave_excused'] as $column) {
                if (Schema::hasColumn('attendance_daily', $column)) {
                    $drops[] = $column;
                }
            }
            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
