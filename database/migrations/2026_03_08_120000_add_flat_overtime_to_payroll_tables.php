<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_setting') && !Schema::hasColumn('payroll_setting', 'a2_overtime_flat')) {
            Schema::table('payroll_setting', function (Blueprint $table) {
                $table->decimal('a2_overtime_flat', 12, 2)->default(0)->after('a2_overtime');
            });
        }

        if (Schema::hasTable('payroll') && !Schema::hasColumn('payroll', 'a2_overtime_flat')) {
            Schema::table('payroll', function (Blueprint $table) {
                $table->decimal('a2_overtime_flat', 12, 2)->default(0)->after('a2_overtime');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll') && Schema::hasColumn('payroll', 'a2_overtime_flat')) {
            Schema::table('payroll', function (Blueprint $table) {
                $table->dropColumn('a2_overtime_flat');
            });
        }

        if (Schema::hasTable('payroll_setting') && Schema::hasColumn('payroll_setting', 'a2_overtime_flat')) {
            Schema::table('payroll_setting', function (Blueprint $table) {
                $table->dropColumn('a2_overtime_flat');
            });
        }
    }
};
