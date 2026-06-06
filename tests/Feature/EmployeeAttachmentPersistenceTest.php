<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeAttachmentPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_edit_employee_without_reupload_keeps_existing_attachments(): void
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
            'photo_path' => 'uploads/employees/test-photo.jpg',
            'ktp_path' => 'uploads/employees/docs/test-ktp.jpg',
            'ijazah_path' => 'uploads/employees/docs/test-ijazah.pdf',
            'surat_lamaran_path' => 'uploads/employees/docs/test-lamaran.pdf',
            'kk_path' => 'uploads/employees/docs/test-kk.jpg',
            'npwp_path' => 'uploads/employees/docs/test-npwp.jpg',
            'skck_path' => 'uploads/employees/docs/test-skck.pdf',
            'address_ktp' => 'Bekasi',
        ]);

        DB::table('payroll_setting')->insert([
            'employee_id' => 1,
            'basic_salary' => 10000000,
            'a2_overtime' => 0,
            'a3_meal' => 0,
            'a4_transport' => 0,
            'a5_performance' => 0,
            'a6_position' => 0,
            'a7_family' => 0,
            'a8_communication' => 0,
            'a9_other' => 0,
            'a10_thr' => 0,
            'a11_bonus' => 0,
            'a12_tax_allowance' => 0,
            'a13_bpjs_allowance' => 0,
            'b1_loan' => 0,
            'b2_absence' => 0,
            'b3_subsidy' => 0,
            'b4_bpjs_health' => 0,
            'b5_jht' => 0,
            'b6_jp' => 0,
            'b7_pph21' => 0,
            'b8_other' => 0,
        ]);

        $response = $this->withSession([
            'user' => [
                'id' => 1,
                'company_id' => null,
                'employee_id' => null,
                'name' => 'Super Admin',
                'email' => 'admin@local.test',
                'role' => 'Super Admin',
            ],
            'company_id' => 1,
        ])->post('/employees/form', [
            'id' => 1,
            'company_id' => 1,
            'nik_ktp' => '3216220403740002',
            'address_ktp' => 'Bekasi',
            'name' => 'Budi Rubiantoro Update',
            'npwp' => '01.234.567.8-999.000',
            'ptkp_status' => 'K/2',
            'employment_status' => 'TETAP ALL-IN',
            'employee_type' => 'Staff',
            'position' => 'Management Representative',
            'grade' => 'M/1',
            'join_date' => '2024-01-01',
            'contract_end' => '',
            'basic_salary' => '10000000',
            'a2_overtime' => '0',
            'a3_meal' => '0',
            'a4_transport' => '0',
            'a5_performance' => '0',
            'a6_position' => '0',
            'a7_family' => '0',
            'a8_communication' => '0',
            'a9_other' => '0',
            'a10_thr' => '0',
            'a11_bonus' => '0',
            'a12_tax_allowance' => '0',
            'a13_bpjs_allowance' => '0',
            'b1_loan' => '0',
            'b3_subsidy' => '0',
            'b4_bpjs_health' => '0',
            'b5_jht' => '0',
            'b6_jp' => '0',
            'b7_pph21' => '0',
            'b8_other' => '0',
        ]);

        $response->assertRedirect('/employees');

        $employee = DB::table('employees')->where('id', 1)->first();
        $this->assertSame('uploads/employees/test-photo.jpg', $employee->photo_path);
        $this->assertSame('uploads/employees/docs/test-ktp.jpg', $employee->ktp_path);
        $this->assertSame('uploads/employees/docs/test-ijazah.pdf', $employee->ijazah_path);
        $this->assertSame('uploads/employees/docs/test-lamaran.pdf', $employee->surat_lamaran_path);
        $this->assertSame('uploads/employees/docs/test-kk.jpg', $employee->kk_path);
        $this->assertSame('uploads/employees/docs/test-npwp.jpg', $employee->npwp_path);
        $this->assertSame('uploads/employees/docs/test-skck.pdf', $employee->skck_path);
    }
}
