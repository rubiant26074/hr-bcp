<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_setting') && !Schema::hasColumn('payroll_setting', 'a12_rapel_gaji')) {
            Schema::table('payroll_setting', function (Blueprint $table) {
                $table->decimal('a12_rapel_gaji', 12, 2)->default(0)->after('a11_bonus');
            });
        }

        if (Schema::hasTable('payroll') && !Schema::hasColumn('payroll', 'a12_rapel_gaji')) {
            Schema::table('payroll', function (Blueprint $table) {
                $table->decimal('a12_rapel_gaji', 12, 2)->default(0)->after('a11_bonus');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_setting') && Schema::hasColumn('payroll_setting', 'a12_rapel_gaji')) {
            Schema::table('payroll_setting', function (Blueprint $table) {
                $table->dropColumn('a12_rapel_gaji');
            });
        }

        if (Schema::hasTable('payroll') && Schema::hasColumn('payroll', 'a12_rapel_gaji')) {
            Schema::table('payroll', function (Blueprint $table) {
                $table->dropColumn('a12_rapel_gaji');
            });
        }
    }
};
