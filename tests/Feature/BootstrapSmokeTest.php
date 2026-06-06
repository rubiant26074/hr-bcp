<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BootstrapSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_login_page_renders_after_bootstrap(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_employee_form_renders_with_seeded_master_data(): void
    {
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
        ])->get('/employees/form')->assertOk();
    }

    public function test_dashboard_renders_after_bootstrap(): void
    {
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
        ])->get('/dashboard')->assertOk();
    }

    public function test_dashboard_renders_for_ceo_global_role(): void
    {
        $this->withSession([
            'user' => [
                'id' => 2,
                'company_id' => null,
                'employee_id' => null,
                'name' => 'CEO Group',
                'email' => 'ceo@local.test',
                'role' => 'CEO',
            ],
            'company_id' => 1,
        ])->get('/dashboard')->assertOk();
    }
}
