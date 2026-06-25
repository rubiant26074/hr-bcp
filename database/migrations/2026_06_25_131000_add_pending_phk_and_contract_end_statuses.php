<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_active_statuses')) {
            return;
        }

        DB::table('employee_active_statuses')->updateOrInsert(
            ['company_id' => 0, 'status_name' => 'Dalam Proses PHK'],
            [
                'is_archive' => 0,
                'sort_order' => 38,
                'note' => 'Karyawan PHK tetapi payroll terakhir belum selesai.',
            ]
        );

        DB::table('employee_active_statuses')->updateOrInsert(
            ['company_id' => 0, 'status_name' => 'Dalam Proses Habis Kontrak'],
            [
                'is_archive' => 0,
                'sort_order' => 48,
                'note' => 'Karyawan habis kontrak tetapi payroll terakhir belum selesai.',
            ]
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('employee_active_statuses')) {
            return;
        }

        DB::table('employee_active_statuses')
            ->where('company_id', 0)
            ->whereIn('status_name', ['Dalam Proses PHK', 'Dalam Proses Habis Kontrak'])
            ->delete();
    }
};
