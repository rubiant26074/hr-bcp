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
            if (!Schema::hasColumn('payroll_setting', 'overtime_mode')) {
                $table->string('overtime_mode', 10)->default('auto')->after('a2_overtime_flat');
            }
            if (!Schema::hasColumn('payroll_setting', 'overtime_manual_hours')) {
                $table->decimal('overtime_manual_hours', 8, 2)->default(0)->after('overtime_mode');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payroll_setting')) {
            return;
        }

        Schema::table('payroll_setting', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_setting', 'overtime_manual_hours')) {
                $table->dropColumn('overtime_manual_hours');
            }
            if (Schema::hasColumn('payroll_setting', 'overtime_mode')) {
                $table->dropColumn('overtime_mode');
            }
        });
    }
};

