<?php

namespace Tests\Feature;

use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacDefaultRoleTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_ceo_and_cfa_have_limited_default_permissions(): void
    {
        $matrix = Rbac::matrixByRole();

        $this->assertTrue($matrix['CEO']['payroll_report'] ?? false);
        $this->assertTrue($matrix['CEO']['payroll_pph21'] ?? false);
        $this->assertFalse($matrix['CEO']['payroll_run'] ?? true);
        $this->assertFalse($matrix['CEO']['employees'] ?? true);

        $this->assertTrue($matrix['CFA']['attendance_report'] ?? false);
        $this->assertFalse($matrix['CFA']['payroll_period'] ?? true);
        $this->assertFalse($matrix['CFA']['users'] ?? true);
    }
}
