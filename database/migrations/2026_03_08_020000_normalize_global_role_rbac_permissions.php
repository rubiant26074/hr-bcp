<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rbac_permissions') || !Schema::hasTable('rbac_role_permissions')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE rbac_role_permissions CHANGE role_name role_name VARCHAR(20) NOT NULL');
        }

        $allowed = [
            'dashboard',
            'company_detail',
            'payroll_review',
            'payroll_report',
            'payroll_pph21',
            'payroll_slip',
            'attendance_report',
        ];

        $permissionKeys = DB::table('rbac_permissions')->pluck('permission_key')->all();
        foreach (['CEO', 'CFA'] as $role) {
            foreach ($permissionKeys as $permissionKey) {
                DB::table('rbac_role_permissions')->updateOrInsert(
                    [
                        'role_name' => $role,
                        'permission_key' => $permissionKey,
                    ],
                    [
                        'is_allowed' => in_array($permissionKey, $allowed, true) ? 1 : 0,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('rbac_role_permissions')) {
            return;
        }

        DB::table('rbac_role_permissions')
            ->whereIn('role_name', ['CEO', 'CFA'])
            ->delete();
    }
};
