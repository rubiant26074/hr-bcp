<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function addColumns(Blueprint $table): void
    {
        $table->id();
        $table->unsignedBigInteger('employee_id');
        $table->unsignedBigInteger('period_id');
        $table->unsignedBigInteger('company_id')->nullable();
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
        $table->decimal('total_penerimaan', 12, 2)->default(0);
        $table->decimal('total_potongan', 12, 2)->default(0);
        $table->decimal('gaji_bersih', 12, 2)->default(0);
        $table->decimal('pembulatan', 12, 2)->default(0);
        $table->dateTime('created_at')->useCurrent();

        $table->unique(['period_id', 'employee_id'], 'uniq_payroll_period_employee');
        $table->index(['company_id', 'period_id']);
    }

    public function up(): void
    {
        if (Schema::hasTable('payroll_items') && !Schema::hasTable('payroll')) {
            Schema::rename('payroll_items', 'payroll');
        }

        if (!Schema::hasTable('payroll')) {
            Schema::create('payroll', function (Blueprint $table) {
                $this->addColumns($table);
            });
        }

        if (!Schema::hasColumn('payroll', 'company_id')) {
            Schema::table('payroll', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->after('period_id');
                $table->index(['company_id', 'period_id']);
            });
        }

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('UPDATE payroll p JOIN employees e ON e.id = p.employee_id SET p.company_id = e.company_id WHERE p.company_id IS NULL');
        } else {
            $rows = DB::table('payroll')->whereNull('company_id')->select('id', 'employee_id')->get();
            foreach ($rows as $row) {
                $companyId = DB::table('employees')->where('id', $row->employee_id)->value('company_id');
                if ($companyId !== null) {
                    DB::table('payroll')->where('id', $row->id)->update(['company_id' => $companyId]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll');
    }
};
