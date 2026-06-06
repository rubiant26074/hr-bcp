<?php

namespace App\Support;

use App\Models\RoleDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Rbac
{
    private static array $roles = ['Super Admin', 'CEO', 'CFA', 'HR', 'HR1', 'HR2', 'Finance', 'Employee'];
    private static bool $booted = false;
    private static ?array $permissionPathMap = null;
    private static array $roleAllowedMap = [];

    public static function defaults(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'path' => 'modules/dashboard/index.php', 'section' => 'Core', 'sort_order' => 10],
            ['key' => 'tv_dashboard', 'label' => 'TV Dashboard', 'path' => 'modules/tv/index.php', 'section' => 'Core', 'sort_order' => 15],
            ['key' => 'notifications', 'label' => 'Notifikasi', 'path' => 'modules/notifications/index.php', 'section' => 'Core', 'sort_order' => 18],
            ['key' => 'account', 'label' => 'Profil Saya / Akun', 'path' => 'modules/account/index.php', 'section' => 'Core', 'sort_order' => 19],
            ['key' => 'help', 'label' => 'Bantuan / Panduan', 'path' => 'modules/help/index.php', 'section' => 'Core', 'sort_order' => 20],
            ['key' => 'settings_theme', 'label' => 'Setting Theme', 'path' => 'modules/settings/theme.php', 'section' => 'Settings', 'sort_order' => 20],
            ['key' => 'settings_backup', 'label' => 'Backup Database', 'path' => 'modules/settings/backup.php', 'section' => 'Settings', 'sort_order' => 25],
            ['key' => 'settings_approval', 'label' => 'Approval Settings', 'path' => 'modules/settings/approval.php', 'section' => 'Settings', 'sort_order' => 27],
            ['key' => 'users', 'label' => 'User Management', 'path' => 'modules/users/index.php', 'section' => 'Settings', 'sort_order' => 30],
            ['key' => 'users_form', 'label' => 'User Form', 'path' => 'modules/users/form.php', 'section' => 'Settings', 'sort_order' => 31],
            ['key' => 'rbac', 'label' => 'Kontrol Hak Akses (RBAC)', 'path' => 'modules/rbac/index.php', 'section' => 'Settings', 'sort_order' => 40],
            ['key' => 'settings_roles', 'label' => 'Role Management', 'path' => 'modules/settings/roles.php', 'section' => 'Settings', 'sort_order' => 45],
            ['key' => 'settings_reset', 'label' => 'Reset Database', 'path' => 'modules/settings/reset.php', 'section' => 'Settings', 'sort_order' => 50],
            ['key' => 'company', 'label' => 'Company', 'path' => 'modules/company/index.php', 'section' => 'Master Data', 'sort_order' => 100],
            ['key' => 'org_structure', 'label' => 'Struktur Organisasi', 'path' => 'modules/org-structure/index.php', 'section' => 'Master Data', 'sort_order' => 105],
            ['key' => 'company_form', 'label' => 'Company Form', 'path' => 'modules/company/form.php', 'section' => 'Master Data', 'sort_order' => 101],
            ['key' => 'company_detail', 'label' => 'Company Detail', 'path' => 'modules/company/detail.php', 'section' => 'Master Data', 'sort_order' => 102],
            ['key' => 'employees', 'label' => 'Employees', 'path' => 'modules/employees/index.php', 'section' => 'Master Data', 'sort_order' => 110],
            ['key' => 'employees_form', 'label' => 'Employee Form', 'path' => 'modules/employees/form.php', 'section' => 'Master Data', 'sort_order' => 111],
            ['key' => 'employees_form_profile', 'label' => 'Employee Form - Data Karyawan', 'path' => 'modules/employees/form_profile.php', 'section' => 'Master Data', 'sort_order' => 111],
            ['key' => 'employees_form_payroll', 'label' => 'Employee Form - Payroll Settings', 'path' => 'modules/employees/form_payroll.php', 'section' => 'Master Data', 'sort_order' => 112],
            ['key' => 'employees_detail', 'label' => 'Employee Detail', 'path' => 'modules/employees/detail.php', 'section' => 'Master Data', 'sort_order' => 112],
            ['key' => 'employees_import', 'label' => 'Employee Import', 'path' => 'modules/employees/import.php', 'section' => 'Master Data', 'sort_order' => 113],
            ['key' => 'employees_export', 'label' => 'Employee Export', 'path' => 'modules/employees/export.php', 'section' => 'Master Data', 'sort_order' => 114],
            ['key' => 'employees_department', 'label' => 'Master Departement', 'path' => 'modules/employees/department.php', 'section' => 'Master Data', 'sort_order' => 115],
            ['key' => 'pension', 'label' => 'Pensiun', 'path' => 'modules/pension/index.php', 'section' => 'Master Data', 'sort_order' => 118],
            ['key' => 'phk', 'label' => 'PHK', 'path' => 'modules/phk/index.php', 'section' => 'Master Data', 'sort_order' => 119],
            ['key' => 'contracts', 'label' => 'Contracts', 'path' => 'modules/contracts/index.php', 'section' => 'Master Data', 'sort_order' => 120],
            ['key' => 'contracts_form', 'label' => 'Contract Form', 'path' => 'modules/contracts/form.php', 'section' => 'Master Data', 'sort_order' => 121],
            ['key' => 'contracts_template', 'label' => 'Contract Template Download', 'path' => 'modules/contracts/template.php', 'section' => 'Master Data', 'sort_order' => 122],
            ['key' => 'leave_management', 'label' => 'Management Cuti', 'path' => 'modules/leave/index.php', 'section' => 'Master Data', 'sort_order' => 130],
            ['key' => 'holidays', 'label' => 'Libur Nasional', 'path' => 'modules/holidays/index.php', 'section' => 'Master Data', 'sort_order' => 140],
            ['key' => 'attendance_import', 'label' => 'Import Absensi', 'path' => 'modules/attendance/import.php', 'section' => 'Operations', 'sort_order' => 200],
            ['key' => 'attendance_logs', 'label' => 'Log Absensi', 'path' => 'modules/attendance/logs.php', 'section' => 'Operations', 'sort_order' => 210],
            ['key' => 'attendance_daily', 'label' => 'Rekap Harian', 'path' => 'modules/attendance/daily.php', 'section' => 'Operations', 'sort_order' => 220],
            ['key' => 'attendance_monthly', 'label' => 'Rekap Bulanan', 'path' => 'modules/attendance/monthly.php', 'section' => 'Operations', 'sort_order' => 230],
            ['key' => 'attendance_monthly_employee', 'label' => 'Rekap Bulanan Per Employee', 'path' => 'modules/attendance/monthly-employee.php', 'section' => 'Operations', 'sort_order' => 231],
            ['key' => 'attendance_security_roster', 'label' => 'Jadwal Shift Security', 'path' => 'modules/attendance/security-roster.php', 'section' => 'Admin', 'sort_order' => 232],
            ['key' => 'attendance_mobile', 'label' => 'Absensi Mobile', 'path' => 'modules/attendance/mobile.php', 'section' => 'Operations', 'sort_order' => 235],
            ['key' => 'attendance_location', 'label' => 'Setting Lokasi Absen', 'path' => 'modules/settings/attendance_location.php', 'section' => 'Settings', 'sort_order' => 24],
            ['key' => 'permissions_absence', 'label' => 'Perizinan Tidak Masuk', 'path' => 'modules/permissions/absence.php', 'section' => 'Permissions', 'sort_order' => 10],
            ['key' => 'permissions_out_office', 'label' => 'Izin Keluar Kantor', 'path' => 'modules/permissions/out_office.php', 'section' => 'Permissions', 'sort_order' => 20],
            ['key' => 'permissions_overtime', 'label' => 'Lembur', 'path' => 'modules/permissions/overtime.php', 'section' => 'Permissions', 'sort_order' => 30],
            ['key' => 'dinas_luar', 'label' => 'Dinas Luar', 'path' => 'modules/dinas_luar/index.php', 'section' => 'Permissions', 'sort_order' => 35],
            ['key' => 'payroll_period', 'label' => 'Payroll Period', 'path' => 'modules/payroll/period.php', 'section' => 'Operations', 'sort_order' => 240],
            ['key' => 'payroll_run', 'label' => 'Run Payroll', 'path' => 'modules/payroll/run.php', 'section' => 'Operations', 'sort_order' => 250],
            ['key' => 'payroll_review', 'label' => 'Review Payroll', 'path' => 'modules/payroll/review.php', 'section' => 'Operations', 'sort_order' => 260],
            ['key' => 'payroll_slip', 'label' => 'Slip Gaji', 'path' => 'modules/payroll/slip.php', 'section' => 'Operations', 'sort_order' => 270],
            ['key' => 'payroll_report', 'label' => 'Payroll Report', 'path' => 'modules/payroll/report.php', 'section' => 'Reports', 'sort_order' => 300],
            ['key' => 'payroll_report_approval', 'label' => 'Payroll Report Approval', 'path' => 'modules/payroll/report-approval.php', 'section' => 'Reports', 'sort_order' => 301],
            ['key' => 'payroll_pph21', 'label' => 'PPh21', 'path' => 'modules/payroll/pph21.php', 'section' => 'Reports', 'sort_order' => 305],
            ['key' => 'payroll_pph21_approval', 'label' => 'Payroll PPh21 Approval', 'path' => 'modules/payroll/pph21-approval.php', 'section' => 'Reports', 'sort_order' => 306],
            ['key' => 'payroll_bank_transfer', 'label' => 'Payroll Bank Transfer', 'path' => 'modules/payroll/bank-transfer.php', 'section' => 'Reports', 'sort_order' => 307],
            ['key' => 'attendance_report', 'label' => 'Attendance Report', 'path' => 'modules/attendance/report.php', 'section' => 'Reports', 'sort_order' => 310],
        ];
    }

    public static function roles(): array
    {
        if (!Schema::hasTable('role_definitions')) {
            return self::$roles;
        }
        $roles = RoleDefinition::orderBy('name')->pluck('name')->all();
        if (!in_array('Super Admin', $roles, true)) {
            array_unshift($roles, 'Super Admin');
        }
        return $roles;
    }

    public static function defaultAllowedKeysForRole(string $role): array
    {
        if ($role === 'Super Admin') {
            return array_map(static fn (array $item): string => $item['key'], self::defaults());
        }

        if (in_array($role, ['CEO', 'CFA'], true)) {
            return [
                'dashboard',
                'account',
                'help',
                'company_detail',
                'dinas_luar',
                'payroll_review',
                'payroll_report',
                'payroll_report_approval',
                'payroll_pph21',
                'payroll_pph21_approval',
                'payroll_bank_transfer',
                'payroll_slip',
                'attendance_report',
            ];
        }

        return array_map(static fn (array $item): string => $item['key'], self::defaults());
    }

    public static function ensureSchema(): void
    {
        if (self::$booted) {
            return;
        }

        if (!Schema::hasTable('rbac_permissions') || !Schema::hasTable('rbac_role_permissions')) {
            return;
        }

        DB::table('rbac_permissions')->upsert(
            array_map(static function (array $item): array {
                return [
                    'permission_key' => $item['key'],
                    'label' => $item['label'],
                    'path' => $item['path'],
                    'section_name' => $item['section'],
                    'sort_order' => (int) $item['sort_order'],
                ];
            }, self::defaults()),
            ['permission_key'],
            ['label', 'path', 'section_name', 'sort_order']
        );

        $existingPairs = DB::table('rbac_role_permissions')
            ->select('role_name', 'permission_key')
            ->get()
            ->mapWithKeys(static function ($row) {
                return [$row->role_name . '|' . $row->permission_key => true];
            })
            ->all();

        $roleRows = [];
        foreach (self::roles() as $role) {
            $allowedKeys = array_flip(self::defaultAllowedKeysForRole($role));
            foreach (self::defaults() as $item) {
                $pairKey = $role . '|' . $item['key'];
                if (isset($existingPairs[$pairKey])) {
                    continue;
                }
                $roleRows[] = [
                    'role_name' => $role,
                    'permission_key' => $item['key'],
                    'is_allowed' => isset($allowedKeys[$item['key']]) ? 1 : 0,
                ];
            }
        }
        if ($roleRows !== []) {
            DB::table('rbac_role_permissions')->insert($roleRows);
        }

        self::$permissionPathMap = null;
        self::$roleAllowedMap = [];
        self::$booted = true;
    }

    public static function allPermissions(): array
    {
        self::ensureSchema();
        return DB::select('SELECT permission_key, label, path, section_name, sort_order
                           FROM rbac_permissions
                           ORDER BY section_name, sort_order, label');
    }

    public static function matrixByRole(): array
    {
        self::ensureSchema();
        $rows = DB::select('SELECT role_name, permission_key, is_allowed FROM rbac_role_permissions');
        $map = [];
        foreach ($rows as $r) {
            $map[$r->role_name][$r->permission_key] = (int) $r->is_allowed === 1;
        }
        return $map;
    }

    public static function saveRolePermissions(string $role, array $allowedKeys): void
    {
        self::ensureSchema();
        if (!in_array($role, self::roles(), true) || $role === 'Super Admin') {
            return;
        }

        $permissions = self::allPermissions();
        $allKeys = array_map(static fn ($p) => $p->permission_key, $permissions);
        $allowedMap = array_flip($allowedKeys);

        $stmt = 'UPDATE rbac_role_permissions SET is_allowed = ? WHERE role_name = ? AND permission_key = ?';
        foreach ($allKeys as $key) {
            $allow = isset($allowedMap[$key]) ? 1 : 0;
            DB::update($stmt, [$allow, $role, $key]);
        }
    }

    private static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $qPos = strpos($path, '?');
        if ($qPos !== false) {
            $path = substr($path, 0, $qPos);
        }
        $pos = strpos($path, '/modules/');
        if ($pos !== false) {
            return ltrim(substr($path, $pos + 1), '/');
        }
        return ltrim($path, '/');
    }

    public static function isAllowedForPath(string $role, string $path): bool
    {
        self::ensureSchema();
        $role = trim($role);
        if ($role === '') {
            return false;
        }
        if ($role === 'Super Admin') {
            return true;
        }

        $normalized = self::normalizePath($path);
        if ($normalized === '') {
            return true;
        }

        if (self::$permissionPathMap === null) {
            self::$permissionPathMap = [];
            $perms = DB::select('SELECT permission_key, path FROM rbac_permissions');
            foreach ($perms as $perm) {
                self::$permissionPathMap[self::normalizePath((string) $perm->path)] = (string) $perm->permission_key;
            }
        }
        $permissionKey = self::$permissionPathMap[$normalized] ?? null;
        if (!$permissionKey) {
            return true;
        }

        if (!isset(self::$roleAllowedMap[$role])) {
            self::$roleAllowedMap[$role] = [];
            $rows = DB::select('SELECT permission_key, is_allowed FROM rbac_role_permissions WHERE role_name = ?', [$role]);
            foreach ($rows as $allow) {
                self::$roleAllowedMap[$role][(string) $allow->permission_key] = (int) $allow->is_allowed === 1;
            }
        }

        $isAllowed = !empty(self::$roleAllowedMap[$role][$permissionKey]);
        if ($isAllowed) {
            return true;
        }

        // Backward compatibility:
        // jika hanya permission base "employees" yang dicentang,
        // tetap izinkan turunan route employees_* (form/detail/import/export/dll).
        if (str_starts_with($permissionKey, 'employees_') && !empty(self::$roleAllowedMap[$role]['employees'])) {
            return true;
        }

        return false;
    }

    public static function isAllowedForKey(string $role, string $permissionKey): bool
    {
        self::ensureSchema();
        $role = trim($role);
        if ($role === '') {
            return false;
        }
        if ($role === 'Super Admin') {
            return true;
        }

        if (!isset(self::$roleAllowedMap[$role])) {
            self::$roleAllowedMap[$role] = [];
            $rows = DB::select('SELECT permission_key, is_allowed FROM rbac_role_permissions WHERE role_name = ?', [$role]);
            foreach ($rows as $allow) {
                self::$roleAllowedMap[$role][(string) $allow->permission_key] = (int) $allow->is_allowed === 1;
            }
        }

        return !empty(self::$roleAllowedMap[$role][$permissionKey]);
    }
}

