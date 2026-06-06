<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('companies')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'payroll_absence_mode')) {
                $table->string('payroll_absence_mode', 10)
                    ->default('auto')
                    ->after('work_days_json');
            }
            if (!Schema::hasColumn('companies', 'payroll_manual_present_days')) {
                $table->decimal('payroll_manual_present_days', 8, 2)
                    ->default(0)
                    ->after('payroll_absence_mode');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('companies')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'payroll_manual_present_days')) {
                $table->dropColumn('payroll_manual_present_days');
            }
            if (Schema::hasColumn('companies', 'payroll_absence_mode')) {
                $table->dropColumn('payroll_absence_mode');
            }
        });
    }
};
