<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SecurityShiftDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $companiesQuery = Company::query();
        if (\Illuminate\Support\Facades\Schema::hasColumn('companies', 'is_active')) {
            $companiesQuery->where('is_active', 1);
        }
        $companies = $companiesQuery->get(['id']);

        foreach ($companies as $company) {
            $companyId = (int) ($company->id ?? 0);
            if ($companyId <= 0) {
                continue;
            }

            $rows = [
                ['code' => 'P', 'name' => 'PAGI', 'start_time' => '07:00:00', 'end_time' => '15:00:00', 'cross_day' => 0],
                ['code' => 'S', 'name' => 'SIANG', 'start_time' => '15:00:00', 'end_time' => '23:00:00', 'cross_day' => 0],
                ['code' => 'M', 'name' => 'MALAM', 'start_time' => '23:00:00', 'end_time' => '07:00:00', 'cross_day' => 1],
                ['code' => 'OFF', 'name' => 'OFF', 'start_time' => null, 'end_time' => null, 'cross_day' => 0],
            ];

            foreach ($rows as $row) {
                DB::table('security_shift_definitions')->updateOrInsert(
                    ['company_id' => $companyId, 'code' => $row['code']],
                    [
                        'name' => $row['name'],
                        'start_time' => $row['start_time'],
                        'end_time' => $row['end_time'],
                        'cross_day' => $row['cross_day'],
                        'is_active' => 1,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }
}
