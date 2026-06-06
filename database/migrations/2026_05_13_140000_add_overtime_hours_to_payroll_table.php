<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payroll')) {
            return;
        }

        if (!Schema::hasColumn('payroll', 'a2_overtime_hours')) {
            Schema::table('payroll', function (Blueprint $table) {
                $table->decimal('a2_overtime_hours', 10, 2)->default(0)->after('a2_overtime');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('payroll')) {
            return;
        }

        if (Schema::hasColumn('payroll', 'a2_overtime_hours')) {
            Schema::table('payroll', function (Blueprint $table) {
                $table->dropColumn('a2_overtime_hours');
            });
        }
    }
};
