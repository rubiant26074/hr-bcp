<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_active_statuses')) {
            Schema::create('employee_active_statuses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->default(0);
                $table->string('status_name', 100);
                $table->boolean('is_archive')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('note', 255)->nullable();

                $table->unique(['company_id', 'status_name'], 'uniq_employee_active_status_company_name');
            });
        }

        $hasAny = DB::table('employee_active_statuses')->count() > 0;
        if (!$hasAny) {
            DB::table('employee_active_statuses')->insert([
                ['company_id' => 0, 'status_name' => 'Active', 'is_archive' => 0, 'sort_order' => 10, 'note' => null],
                ['company_id' => 0, 'status_name' => 'Non Active', 'is_archive' => 0, 'sort_order' => 20, 'note' => null],
                ['company_id' => 0, 'status_name' => 'Mutasi', 'is_archive' => 1, 'sort_order' => 25, 'note' => null],
                ['company_id' => 0, 'status_name' => 'Resign', 'is_archive' => 1, 'sort_order' => 30, 'note' => null],
                ['company_id' => 0, 'status_name' => 'PHK', 'is_archive' => 1, 'sort_order' => 40, 'note' => null],
                ['company_id' => 0, 'status_name' => 'Habis Kontrak', 'is_archive' => 1, 'sort_order' => 50, 'note' => null],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_active_statuses');
    }
};
