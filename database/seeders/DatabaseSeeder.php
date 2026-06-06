<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\EmployeeGrade;
use App\Models\EmployeePosition;
use App\Models\EmployeeStatus;
use App\Support\Rbac;
use Database\Seeders\SecurityShiftDefinitionSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $company = Company::query()->updateOrCreate(
            ['id' => 1],
            [
                'company_name' => 'PT. Berkah Cipta Persada',
                'company_code' => 'BK',
                'address' => 'Local Development Bootstrap',
                'npwp' => '',
                'logo_path' => null,
                'bpjs_health_pct' => 1,
                'bpjs_jht_pct' => 2,
                'bpjs_jp_pct' => 1,
                'payroll_day' => 25,
                'work_days_per_week' => 5,
                'work_time_start' => '08:00',
                'work_time_end' => '17:00',
                'work_duration_hours' => 8,
                'work_days_json' => json_encode(['Mon','Tue','Wed','Thu','Fri']),
            ]
        );

        EmployeeStatus::query()->updateOrCreate([
            'company_id' => $company->id,
            'status_name' => 'TETAP ALL-IN',
        ], [
            'note' => 'Seed bootstrap local',
        ]);

        EmployeePosition::query()->updateOrCreate([
            'company_id' => $company->id,
            'position_name' => 'Management Representative',
        ], [
            'note' => 'Seed bootstrap local',
        ]);

        EmployeeGrade::query()->updateOrCreate([
            'company_id' => $company->id,
            'grade_name' => 'M/1',
        ], [
            'note' => 'Seed bootstrap local',
        ]);

        $passwordHash = password_hash('password', PASSWORD_DEFAULT);
        $userPayload = [
            'company_id' => null,
            'employee_id' => null,
            'name' => 'Super Admin',
            'email' => 'admin@local.test',
            'password_hash' => $passwordHash,
            'role' => 'Super Admin',
            'created_at' => now(),
        ];
        if (Schema::hasColumn('users', 'email_verified_at')) {
            $userPayload['email_verified_at'] = now();
        }
        if (Schema::hasColumn('users', 'password')) {
            $userPayload['password'] = $passwordHash;
        }
        if (Schema::hasColumn('users', 'remember_token')) {
            $userPayload['remember_token'] = null;
        }
        if (Schema::hasColumn('users', 'updated_at')) {
            $userPayload['updated_at'] = now();
        }

        DB::table('users')->updateOrInsert(
            ['email' => 'admin@local.test'],
            $userPayload
        );

        Rbac::ensureSchema();

        if (Schema::hasTable('security_shift_definitions')) {
            $this->call(SecurityShiftDefinitionSeeder::class);
        }
    }
}

