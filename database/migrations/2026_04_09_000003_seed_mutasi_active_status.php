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

        $exists = DB::table('employee_active_statuses')
            ->where('company_id', 0)
            ->where('status_name', 'Mutasi')
            ->exists();

        if (!$exists) {
            DB::table('employee_active_statuses')->insert([
                'company_id' => 0,
                'status_name' => 'Mutasi',
                'is_archive' => 1,
                'sort_order' => 25,
                'note' => null,
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('employee_active_statuses')) {
            return;
        }

        DB::table('employee_active_statuses')
            ->where('company_id', 0)
            ->where('status_name', 'Mutasi')
            ->delete();
    }
};

