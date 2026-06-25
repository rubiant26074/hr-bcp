<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees') && !Schema::hasColumn('employees', 'last_working_date')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->date('last_working_date')->nullable()->after('join_date');
            });
        }

        if (Schema::hasTable('employee_active_statuses')) {
            DB::table('employee_active_statuses')->updateOrInsert(
                ['company_id' => 0, 'status_name' => 'Dalam Proses Resign'],
                [
                    'is_archive' => 0,
                    'sort_order' => 28,
                    'note' => 'Karyawan sudah resign/akan resign tetapi payroll terakhir belum selesai.',
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employee_active_statuses')) {
            DB::table('employee_active_statuses')
                ->where('company_id', 0)
                ->where('status_name', 'Dalam Proses Resign')
                ->delete();
        }

        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'last_working_date')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('last_working_date');
            });
        }
    }
};
