<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Pph21ModuleTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_pph21_module_renders_with_payroll_data(): void
    {
        DB::table('employees')->insert([
            'id' => 1,
            'company_id' => 1,
            'nik' => 'BK01010001',
            'nik_ktp' => '3216220403740002',
            'name' => 'Budi Rubiantoro',
            'npwp' => '01.234.567.8-999.000',
            'ptkp_status' => 'K/2',
            'employment_status' => 'TETAP ALL-IN',
            'employee_type' => 'Staff',
            'position' => 'Management Representative',
            'grade' => 'M/1',
            'join_date' => '2024-01-01',
            'contract_end' => null,
            'photo_path' => 'uploads/employees/test.jpg',
            'ktp_path' => 'uploads/employees/docs/test.jpg',
            'ijazah_path' => 'uploads/employees/docs/test.pdf',
            'surat_lamaran_path' => null,
            'kk_path' => 'uploads/employees/docs/test.jpg',
            'npwp_path' => 'uploads/employees/docs/test.jpg',
            'skck_path' => null,
            'address_ktp' => 'Bekasi',
        ]);

        DB::table('payroll_period')->insert([
            'id' => 1,
            'month' => 1,
            'year' => 2026,
            'status' => 'Closed',
        ]);

        DB::table('payroll')->insert([
            'period_id' => 1,
            'employee_id' => 1,
            'company_id' => 1,
            'basic_salary' => 10000000,
            'a2_overtime' => 500000,
            'a3_meal' => 300000,
            'a4_transport' => 300000,
            'a5_performance' => 250000,
            'a6_position' => 400000,
            'a7_family' => 200000,
            'a8_communication' => 100000,
            'a9_other' => 0,
            'a10_thr' => 0,
            'a11_bonus' => 0,
            'a12_tax_allowance' => 0,
            'a13_bpjs_allowance' => 0,
            'total_penerimaan' => 12050000,
            'b1_loan' => 0,
            'b2_absence' => 0,
            'b3_subsidy' => 0,
            'b4_bpjs_health' => 100000,
            'b5_jht' => 200000,
            'b6_jp' => 100000,
            'b7_pph21' => 241000,
            'b8_other' => 0,
            'total_potongan' => 541000,
            'gaji_bersih' => 11509000,
        ]);

        $this->withSession([
            'user' => [
                'id' => 1,
                'company_id' => null,
                'employee_id' => null,
                'name' => 'Super Admin',
                'email' => 'admin@local.test',
                'role' => 'Super Admin',
            ],
            'company_id' => 1,
        ])->get('/payroll/pph21?period_id=1')
            ->assertOk()
            ->assertSee('Modul PPh21')
            ->assertSee('Budi Rubiantoro');
    }
}
