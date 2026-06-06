<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payroll_period')) {
            return;
        }

        Schema::table('payroll_period', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_period', 'period_type')) {
                $table->string('period_type', 20)->default('month_year')->after('year');
            }
            if (!Schema::hasColumn('payroll_period', 'start_date')) {
                $table->date('start_date')->nullable()->after('period_type');
            }
            if (!Schema::hasColumn('payroll_period', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payroll_period')) {
            return;
        }

        Schema::table('payroll_period', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_period', 'end_date')) {
                $table->dropColumn('end_date');
            }
            if (Schema::hasColumn('payroll_period', 'start_date')) {
                $table->dropColumn('start_date');
            }
            if (Schema::hasColumn('payroll_period', 'period_type')) {
                $table->dropColumn('period_type');
            }
        });
    }
};
