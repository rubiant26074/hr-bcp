<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payroll_setting')) {
            return;
        }

        Schema::table('payroll_setting', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_setting', 'overtime_manual_hour_1')) {
                $table->decimal('overtime_manual_hour_1', 8, 2)->default(0)->after('overtime_manual_hours');
            }
            if (!Schema::hasColumn('payroll_setting', 'overtime_manual_hour_2')) {
                $table->decimal('overtime_manual_hour_2', 8, 2)->default(0)->after('overtime_manual_hour_1');
            }
            if (!Schema::hasColumn('payroll_setting', 'overtime_manual_holiday_8')) {
                $table->decimal('overtime_manual_holiday_8', 8, 2)->default(0)->after('overtime_manual_hour_2');
            }
            if (!Schema::hasColumn('payroll_setting', 'overtime_manual_holiday_9')) {
                $table->decimal('overtime_manual_holiday_9', 8, 2)->default(0)->after('overtime_manual_holiday_8');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payroll_setting')) {
            return;
        }

        Schema::table('payroll_setting', function (Blueprint $table) {
            foreach ([
                'overtime_manual_holiday_9',
                'overtime_manual_holiday_8',
                'overtime_manual_hour_2',
                'overtime_manual_hour_1',
            ] as $column) {
                if (Schema::hasColumn('payroll_setting', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

