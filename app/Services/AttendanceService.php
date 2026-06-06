<?php

namespace App\Services;

use DateTime;
use App\Models\Employee;
use App\Services\OvertimeCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttendanceService
{
    private static ?bool $specialLeaveExcuseColumnExists = null;

    private static function hasSpecialLeaveExcuseColumn(): bool
    {
        if (self::$specialLeaveExcuseColumnExists !== null) {
            return self::$specialLeaveExcuseColumnExists;
        }
        try {
            self::$specialLeaveExcuseColumnExists = Schema::hasColumn('attendance_daily', 'is_special_leave_excused');
        } catch (\Throwable $e) {
            self::$specialLeaveExcuseColumnExists = false;
        }
        return self::$specialLeaveExcuseColumnExists;
    }

    public static function upsertDeviceUserEmployeeMap(int $companyId, ?int $employeeId, ?string $deviceUserId): void
    {
        $companyId = (int) $companyId;
        $employeeId = (int) ($employeeId ?? 0);
        $deviceUserId = trim((string) ($deviceUserId ?? ''));

        if (
            $companyId <= 0
            || $employeeId <= 0
            || $deviceUserId === ''
            || !Schema::hasTable('attendance_device_user_maps')
        ) {
            return;
        }

        DB::table('attendance_device_user_maps')->updateOrInsert(
            [
                'company_id' => $companyId,
                'device_user_id' => $deviceUserId,
            ],
            [
                'employee_id' => $employeeId,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private static function resolveEmployeeIdByDeviceUserMap(int $companyId, string $deviceUserId): ?int
    {
        if (
            $companyId <= 0
            || $deviceUserId === ''
            || !Schema::hasTable('attendance_device_user_maps')
        ) {
            return null;
        }

        $mapped = DB::table('attendance_device_user_maps')
            ->where('company_id', $companyId)
            ->where('device_user_id', $deviceUserId)
            ->value('employee_id');

        $mapped = (int) ($mapped ?? 0);
        return $mapped > 0 ? $mapped : null;
    }

    private static bool $noOtColumnReady = false;
    private static bool $absenceExcuseColumnsReady = false;

    private static function ensureNoOvertimePermitColumn(): void
    {
        if (self::$noOtColumnReady) {
            return;
        }
        self::$noOtColumnReady = true;
    }

    private static function ensureAbsenceExcuseColumns(): void
    {
        if (self::$absenceExcuseColumnsReady) {
            return;
        }
        self::$absenceExcuseColumnsReady = true;
    }

    public static function ensureDailySchema(): void
    {
        self::ensureNoOvertimePermitColumn();
        self::ensureAbsenceExcuseColumns();
    }

    private static function isSecurityPosition(?string $position): bool
    {
        $pos = strtoupper(trim((string) ($position ?? '')));
        if ($pos === '') {
            return false;
        }
        if (str_contains($pos, 'KEPALA SECURITY')) {
            return false;
        }
        return str_contains($pos, 'SECURITY')
            || str_contains($pos, 'SCURITY')
            || str_contains($pos, 'SATPAM');
    }

    private static function getSecurityRosterWindow(int $companyId, int $employeeId, string $date): ?array
    {
        if (!Schema::hasTable('security_rosters')) {
            return null;
        }
        $row = DB::table('security_rosters')
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('work_date', $date)
            ->first(['shift_code', 'shift_start_at', 'shift_end_at']);
        if (!$row) {
            return null;
        }
        $shiftCode = strtoupper(trim((string) ($row->shift_code ?? '')));
        return [
            'shift_code' => $shiftCode,
            'start_at' => $row->shift_start_at ? (string) $row->shift_start_at : null,
            'end_at' => $row->shift_end_at ? (string) $row->shift_end_at : null,
        ];
    }

    private static function loadSecurityRosterMap(int $companyId, int $employeeId, string $startDate, string $endDate): array
    {
        if (!Schema::hasTable('security_rosters')) {
            return [];
        }
        $rows = DB::table('security_rosters')
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereRaw('work_date BETWEEN ? AND ?', [$startDate, $endDate])
            ->get(['work_date', 'shift_code', 'shift_start_at', 'shift_end_at']);

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->work_date] = [
                'shift_code' => strtoupper(trim((string) ($row->shift_code ?? ''))),
                'start_at' => $row->shift_start_at ? (string) $row->shift_start_at : null,
                'end_at' => $row->shift_end_at ? (string) $row->shift_end_at : null,
            ];
        }
        return $map;
    }

    private static function getSecurityShiftDefinition(string $workDate, ?string $shiftCode): ?array
    {
        $code = strtoupper(trim((string) ($shiftCode ?? '')));
        if ($code === '' || $code === 'OFF') {
            return null;
        }

        if ($code === 'P') {
            return [
                'shift_code' => 'P',
                'start_at' => $workDate . ' 07:00:00',
                'end_at' => $workDate . ' 15:00:00',
                'window_start' => $workDate . ' 05:00:00',
                'window_end' => $workDate . ' 18:00:00',
            ];
        }
        if ($code === 'S') {
            return [
                'shift_code' => 'S',
                'start_at' => $workDate . ' 15:00:00',
                'end_at' => $workDate . ' 23:00:00',
                'window_start' => $workDate . ' 13:00:00',
                'window_end' => date('Y-m-d H:i:s', strtotime($workDate . ' 23:00:00 +3 hours')),
            ];
        }
        if ($code === 'M') {
            return [
                'shift_code' => 'M',
                'start_at' => $workDate . ' 23:00:00',
                'end_at' => date('Y-m-d H:i:s', strtotime($workDate . ' 23:00:00 +8 hours')),
                'window_start' => $workDate . ' 21:00:00',
                'window_end' => date('Y-m-d H:i:s', strtotime($workDate . ' 23:00:00 +13 hours')),
            ];
        }

        return null;
    }

    private static function inferSecurityShiftCodeFromLogs(array $dayLogs): ?string
    {
        if (count($dayLogs) === 0) {
            return null;
        }

        $firstLog = (string) ($dayLogs[0] ?? '');
        if ($firstLog !== '') {
            $firstHm = date('H:i:s', strtotime($firstLog));
            if ($firstHm >= '04:00:00' && $firstHm < '12:00:00') {
                return 'P';
            }
            if ($firstHm >= '12:00:00' && $firstHm < '21:00:00') {
                return 'S';
            }
            if ($firstHm >= '21:00:00') {
                return 'M';
            }
        }

        foreach ($dayLogs as $ts) {
            $hm = date('H:i:s', strtotime($ts));
            if ($hm >= '04:00:00' && $hm < '12:00:00') {
                return 'P';
            }
            if ($hm >= '12:00:00' && $hm < '21:00:00') {
                return 'S';
            }
            if ($hm >= '21:00:00') {
                return 'M';
            }
        }

        return null;
    }

    private static function isMorningOnlyLogSet(array $dayLogs): bool
    {
        if (count($dayLogs) === 0) {
            return false;
        }
        foreach ($dayLogs as $ts) {
            $hm = date('H:i:s', strtotime($ts));
            if (!($hm >= '04:00:00' && $hm <= '12:00:00')) {
                return false;
            }
        }
        return true;
    }

    private static function hasNightLog(array $dayLogs): bool
    {
        foreach ($dayLogs as $ts) {
            if (date('H:i:s', strtotime($ts)) >= '21:00:00') {
                return true;
            }
        }
        return false;
    }

    private static function hasMiddayOrAfternoonLog(array $dayLogs): bool
    {
        foreach ($dayLogs as $ts) {
            $hm = date('H:i:s', strtotime($ts));
            if ($hm >= '12:00:00' && $hm < '21:00:00') {
                return true;
            }
        }
        return false;
    }

    private static function firstMorningLog(array $dayLogs): ?string
    {
        foreach ($dayLogs as $ts) {
            $hm = date('H:i:s', strtotime($ts));
            if ($hm >= '04:00:00' && $hm <= '12:00:00') {
                return $ts;
            }
        }

        return null;
    }

    private static function stripCarryoverMorningLogs(array $dayLogs, array $prevDayLogs): array
    {
        if (count($dayLogs) === 0 || !self::hasNightLog($prevDayLogs)) {
            return [$dayLogs, []];
        }

        $kept = [];
        $ignored = [];
        $skippingCarryover = true;
        foreach ($dayLogs as $ts) {
            $hm = date('H:i:s', strtotime($ts));
            $isCarryoverMorning = $hm >= '04:00:00' && $hm <= '12:00:00';
            if ($skippingCarryover && $isCarryoverMorning) {
                $ignored[] = $ts;
                continue;
            }

            $skippingCarryover = false;
            $kept[] = $ts;
        }

        return [$kept, $ignored];
    }

    public static function buildSecurityShiftDailyMap(int $companyId, int $employeeId, string $startDate, string $endDate): array
    {
        $rosterMap = self::loadSecurityRosterMap($companyId, $employeeId, $startDate, $endDate);
        $rawLogs = DB::table('attendance_logs')
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereRaw('DATE(scan_time) BETWEEN ? AND ?', [
                date('Y-m-d', strtotime($startDate . ' -1 day')),
                date('Y-m-d', strtotime($endDate . ' +2 day')),
            ])
            ->orderBy('scan_time')
            ->pluck('scan_time')
            ->map(static fn ($v) => (string) $v)
            ->filter(static fn ($v) => $v !== '')
            ->unique()
            ->values()
            ->all();

        $logsByDate = [];
        foreach ($rawLogs as $ts) {
            $dateKey = date('Y-m-d', strtotime($ts));
            if (!isset($logsByDate[$dateKey])) {
                $logsByDate[$dateKey] = [];
            }
            $logsByDate[$dateKey][] = $ts;
        }

        $results = [];
        $consumedLogMap = [];
        $cursor = $startDate;
        while ($cursor <= $endDate) {
            $roster = $rosterMap[$cursor] ?? null;
            $dayLogs = array_values(array_filter(
                $logsByDate[$cursor] ?? [],
                static fn ($ts) => !isset($consumedLogMap[$ts])
            ));
            $originalDayLogs = $dayLogs;
            $prevDate = date('Y-m-d', strtotime($cursor . ' -1 day'));
            $prevDayLogs = array_values(array_filter(
                $logsByDate[$prevDate] ?? [],
                static fn ($ts) => !isset($consumedLogMap[$ts])
            ));
            $ignoredCarryoverLogs = [];

            if (!$roster) {
                [$dayLogs, $ignoredCarryoverLogs] = self::stripCarryoverMorningLogs($dayLogs, $prevDayLogs);
            }
            $ignoredCarryoverLogMap = array_flip($ignoredCarryoverLogs);

            $shiftCode = $roster['shift_code'] ?? self::inferSecurityShiftCodeFromLogs($dayLogs);

            if (
                !$roster
                && self::isMorningOnlyLogSet($originalDayLogs)
                && self::hasNightLog($prevDayLogs)
            ) {
                $shiftCode = null;
            }
            if (
                !$roster
                && self::hasNightLog($dayLogs)
                && self::firstMorningLog($logsByDate[date('Y-m-d', strtotime($cursor . ' +1 day'))] ?? []) !== null
            ) {
                $shiftCode = 'M';
            } elseif (
                !$roster
                && self::hasNightLog($dayLogs)
                && !self::hasMiddayOrAfternoonLog($dayLogs)
            ) {
                $shiftCode = 'M';
            }

            if ($shiftCode === 'OFF') {
                $results[$cursor] = [
                    'date' => $cursor,
                    'shift_code' => 'OFF',
                    'shift_start_at' => $roster['start_at'] ?? null,
                    'shift_end_at' => $roster['end_at'] ?? null,
                    'check_in' => null,
                    'check_out' => null,
                    'work_hours' => 0.0,
                    'overtime_hours' => 0.0,
                    'is_off' => true,
                    'is_missing_checkout' => false,
                ];
                $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
                continue;
            }

            $def = null;
            if ($roster && !empty($roster['start_at']) && !empty($roster['end_at'])) {
                $def = [
                    'shift_code' => $shiftCode,
                    'start_at' => (string) $roster['start_at'],
                    'end_at' => (string) $roster['end_at'],
                    'window_start' => date('Y-m-d H:i:s', strtotime((string) $roster['start_at'] . ' -2 hours')),
                    'window_end' => date('Y-m-d H:i:s', strtotime((string) $roster['end_at'] . ' +3 hours')),
                ];
            } else {
                $def = self::getSecurityShiftDefinition($cursor, $shiftCode);
            }

            if ($def === null) {
                $onlyMorningCarry = count($dayLogs) > 0;
                foreach ($dayLogs as $ts) {
                    $hm = date('H:i:s', strtotime($ts));
                    if (!($hm >= '04:00:00' && $hm <= '12:00:00')) {
                        $onlyMorningCarry = false;
                        break;
                    }
                }
                if ($onlyMorningCarry) {
                    $results[$cursor] = [
                        'date' => $cursor,
                        'shift_code' => 'OFF',
                        'shift_start_at' => null,
                        'shift_end_at' => null,
                        'check_in' => null,
                        'check_out' => null,
                        'work_hours' => 0.0,
                        'overtime_hours' => 0.0,
                        'is_off' => true,
                        'is_missing_checkout' => false,
                    ];
                }
                $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
                continue;
            }

            $windowLogs = [];
            foreach ($rawLogs as $ts) {
                if (isset($consumedLogMap[$ts])) {
                    continue;
                }
                if (isset($ignoredCarryoverLogMap[$ts])) {
                    continue;
                }
                if ($ts < $def['window_start']) {
                    continue;
                }
                if ($ts > $def['window_end']) {
                    break;
                }
                $windowLogs[] = $ts;
            }

            if (count($windowLogs) === 0) {
                $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
                continue;
            }

            $checkIn = $windowLogs[0];
            $checkOut = null;
            if (($def['shift_code'] ?? '') === 'M') {
                foreach ($windowLogs as $ts) {
                    if ($ts <= $checkIn) {
                        continue;
                    }
                    if ($ts >= date('Y-m-d H:i:s', strtotime($cursor . ' +1 day 04:00:00'))) {
                        $checkOut = $ts;
                    }
                }
            } else {
                $checkOut = $windowLogs[count($windowLogs) - 1] ?? null;
                if ($checkOut === $checkIn) {
                    $checkOut = null;
                }
            }

            if (
                $checkIn !== null
                && $checkOut === null
                && date('H:i:s', strtotime($checkIn)) >= '12:00:00'
            ) {
                $nextDate = date('Y-m-d', strtotime($cursor . ' +1 day'));
                $nextDayLogs = array_values(array_filter(
                    $logsByDate[$nextDate] ?? [],
                    static fn ($ts) => !isset($consumedLogMap[$ts])
                ));
                $fallbackCheckout = self::firstMorningLog($nextDayLogs);
                if ($fallbackCheckout !== null && $fallbackCheckout > $checkIn) {
                    $checkOut = $fallbackCheckout;
                }
            }

            $workHours = 0.0;
            $overtimeHours = 0.0;
            if ($checkIn && $checkOut) {
                $workHours = round(max(0, (strtotime($checkOut) - strtotime($checkIn))) / 3600, 2);
                $calc = OvertimeCalculator::calculateForRecord($companyId, $cursor, $checkIn, $checkOut, false);
                $overtimeHours = round((float) ($calc['hours'] ?? 0), 2);
            }

            if ($checkIn !== null) {
                $consumedLogMap[$checkIn] = true;
            }
            if ($checkOut !== null) {
                $consumedLogMap[$checkOut] = true;
            }

            $results[$cursor] = [
                'date' => $cursor,
                'shift_code' => (string) ($def['shift_code'] ?? $shiftCode),
                'shift_start_at' => (string) ($def['start_at'] ?? ''),
                'shift_end_at' => (string) ($def['end_at'] ?? ''),
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'work_hours' => $workHours,
                'overtime_hours' => $overtimeHours,
                'is_off' => false,
                'is_missing_checkout' => $checkIn !== null && $checkOut === null,
            ];

            $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
        }

        return $results;
    }

    public static function insertLog(array $data): void
    {
        $resolvedEmployeeId = self::resolveEmployeeIdForLog(
            (int) ($data['company_id'] ?? 0),
            isset($data['employee_id']) ? (int) $data['employee_id'] : 0,
            trim((string) ($data['device_user_id'] ?? ''))
        );

        DB::table('attendance_logs')->insert([
            'company_id' => $data['company_id'],
            'employee_id' => $resolvedEmployeeId,
            'device_user_id' => $data['device_user_id'],
            'scan_time' => $data['scan_time'],
            'verify_type' => $data['verify_type'],
            'device_id' => $data['device_id'],
        ]);
    }

    public static function logsByCompany(int $companyId, int $limit = 200, int $offset = 0, array $filters = [])
    {
        $query = DB::table('attendance_logs as l')
            ->leftJoin('employees as e', 'e.id', '=', 'l.employee_id')
            ->where('l.company_id', $companyId);

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($q2) use ($like) {
                $q2->where('e.name', 'like', $like)
                    ->orWhere('l.device_user_id', 'like', $like)
                    ->orWhere('l.device_id', 'like', $like);
            });
        }
        $verifyType = trim((string)($filters['verify_type'] ?? ''));
        if ($verifyType !== '') {
            $query->where('l.verify_type', $verifyType);
        }
        $deviceId = trim((string)($filters['device_id'] ?? ''));
        if ($deviceId !== '') {
            $query->where('l.device_id', $deviceId);
        }
        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $query->whereRaw('DATE(l.scan_time) >= ?', [$dateFrom]);
        }
        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $query->whereRaw('DATE(l.scan_time) <= ?', [$dateTo]);
        }

        return $query
            ->orderByDesc('l.scan_time')
            ->offset($offset)
            ->limit($limit)
            ->select('l.*', 'e.name')
            ->get();
    }

    public static function logsCountByCompany(int $companyId, array $filters = []): int
    {
        $query = DB::table('attendance_logs as l')
            ->leftJoin('employees as e', 'e.id', '=', 'l.employee_id')
            ->where('l.company_id', $companyId);

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($q2) use ($like) {
                $q2->where('e.name', 'like', $like)
                    ->orWhere('l.device_user_id', 'like', $like)
                    ->orWhere('l.device_id', 'like', $like);
            });
        }
        $verifyType = trim((string)($filters['verify_type'] ?? ''));
        if ($verifyType !== '') {
            $query->where('l.verify_type', $verifyType);
        }
        $deviceId = trim((string)($filters['device_id'] ?? ''));
        if ($deviceId !== '') {
            $query->where('l.device_id', $deviceId);
        }
        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $query->whereRaw('DATE(l.scan_time) >= ?', [$dateFrom]);
        }
        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $query->whereRaw('DATE(l.scan_time) <= ?', [$dateTo]);
        }

        return (int) $query->count();
    }

    public static function deleteLogByCompany(int $companyId, int $logId): int
    {
        $affectedDate = DB::table('attendance_logs')
            ->where('id', $logId)
            ->where('company_id', $companyId)
            ->selectRaw('DATE(scan_time) as scan_date')
            ->value('scan_date');

        $deleted = DB::table('attendance_logs')
            ->where('id', $logId)
            ->where('company_id', $companyId)
            ->delete();

        if ($affectedDate) {
            self::rebuildDailyForDates($companyId, [(string) $affectedDate]);
        }

        return $deleted;
    }

    public static function deleteLogsByCompany(int $companyId, array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', (array) $ids), static function ($v) {
            return $v > 0;
        }));
        if (count($ids) === 0) {
            return 0;
        }
        $affectedDates = DB::table('attendance_logs')
            ->where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->selectRaw('DISTINCT DATE(scan_time) as scan_date')
            ->pluck('scan_date')
            ->filter()
            ->values()
            ->all();

        $deleted = DB::table('attendance_logs')
            ->where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->delete();

        if (!empty($affectedDates)) {
            self::rebuildDailyForDates($companyId, $affectedDates);
        }

        return $deleted;
    }

    public static function deleteLogsByCompanyDateRange(int $companyId, string $dateFrom, string $dateTo): int
    {
        $deleted = DB::table('attendance_logs')
            ->where('company_id', $companyId)
            ->whereRaw('DATE(scan_time) >= ?', [$dateFrom])
            ->whereRaw('DATE(scan_time) <= ?', [$dateTo])
            ->delete();

        self::clearDailyByCompanyDateRange($companyId, $dateFrom, $dateTo);

        return $deleted;
    }

    public static function deleteAllLogsByCompany(int $companyId): int
    {
        $deleted = DB::table('attendance_logs')
            ->where('company_id', $companyId)
            ->delete();

        self::clearAllDailyByCompany($companyId);

        return $deleted;
    }

    private static function resolveEmployeeIdByCompanyNik(int $companyId, string $deviceUserId): ?int
    {
        if ($companyId <= 0 || $deviceUserId === '') {
            return null;
        }

        $exact = DB::table('employees')
            ->where('company_id', $companyId)
            ->where('nik', $deviceUserId)
            ->value('id');
        if ($exact) {
            return (int) $exact;
        }

        if (!preg_match('/^\d+$/', $deviceUserId)) {
            return null;
        }

        $candidates = [$deviceUserId];
        if (strlen($deviceUserId) <= 4) {
            $candidates[] = str_pad($deviceUserId, 4, '0', STR_PAD_LEFT);
        }
        $candidates = array_values(array_unique(array_filter($candidates, static fn ($v) => $v !== '')));

        foreach ($candidates as $suffix) {
            $rows = DB::table('employees')
                ->where('company_id', $companyId)
                ->where('nik', 'like', '%' . $suffix)
                ->limit(2)
                ->pluck('id');
            if ($rows->count() === 1) {
                return (int) ($rows->first() ?? 0) ?: null;
            }
        }

        return null;
    }

    private static function resolveEmployeeIdByHistory(int $companyId, string $deviceUserId): ?int
    {
        if ($companyId <= 0 || $deviceUserId === '') {
            return null;
        }

        $rows = DB::table('attendance_logs')
            ->where('company_id', $companyId)
            ->where('device_user_id', $deviceUserId)
            ->whereNotNull('employee_id')
            ->groupBy('employee_id')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->select('employee_id', DB::raw('COUNT(*) as total'))
            ->limit(2)
            ->get();

        if ($rows->count() !== 1) {
            return null;
        }

        return (int) ($rows->first()->employee_id ?? 0) ?: null;
    }

    private static function resolveEmployeeIdForLog(int $companyId, int $employeeId, string $deviceUserId): ?int
    {
        if ($employeeId > 0) {
            return $employeeId;
        }
        if ($companyId <= 0 || $deviceUserId === '') {
            return null;
        }

        $byMap = self::resolveEmployeeIdByDeviceUserMap($companyId, $deviceUserId);
        if ($byMap !== null) {
            return $byMap;
        }

        $byNik = self::resolveEmployeeIdByCompanyNik($companyId, $deviceUserId);
        if ($byNik !== null) {
            return $byNik;
        }

        return self::resolveEmployeeIdByHistory($companyId, $deviceUserId);
    }

    public static function backfillMissingEmployeeIdsByCompany(int $companyId, int $maxDeviceUsers = 300): int
    {
        if ($companyId <= 0) {
            return 0;
        }

        $updated = 0;
        $affectedDates = [];
        $deviceUsers = DB::table('attendance_logs')
            ->where('company_id', $companyId)
            ->whereNull('employee_id')
            ->select('device_user_id')
            ->whereRaw("TRIM(COALESCE(device_user_id, '')) <> ''")
            ->groupBy('device_user_id')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(max(1, $maxDeviceUsers))
            ->get();

        foreach ($deviceUsers as $row) {
            $deviceUserId = trim((string) ($row->device_user_id ?? ''));
            if ($deviceUserId === '') {
                continue;
            }
            $resolved = self::resolveEmployeeIdForLog(
                $companyId,
                0,
                $deviceUserId
            );
            if (!$resolved) {
                continue;
            }

            $dates = DB::table('attendance_logs')
                ->where('company_id', $companyId)
                ->whereNull('employee_id')
                ->where('device_user_id', $deviceUserId)
                ->selectRaw('DATE(scan_time) as log_date')
                ->distinct()
                ->pluck('log_date')
                ->all();
            if (count($dates) > 0) {
                $affectedDates = array_merge($affectedDates, $dates);
            }

            $updatedRows = DB::table('attendance_logs')
                ->where('company_id', $companyId)
                ->whereNull('employee_id')
                ->where('device_user_id', $deviceUserId)
                ->update(['employee_id' => (int) $resolved]);
            $updated += $updatedRows;
            if ($updatedRows > 0) {
                self::upsertDeviceUserEmployeeMap($companyId, (int) $resolved, $deviceUserId);
            }
        }

        if ($updated > 0 && count($affectedDates) > 0) {
            self::rebuildDailyForDates($companyId, $affectedDates);
        }

        return $updated;
    }

    private static function rebuildDailyForDates(int $companyId, array $dates): void
    {
        $dates = array_values(array_unique(array_filter(array_map(static function ($value) {
            $date = trim((string) $value);
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
        }, $dates))));

        if (count($dates) === 0) {
            return;
        }

        // Shift malam dapat membuat OUT jatuh di hari berikutnya, jadi ketika ada
        // perubahan log di suatu tanggal, tanggal sebelumnya juga ikut direbuild.
        $expandedDates = [];
        foreach ($dates as $date) {
            $expandedDates[] = $date;
            $expandedDates[] = date('Y-m-d', strtotime($date . ' -1 day'));
        }
        $dates = array_values(array_unique(array_filter($expandedDates)));

        foreach ($dates as $date) {
            self::rebuildDaily($companyId, $date);
        }

        self::pruneDailyRowsWithoutLogs($companyId, $dates);
    }

    private static function pruneDailyRowsWithoutLogs(int $companyId, array $dates): void
    {
        $hasSpecialLeave = self::hasSpecialLeaveExcuseColumn();
        foreach ($dates as $date) {
            $keepSpecialCond = $hasSpecialLeave ? ' AND COALESCE(d.is_special_leave_excused, 0) = 0' : '';
            DB::statement(
                'DELETE d
                 FROM attendance_daily d
                 INNER JOIN employees e ON e.id = d.employee_id
                 WHERE e.company_id = ?
                   AND d.date = ?
                   AND COALESCE(d.is_leave_excused, 0) = 0
                   AND COALESCE(d.is_sick_doctor_excused, 0) = 0'
                   . $keepSpecialCond . '
                   AND NOT EXISTS (
                       SELECT 1
                       FROM attendance_logs l
                       WHERE l.company_id = ?
                         AND l.employee_id = d.employee_id
                         AND DATE(l.scan_time) = d.date
                   )',
                [$companyId, $date, $companyId]
            );
        }
    }

    private static function clearDailyByCompanyDateRange(int $companyId, string $dateFrom, string $dateTo): void
    {
        DB::statement(
            'DELETE d
             FROM attendance_daily d
             INNER JOIN employees e ON e.id = d.employee_id
             WHERE e.company_id = ?
               AND d.date >= ?
               AND d.date <= ?',
            [$companyId, $dateFrom, $dateTo]
        );
    }

    private static function clearAllDailyByCompany(int $companyId): void
    {
        DB::statement(
            'DELETE d
             FROM attendance_daily d
             INNER JOIN employees e ON e.id = d.employee_id
             WHERE e.company_id = ?',
            [$companyId]
        );
    }

    public static function rebuildDaily(int $companyId, string $date): void
    {
        self::ensureDailySchema();
        $rows = DB::table('attendance_logs as l')
            ->join('employees as e', 'e.id', '=', 'l.employee_id')
            ->select(
                'l.employee_id',
                DB::raw('DATE(l.scan_time) as d'),
                DB::raw('MIN(l.scan_time) as min_time'),
                DB::raw('MAX(l.scan_time) as max_time'),
                'e.position'
            )
            ->where('l.company_id', $companyId)
            ->whereRaw('DATE(l.scan_time) = ?', [$date])
            ->whereNotNull('l.employee_id')
            ->where('e.company_id', $companyId)
            ->groupBy('l.employee_id', DB::raw('DATE(l.scan_time)'), 'e.position')
            ->get();

        foreach ($rows as $row) {
            $checkIn = $row->min_time;
            $checkOut = $row->max_time;
            $isSecurity = self::isSecurityPosition((string) ($row->position ?? ''));
            if ($isSecurity) {
                $securityMap = self::buildSecurityShiftDailyMap(
                    $companyId,
                    (int) $row->employee_id,
                    date('Y-m-d', strtotime($date . ' -1 day')),
                    $date
                );
                $securityDaily = $securityMap[$date] ?? null;
                if (!$securityDaily || !empty($securityDaily['is_off'])) {
                    DB::table('attendance_daily')
                        ->where('employee_id', (int) $row->employee_id)
                        ->where('date', $date)
                        ->delete();
                    continue;
                }
                $checkIn = $securityDaily['check_in'] ?? null;
                $checkOut = $securityDaily['check_out'] ?? null;
                $workHours = round((float) ($securityDaily['work_hours'] ?? 0), 2);
                $overtime = round((float) ($securityDaily['overtime_hours'] ?? 0), 2);
                DB::statement('INSERT INTO attendance_daily (employee_id, date, check_in, check_out, work_hours, overtime_hours)
                               VALUES (?,?,?,?,?,?)
                               ON DUPLICATE KEY UPDATE
                                 check_in = VALUES(check_in),
                                 check_out = VALUES(check_out),
                                 work_hours = VALUES(work_hours),
                                 overtime_hours = CASE
                                     WHEN no_overtime_permit = 1 THEN 0
                                     ELSE VALUES(overtime_hours)
                                 END', [
                    $row->employee_id,
                    $date,
                    $checkIn,
                    $checkOut,
                    $workHours,
                    $overtime,
                ]);
                continue;
            }
            $workHours = 0;
            $overtime = 0;
            if ($checkIn && $checkOut) {
                $diff = max(0, (strtotime($checkOut) - strtotime($checkIn))) / 3600;
                $workHours = round($diff, 2);
                $calc = OvertimeCalculator::calculateForRecord($companyId, (string) $row->d, (string) $checkIn, (string) $checkOut, false);
                $overtime = round((float) ($calc['hours'] ?? 0), 2);
            }
            DB::statement('INSERT INTO attendance_daily (employee_id, date, check_in, check_out, work_hours, overtime_hours)
                           VALUES (?,?,?,?,?,?)
                           ON DUPLICATE KEY UPDATE
                             check_in = VALUES(check_in),
                             check_out = VALUES(check_out),
                             work_hours = VALUES(work_hours),
                             overtime_hours = CASE
                                 WHEN no_overtime_permit = 1 THEN 0
                                 ELSE VALUES(overtime_hours)
                             END', [
                $row->employee_id,
                $row->d,
                $checkIn,
                $checkOut,
                $workHours,
                $overtime,
            ]);
        }

        self::pruneDailyRowsWithoutLogs($companyId, [$date]);
    }

    public static function rebuildMonthly(int $companyId, int $month, int $year): void
    {
        $start = new DateTime(sprintf('%04d-%02d-01', $year, $month));
        $end = (clone $start)->modify('last day of this month');
        $cur = clone $start;
        while ($cur <= $end) {
            self::rebuildDaily($companyId, $cur->format('Y-m-d'));
            $cur->modify('+1 day');
        }
    }

    public static function dailyAllByCompany(int $companyId, string $date, bool $onlyPresent = false)
    {
        self::ensureDailySchema();
        $join = $onlyPresent ? 'join' : 'leftJoin';
        $query = DB::table('employees as e')
            ->$join('attendance_daily as d', function ($q) use ($date) {
                $q->on('d.employee_id', '=', 'e.id')->where('d.date', '=', $date);
            })
            ->where('e.company_id', $companyId)
            ->where(function ($q) {
                $q->whereNull('e.employment_status')
                    ->orWhereRaw("TRIM(COALESCE(e.employment_status, '')) = ''")
                    ->orWhereRaw(
                        "LOWER(TRIM(COALESCE(e.employment_status, ''))) NOT IN (?, ?, ?, ?)",
                        ['komisaris', 'freelance', 'frelance', 'frelancer']
                    );
            })
            ->where(function ($q) {
                $q->whereNull('e.active_status')
                    ->orWhereRaw("TRIM(COALESCE(e.active_status, '')) = ''")
                    ->orWhereRaw(
                        "LOWER(TRIM(COALESCE(e.active_status, ''))) NOT IN (?, ?, ?)",
                        [
                            strtolower(Employee::ACTIVE_STATUS_RESIGN),
                            strtolower(Employee::ACTIVE_STATUS_PHK),
                            strtolower(Employee::ACTIVE_STATUS_HABIS_KONTRAK),
                        ]
                    );
            });

        $selectColumns = [
            'e.id as employee_id',
            'e.name',
            'e.nik',
            'd.date',
            'd.check_in',
            'd.check_out',
            'd.work_hours',
            'd.overtime_hours',
            DB::raw('COALESCE(d.no_overtime_permit, 0) AS no_overtime_permit'),
            DB::raw('COALESCE(d.is_leave_excused, 0) AS is_leave_excused'),
            DB::raw('COALESCE(d.is_sick_doctor_excused, 0) AS is_sick_doctor_excused'),
        ];
        if (self::hasSpecialLeaveExcuseColumn()) {
            $selectColumns[] = DB::raw('COALESCE(d.is_special_leave_excused, 0) AS is_special_leave_excused');
        } else {
            $selectColumns[] = DB::raw('0 AS is_special_leave_excused');
        }

        return $query
            ->orderBy('e.name')
            ->select($selectColumns)
            ->get();
    }

    public static function logCountByCompanyDate(int $companyId, string $date): int
    {
        return (int) DB::table('attendance_logs')
            ->where('company_id', $companyId)
            ->whereRaw('DATE(scan_time) = ?', [$date])
            ->count();
    }

    public static function latestLogDateByCompany(int $companyId): ?string
    {
        $val = DB::table('attendance_logs')
            ->where('company_id', $companyId)
            ->selectRaw('DATE(MAX(scan_time)) as max_date')
            ->value('max_date');
        return $val ?: null;
    }

    public static function saveNoOvertimePermitByCompanyDate(int $companyId, string $date, array $allEmployeeIds, array $checkedEmployeeIds): int
    {
        self::ensureDailySchema();

        $allIds = array_values(array_filter(array_map('intval', (array) $allEmployeeIds), static function ($v) {
            return $v > 0;
        }));
        $checked = array_flip(array_values(array_filter(array_map('intval', (array) $checkedEmployeeIds), static function ($v) {
            return $v > 0;
        })));

        if (count($allIds) === 0) {
            return 0;
        }

        $updated = 0;
        foreach ($allIds as $employeeId) {
            $flag = isset($checked[$employeeId]) ? 1 : 0;
            $daily = DB::table('attendance_daily as d')
                ->join('employees as e', 'e.id', '=', 'd.employee_id')
                ->where('d.employee_id', $employeeId)
                ->where('d.date', $date)
                ->where('e.company_id', $companyId)
                ->select('d.check_in', 'd.check_out')
                ->first();

            $overtime = 0.0;
            if ($flag === 0 && $daily && !empty($daily->check_in) && !empty($daily->check_out)) {
                $calc = OvertimeCalculator::calculateForRecord(
                    $companyId,
                    $date,
                    (string) $daily->check_in,
                    (string) $daily->check_out,
                    false
                );
                $overtime = round((float) ($calc['hours'] ?? 0), 2);
            }

            $updated += DB::table('attendance_daily as d')
                ->join('employees as e', 'e.id', '=', 'd.employee_id')
                ->where('d.employee_id', $employeeId)
                ->where('d.date', $date)
                ->where('e.company_id', $companyId)
                ->update([
                    'd.no_overtime_permit' => $flag,
                    'd.overtime_hours' => $flag === 1 ? 0 : $overtime,
                ]);
        }
        return $updated;
    }

    public static function saveExcuseByCompanyDate(int $companyId, string $date, array $allEmployeeIds, array $leaveCheckedIds, array $sickDoctorCheckedIds, array $specialLeaveCheckedIds): int
    {
        self::ensureDailySchema();

        $allIds = array_values(array_filter(array_map('intval', (array) $allEmployeeIds), static function ($v) {
            return $v > 0;
        }));
        $leaveMap = array_flip(array_values(array_filter(array_map('intval', (array) $leaveCheckedIds), static function ($v) {
            return $v > 0;
        })));
        $sickMap = array_flip(array_values(array_filter(array_map('intval', (array) $sickDoctorCheckedIds), static function ($v) {
            return $v > 0;
        })));
        $specialLeaveMap = array_flip(array_values(array_filter(array_map('intval', (array) $specialLeaveCheckedIds), static function ($v) {
            return $v > 0;
        })));

        if (count($allIds) === 0) {
            return 0;
        }

        $updated = 0;
        foreach ($allIds as $employeeId) {
            $leaveFlag = isset($leaveMap[$employeeId]) ? 1 : 0;
            $sickFlag = isset($sickMap[$employeeId]) ? 1 : 0;
            $specialLeaveFlag = isset($specialLeaveMap[$employeeId]) ? 1 : 0;
            if ($leaveFlag === 1 && $sickFlag === 1) {
                $sickFlag = 0;
            }
            if ($specialLeaveFlag === 1) {
                $leaveFlag = 0;
                $sickFlag = 0;
            }

            if (self::hasSpecialLeaveExcuseColumn()) {
                DB::statement('INSERT INTO attendance_daily (employee_id, date, check_in, check_out, work_hours, overtime_hours, no_overtime_permit, is_leave_excused, is_sick_doctor_excused, is_special_leave_excused)
                               VALUES (?,?,?,?,?,?,?,?,?,?)
                               ON DUPLICATE KEY UPDATE
                                 is_leave_excused = VALUES(is_leave_excused),
                                 is_sick_doctor_excused = VALUES(is_sick_doctor_excused),
                                 is_special_leave_excused = VALUES(is_special_leave_excused)', [
                    $employeeId, $date, null, null, 0, 0, 0, $leaveFlag, $sickFlag, $specialLeaveFlag
                ]);
                $updated += DB::update('UPDATE attendance_daily d
                                       JOIN employees e ON e.id = d.employee_id
                                       SET d.is_leave_excused = ?,
                                           d.is_sick_doctor_excused = ?,
                                           d.is_special_leave_excused = ?
                                       WHERE d.employee_id = ? AND d.date = ? AND e.company_id = ?', [
                    $leaveFlag, $sickFlag, $specialLeaveFlag, $employeeId, $date, $companyId
                ]);
            } else {
                DB::statement('INSERT INTO attendance_daily (employee_id, date, check_in, check_out, work_hours, overtime_hours, no_overtime_permit, is_leave_excused, is_sick_doctor_excused)
                               VALUES (?,?,?,?,?,?,?,?,?)
                               ON DUPLICATE KEY UPDATE
                                 is_leave_excused = VALUES(is_leave_excused),
                                 is_sick_doctor_excused = VALUES(is_sick_doctor_excused)', [
                    $employeeId, $date, null, null, 0, 0, 0, $leaveFlag, $sickFlag
                ]);
                $updated += DB::update('UPDATE attendance_daily d
                                       JOIN employees e ON e.id = d.employee_id
                                       SET d.is_leave_excused = ?,
                                           d.is_sick_doctor_excused = ?
                                       WHERE d.employee_id = ? AND d.date = ? AND e.company_id = ?', [
                    $leaveFlag, $sickFlag, $employeeId, $date, $companyId
                ]);
            }
        }
        return $updated;
    }
}
