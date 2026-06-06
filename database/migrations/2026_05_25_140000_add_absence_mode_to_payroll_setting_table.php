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
            if (!Schema::hasColumn('payroll_setting', 'absence_mode')) {
                $table->string('absence_mode', 10)->default('auto')->after('overtime_mode');
            }
            if (!Schema::hasColumn('payroll_setting', 'manual_present_days')) {
                $table->decimal('manual_present_days', 8, 2)->default(0)->after('absence_mode');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payroll_setting')) {
            return;
        }

        Schema::table('payroll_setting', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_setting', 'manual_present_days')) {
                $table->dropColumn('manual_present_days');
            }
            if (Schema::hasColumn('payroll_setting', 'absence_mode')) {
                $table->dropColumn('absence_mode');
            }
        });
    }
};
