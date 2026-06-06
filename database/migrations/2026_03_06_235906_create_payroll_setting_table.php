<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function addColumns(Blueprint $table): void
    {
        $table->unsignedBigInteger('employee_id')->primary();
        $table->decimal('basic_salary', 12, 2)->default(0);
        $table->decimal('a2_overtime', 12, 2)->default(0);
        $table->decimal('a2_overtime_flat', 12, 2)->default(0);
        $table->decimal('a3_meal', 12, 2)->default(0);
        $table->decimal('a4_transport', 12, 2)->default(0);
        $table->decimal('a5_performance', 12, 2)->default(0);
        $table->decimal('a6_position', 12, 2)->default(0);
        $table->decimal('a7_family', 12, 2)->default(0);
        $table->decimal('a8_communication', 12, 2)->default(0);
        $table->decimal('a9_other', 12, 2)->default(0);
        $table->decimal('a10_thr', 12, 2)->default(0);
        $table->decimal('a11_bonus', 12, 2)->default(0);
        $table->decimal('a12_tax_allowance', 12, 2)->default(0);
        $table->decimal('a13_bpjs_allowance', 12, 2)->default(0);
        $table->decimal('b1_loan', 12, 2)->default(0);
        $table->decimal('b2_absence', 12, 2)->default(0);
        $table->decimal('b3_subsidy', 12, 2)->default(0);
        $table->decimal('b4_bpjs_health', 12, 2)->default(0);
        $table->decimal('b5_jht', 12, 2)->default(0);
        $table->decimal('b6_jp', 12, 2)->default(0);
        $table->decimal('b7_pph21', 12, 2)->default(0);
        $table->decimal('b8_other', 12, 2)->default(0);
        $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
    }

    public function up(): void
    {
        if (Schema::hasTable('employee_payroll_settings') && !Schema::hasTable('payroll_setting')) {
            Schema::rename('employee_payroll_settings', 'payroll_setting');
        }

        if (Schema::hasTable('payroll_setting')) {
            return;
        }

        Schema::create('payroll_setting', function (Blueprint $table) {
            $this->addColumns($table);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_setting')) {
            Schema::drop('payroll_setting');
        }
    }
};
