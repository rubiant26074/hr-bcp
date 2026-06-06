<?php

namespace App\Services;

use DateTime;
use App\Models\Employee;
use App\Services\OvertimeCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttendanceService
{
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

            $updated += DB::table('attendance_logs')
                ->where('company_id', $companyId)
                ->whereNull('employee_id')
                ->where('device_user_id', $deviceUserId)
                ->update(['employee_id' => (int) $resolved]);
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

        foreach ($dates as $date) {
            self::rebuildDaily($companyId, $date);
        }

        self::pruneDailyRowsWithoutLogs($companyId, $dates);
    }

    private static function pruneDailyRowsWithoutLogs(int $companyId, array $dates): void
    {
        foreach ($dates as $date) {
            DB::statement(
                'DELETE d
                 FROM attendance_daily d
                 INNER JOIN employees e ON e.id = d.employee_id
                 WHERE e.company_id = ?
                   AND d.date = ?
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
        $rows = DB::table('attendance_logs')
            ->select('employee_id', DB::raw('DATE(scan_time) as d'), DB::raw('MIN(scan_time) as min_time'), DB::raw('MAX(scan_time) as max_time'))
            ->where('company_id', $companyId)
            ->whereRaw('DATE(scan_time) = ?', [$date])
            ->whereNotNull('employee_id')
            ->groupBy('employee_id', DB::raw('DATE(scan_time)'))
            ->get();

        foreach ($rows as $row) {
            $checkIn = $row->min_time;
            $checkOut = $row->max_time;
            $workHours = 0;
            $overtime = 0;
            if ($checkIn && $checkOut) {
                $diff = (strtotime($checkOut) - strtotime($checkIn)) / 3600;
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

        return $query
            ->orderBy('e.name')
            ->select(
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
                DB::raw('COALESCE(d.is_sick_doctor_excused, 0) AS is_sick_doctor_excused')
            )
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

    public static function saveExcuseByCompanyDate(int $companyId, string $date, array $allEmployeeIds, array $leaveCheckedIds, array $sickDoctorCheckedIds): int
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

        if (count($allIds) === 0) {
            return 0;
        }

        $updated = 0;
        foreach ($allIds as $employeeId) {
            $leaveFlag = isset($leaveMap[$employeeId]) ? 1 : 0;
            $sickFlag = isset($sickMap[$employeeId]) ? 1 : 0;
            if ($leaveFlag === 1 && $sickFlag === 1) {
                $sickFlag = 0;
            }

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
        return $updated;
    }
}
