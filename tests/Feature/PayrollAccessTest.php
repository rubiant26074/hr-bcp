<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PayrollAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('company_code')->nullable();
            $table->string('logo_path')->nullable();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('nik');
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('grade')->nullable();
        });

        Schema::create('payroll_period', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->string('status')->default('Draft');
        });

        Schema::create('payroll', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('period_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('company_id');
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('total_penerimaan', 15, 2)->default(0);
            $table->decimal('total_potongan', 15, 2)->default(0);
            $table->decimal('gaji_bersih', 15, 2)->default(0);
        });
    }

    public function test_finance_cannot_open_slip_from_other_company(): void
    {
        DB::table('companies')->insert([
            ['id' => 1, 'company_name' => 'Company A', 'company_code' => 'A'],
            ['id' => 2, 'company_name' => 'Company B', 'company_code' => 'B'],
        ]);

        DB::table('employees')->insert([
            ['id' => 10, 'company_id' => 2, 'nik' => 'EMP002', 'name' => 'Lintas Company', 'position' => 'Staff', 'grade' => 'A'],
        ]);

        DB::table('payroll_period')->insert([
            'id' => 100,
            'month' => 3,
            'year' => 2026,
            'status' => 'Closed',
        ]);

        DB::table('payroll')->insert([
            'period_id' => 100,
            'employee_id' => 10,
            'company_id' => 2,
            'basic_salary' => 1000000,
            'total_penerimaan' => 1000000,
            'total_potongan' => 0,
            'gaji_bersih' => 1000000,
        ]);

        $response = $this
            ->withSession([
                'user' => [
                    'id' => 99,
                    'company_id' => 1,
                    'employee_id' => null,
                    'name' => 'Finance A',
                    'email' => 'finance@example.com',
                    'role' => 'Finance',
                ],
                'company_id' => 1,
            ])
            ->get('/payroll/slip?period_id=100&employee_id=10');

        $response->assertForbidden();
    }
}
