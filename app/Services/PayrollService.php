<?php

namespace App\Services;

use DateTime;
use App\Models\Employee;
use App\Services\OvertimeCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayrollService
{
    private const PAYROLL_CLOSING_DAY = 20;

    private static ?bool $hasNoOtPermitColumn = null;
    private static ?bool $hasLeaveExcuseColumn = null;
    private static ?bool $hasSickExcuseColumn = null;
    private static ?bool $hasSpecialLeaveExcuseColumn = null;
    private static ?bool $hasManualOvertimeColumns = null;
    private static ?bool $hasOvertimeModeColumn = null;
    private static ?bool $hasOvertimeManualHoursColumn = null;
    private static ?bool $hasOvertimeManualHour1Column = null;
    private static ?bool $hasOvertimeManualHour2Column = null;
    private static ?bool $hasOvertimeManualHoliday8Column = null;
    private static ?bool $hasOvertimeManualHoliday9Column = null;
    private static ?bool $hasAbsenceModeColumn = null;
    private static ?bool $hasManualPresentDaysColumn = null;

    private static function attendanceDailyHasNoOtPermitColumn(): bool
    {
        if (self::$hasNoOtPermitColumn !== null) {
            return self::$hasNoOtPermitColumn;
        }
        try {
            self::$hasNoOtPermitColumn = Schema::hasColumn('attendance_daily', 'no_overtime_permit');
        } catch (\Throwable $e) {
            self::$hasNoOtPermitColumn = false;
        }
        return self::$hasNoOtPermitColumn;
    }

    private static function attendanceDailyHasLeaveExcuseColumn(): bool
    {
        if (self::$hasLeaveExcuseColumn !== null) {
            return self::$hasLeaveExcuseColumn;
        }
        try {
            self::$hasLeaveExcuseColumn = Schema::hasColumn('attendance_daily', 'is_leave_excused');
        } catch (\Throwable $e) {
            self::$hasLeaveExcuseColumn = false;
        }
        return self::$hasLeaveExcuseColumn;
    }

    private static function attendanceDailyHasSickExcuseColumn(): bool
    {
        if (self::$hasSickExcuseColumn !== null) {
            return self::$hasSickExcuseColumn;
        }
        try {
            self::$hasSickExcuseColumn = Schema::hasColumn('attendance_daily', 'is_sick_doctor_excused');
        } catch (\Throwable $e) {
            self::$hasSickExcuseColumn = false;
        }
        return self::$hasSickExcuseColumn;
    }

    private static function attendanceDailyHasSpecialLeaveExcuseColumn(): bool
    {
        if (self::$hasSpecialLeaveExcuseColumn !== null) {
            return self::$hasSpecialLeaveExcuseColumn;
        }
        try {
            self::$hasSpecialLeaveExcuseColumn = Schema::hasColumn('attendance_daily', 'is_special_leave_excused');
        } catch (\Throwable $e) {
            self::$hasSpecialLeaveExcuseColumn = false;
        }
        return self::$hasSpecialLeaveExcuseColumn;
    }

    private static function attendanceDailyHasManualOvertimeColumns(): bool
    {
        if (self::$hasManualOvertimeColumns !== null) {
            return self::$hasManualOvertimeColumns;
        }
        try {
            self::$hasManualOvertimeColumns =
                Schema::hasColumn('attendance_daily', 'overtime_hours_manual')
                && Schema::hasColumn('attendance_daily', 'overtime_hours_is_manual');
        } catch (\Throwable $e) {
            self::$hasManualOvertimeColumns = false;
        }
        return self::$hasManualOvertimeColumns;
    }

    private static function payrollSettingHasOvertimeModeColumn(): bool
    {
        if (self::$hasOvertimeModeColumn !== null) {
            return self::$hasOvertimeModeColumn;
        }
        try {
            self::$hasOvertimeModeColumn = Schema::hasColumn('payroll_setting', 'overtime_mode');
        } catch (\Throwable $e) {
            self::$hasOvertimeModeColumn = false;
        }
        return self::$hasOvertimeModeColumn;
    }

    private static function payrollSettingHasOvertimeManualHoursColumn(): bool
    {
        if (self::$hasOvertimeManualHoursColumn !== null) {
            return self::$hasOvertimeManualHoursColumn;
        }
        try {
            self::$hasOvertimeManualHoursColumn = Schema::hasColumn('payroll_setting', 'overtime_manual_hours');
        } catch (\Throwable $e) {
            self::$hasOvertimeManualHoursColumn = false;
        }
        return self::$hasOvertimeManualHoursColumn;
    }

    private static function payrollSettingHasOvertimeManualHour1Column(): bool
    {
        if (self::$hasOvertimeManualHour1Column !== null) {
            return self::$hasOvertimeManualHour1Column;
        }
        try {
            self::$hasOvertimeManualHour1Column = Schema::hasColumn('payroll_setting', 'overtime_manual_hour_1');
        } catch (\Throwable $e) {
            self::$hasOvertimeManualHour1Column = false;
        }
        return self::$hasOvertimeManualHour1Column;
    }

    private static function payrollSettingHasOvertimeManualHour2Column(): bool
    {
        if (self::$hasOvertimeManualHour2Column !== null) {
            return self::$hasOvertimeManualHour2Column;
        }
        try {
            self::$hasOvertimeManualHour2Column = Schema::hasColumn('payroll_setting', 'overtime_manual_hour_2');
        } catch (\Throwable $e) {
            self::$hasOvertimeManualHour2Column = false;
        }
        return self::$hasOvertimeManualHour2Column;
    }

    private static function payrollSettingHasOvertimeManualHoliday8Column(): bool
    {
        if (self::$hasOvertimeManualHoliday8Column !== null) {
            return self::$hasOvertimeManualHoliday8Column;
        }
        try {
            self::$hasOvertimeManualHoliday8Column = Schema::hasColumn('payroll_setting', 'overtime_manual_holiday_8');
        } catch (\Throwable $e) {
            self::$hasOvertimeManualHoliday8Column = false;
        }
        return self::$hasOvertimeManualHoliday8Column;
    }

    private static function payrollSettingHasOvertimeManualHoliday9Column(): bool
    {
        if (self::$hasOvertimeManualHoliday9Column !== null) {
            return self::$hasOvertimeManualHoliday9Column;
        }
        try {
            self::$hasOvertimeManualHoliday9Column = Schema::hasColumn('payroll_setting', 'overtime_manual_holiday_9');
        } catch (\Throwable $e) {
            self::$hasOvertimeManualHoliday9Column = false;
        }
        return self::$hasOvertimeManualHoliday9Column;
    }

    private static function payrollSettingHasAbsenceModeColumn(): bool
    {
        if (self::$hasAbsenceModeColumn !== null) {
            return self::$hasAbsenceModeColumn;
        }
        try {
            self::$hasAbsenceModeColumn = Schema::hasColumn('payroll_setting', 'absence_mode');
        } catch (\Throwable $e) {
            self::$hasAbsenceModeColumn = false;
        }
        return self::$hasAbsenceModeColumn;
    }

    private static function payrollSettingHasManualPresentDaysColumn(): bool
    {
        if (self::$hasManualPresentDaysColumn !== null) {
            return self::$hasManualPresentDaysColumn;
        }
        try {
            self::$hasManualPresentDaysColumn = Schema::hasColumn('payroll_setting', 'manual_present_days');
        } catch (\Throwable $e) {
            self::$hasManualPresentDaysColumn = false;
        }
        return self::$hasManualPresentDaysColumn;
    }

    private static function isAllInStatus(string $employmentStatus): bool
    {
        return str_contains($employmentStatus, 'ALL-IN');
    }

    private static function isFreelanceStatus(string $employmentStatus): bool
    {
        return in_array($employmentStatus, ['FREELANCE', 'FRELANCE'], true);
    }

    private static function isProbationStatus(string $employmentStatus): bool
    {
        return str_contains($employmentStatus, 'PERCOBAAN');
    }

    private static function isOfficeSupportHarianException(?string $position): bool
    {
        $p = strtoupper(trim((string) $position));
        return in_array($p, ['OFFICE BOY', 'OFFICE GIRL'], true);
    }

    private static function isSecurityPosition(?string $position): bool
    {
        $p = strtoupper(trim((string) $position));
        if ($p === '') {
            return false;
        }
        return str_contains($p, 'SECURITY') || str_contains($p, 'SCURITY') || str_contains($p, 'SATPAM');
    }

    private static function parseDateTimeToSeconds(string $dateTime): ?int
    {
        $dt = trim($dateTime);
        if ($dt === '') {
            return null;
        }
        $ts = strtotime($dt);
        if ($ts === false) {
            return null;
        }
        return (int) date('H', $ts) * 3600 + (int) date('i', $ts) * 60 + (int) date('s', $ts);
    }

    private static function parseTimeToSeconds(?string $time): ?int
    {
        $t = trim((string) $time);
        if ($t === '') {
            return null;
        }
        $parts = explode(':', $t);
        if (count($parts) < 2) {
            return null;
        }
        $h = (int) $parts[0];
        $m = (int) $parts[1];
        $s = isset($parts[2]) ? (int) $parts[2] : 0;
        return ($h * 3600) + ($m * 60) + $s;
    }

    private static function resolveSecurityShiftStartSeconds(?string $checkInDateTime): ?int
    {
        $checkInSec = self::parseDateTimeToSeconds((string) $checkInDateTime);
        if ($checkInSec === null) {
            return null;
        }
        $shiftStarts = [7 * 3600, 15 * 3600, 23 * 3600];
        $nearest = null;
        $nearestDiff = null;
        foreach ($shiftStarts as $startSec) {
            $diff = abs($checkInSec - $startSec);
            $diff = min($diff, (24 * 3600) - $diff);
            if ($nearestDiff === null || $diff < $nearestDiff) {
                $nearestDiff = $diff;
                $nearest = $startSec;
            }
        }
        return $nearest;
    }

    private static function resolveHarianNormalPaidHours(
        iterable $dailyRows,
        float $standardHoursPerDay,
        ?string $workTimeStart,
        bool $includeEarlyForException,
        bool $useSecurityShiftTemplate = false
    ): float
    {
        $total = 0.0;
        $standardHoursPerDay = max(0.0, $standardHoursPerDay);
        $workStartSec = self::parseTimeToSeconds($workTimeStart);

        foreach ($dailyRows as $row) {
            $workHours = max(0.0, (float) ($row->work_hours ?? 0));
            if ($workHours <= 0.0) {
                continue;
            }

            $rowStandardHours = $useSecurityShiftTemplate ? 8.0 : $standardHoursPerDay;
            $rowWorkStartSec = $useSecurityShiftTemplate
                ? self::resolveSecurityShiftStartSeconds((string) ($row->check_in ?? ''))
                : $workStartSec;

            $normalCapped = $rowStandardHours > 0
                ? min($workHours, $rowStandardHours)
                : $workHours;

            $earlyHours = 0.0;
            if ($rowWorkStartSec !== null) {
                $checkInSec = self::parseDateTimeToSeconds((string) ($row->check_in ?? ''));
                if ($checkInSec !== null && $checkInSec < $rowWorkStartSec) {
                    $earlyHours = min($workHours, ($rowWorkStartSec - $checkInSec) / 3600);
                }
            }

            // Default HARIAN: jam sebelum jam kerja normal tidak dibayar.
            $normalPaid = max(0.0, $normalCapped - $earlyHours);
            // Pengecualian OFFICE BOY / OFFICE GIRL: jam awal tetap dibayar.
            if ($includeEarlyForException) {
                $normalPaid += $earlyHours;
            }

            $total += $normalPaid;
        }

        return round(max(0.0, $total), 2);
    }

    private static function resolveAllInOvertimeRate(float $basicSalary, ?object $company): float
    {
        if ($basicSalary < 6000000 || $basicSalary > 10000000) {
            return 0.0;
        }
        if ($basicSalary < 7000000) {
            return (float) ($company->allin_ot_rate_6_7 ?? 30000);
        }
        if ($basicSalary < 8000000) {
            return (float) ($company->allin_ot_rate_7_8 ?? 35000);
        }
        if ($basicSalary < 9000000) {
            return (float) ($company->allin_ot_rate_8_9 ?? 40000);
        }
        return (float) ($company->allin_ot_rate_9_10 ?? 45000);
    }

    private static function resolveAllInOvertimeHours(object $daily, int $companyId, array $scheduledDateMap, ?float $manualOvertime = null): float
    {
        if ((int) ($daily->no_overtime_permit ?? 0) === 1) {
            return 0.0;
        }
        if ($manualOvertime !== null) {
            return max(0.0, $manualOvertime);
        }

        $workDate = (string) ($daily->date ?? '');
        $checkIn = trim((string) ($daily->check_in ?? ''));
        $checkOut = trim((string) ($daily->check_out ?? ''));
        if ($workDate === '' || $checkIn === '' || $checkOut === '') {
            return 0.0;
        }

        if (!isset($scheduledDateMap[$workDate])) {
            $calc = OvertimeCalculator::calculateForRecord($companyId, $workDate, $checkIn, $checkOut, false);
            return round(max(0.0, (float) ($calc['hours'] ?? 0)), 2);
        }

        try {
            $start = new \DateTimeImmutable($checkIn);
            $end = new \DateTimeImmutable($checkOut);
            $policyStart = new \DateTimeImmutable($workDate . ' 19:00:00');
        } catch (\Throwable $e) {
            return 0.0;
        }
        if ($end <= $policyStart || $end <= $start) {
            return 0.0;
        }

        $effectiveStart = $start > $policyStart ? $start : $policyStart;
        $minutes = max(0.0, ($end->getTimestamp() - $effectiveStart->getTimestamp()) / 60);

        // Kalau lembur melewati tengah malam, tetap kurangi jeda istirahat 00:00-01:00.
        $breakCursor = new \DateTimeImmutable($effectiveStart->format('Y-m-d') . ' 00:00:00');
        $breakLimit = new \DateTimeImmutable($end->format('Y-m-d') . ' 00:00:00');
        while ($breakCursor <= $breakLimit) {
            $breakStart = new \DateTimeImmutable($breakCursor->format('Y-m-d') . ' 00:00:00');
            $breakEnd = new \DateTimeImmutable($breakCursor->format('Y-m-d') . ' 01:00:00');
            $overlapStart = $effectiveStart > $breakStart ? $effectiveStart : $breakStart;
            $overlapEnd = $end < $breakEnd ? $end : $breakEnd;
            if ($overlapEnd > $overlapStart) {
                $minutes -= ($overlapEnd->getTimestamp() - $overlapStart->getTimestamp()) / 60;
            }
            $breakCursor = $breakCursor->modify('+1 day');
        }

        return round(max(0.0, $minutes / 60), 2);
    }

    private static function countWorkdaysExcludingSunday(string $startDate, string $endDate): int
    {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $workdays = 0;
        while ($start <= $end) {
            $dow = (int) $start->format('w');
            if ($dow !== 0 && $dow !== 6) {
                $workdays++;
            }
            $start->modify('+1 day');
        }
        return $workdays;
    }

    private static function applyExcludeInactiveEmployeeStatuses($query, string $column): void
    {
        $query->where(function ($q) use ($column) {
            $q->whereNull($column)
                ->orWhereRaw("TRIM(COALESCE($column, '')) = ''")
                ->orWhereRaw(
                    "LOWER(TRIM(COALESCE($column, ''))) NOT IN (?, ?, ?)",
                    [
                        strtolower(Employee::ACTIVE_STATUS_RESIGN),
                        strtolower(Employee::ACTIVE_STATUS_PHK),
                        strtolower(Employee::ACTIVE_STATUS_HABIS_KONTRAK),
                    ]
                );
        });
    }

    private static function resolveCompanyWorkDays(?object $company): array
    {
        $workDays = [];
        if ($company && !empty($company->work_days_json)) {
            $decoded = json_decode((string) $company->work_days_json, true);
            if (is_array($decoded)) {
                foreach ($decoded as $d) {
                    $v = trim((string) $d);
                    if ($v !== '') {
                        $workDays[] = $v;
                    }
                }
            }
        }

        if (count($workDays) > 0) {
            return array_values(array_unique($workDays));
        }

        $perWeek = (int) ($company->work_days_per_week ?? 6);
        if ($perWeek <= 5) {
            return ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
        }
        if ($perWeek === 6) {
            return ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        }
        return ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    }

    private static function scheduledWorkDates(int $companyId, string $startDate, string $endDate, ?object $company = null): array
    {
        $workDays = self::resolveCompanyWorkDays($company);
        $holidayMap = DB::table('holidays')
            ->where('company_id', 0)
            ->whereBetween('holiday_date', [$startDate, $endDate])
            ->pluck('holiday_date')
            ->map(static fn ($d) => (string) $d)
            ->flip()
            ->all();

        $dates = [];
        $cursor = new DateTime($startDate);
        $end = new DateTime($endDate);
        while ($cursor <= $end) {
            $date = $cursor->format('Y-m-d');
            $dow = $cursor->format('D');
            if (in_array($dow, $workDays, true) && !isset($holidayMap[$date])) {
                $dates[] = $date;
            }
            $cursor->modify('+1 day');
        }
        return $dates;
    }

    private static function effectiveScheduledWorkDatesForEmployee(array $scheduledDates, ?string $joinDate, ?string $lastWorkingDate = null): array
    {
        $startKey = null;
        $join = trim((string) $joinDate);
        if ($join !== '') {
            $joinTs = strtotime($join);
            if ($joinTs !== false) {
                $startKey = date('Y-m-d', $joinTs);
            }
        }

        $endKey = null;
        $lastWorking = trim((string) $lastWorkingDate);
        if ($lastWorking !== '') {
            $lastWorkingTs = strtotime($lastWorking);
            if ($lastWorkingTs !== false) {
                $endKey = date('Y-m-d', $lastWorkingTs);
            }
        }

        return array_values(array_filter($scheduledDates, static function ($date) use ($startKey, $endKey) {
            $date = (string) $date;
            if ($startKey !== null && $date < $startKey) {
                return false;
            }
            if ($endKey !== null && $date > $endKey) {
                return false;
            }
            return true;
        }));
    }

    private static function absenceDivisor(int $fullWorkdays, int $employeeWorkdays): float
    {
        if ($employeeWorkdays <= 0) {
            return 26.0;
        }

        return $employeeWorkdays < $fullWorkdays ? (float) $employeeWorkdays : 26.0;
    }

    private static function excusedScheduledDatesForEmployee(int $companyId, int $employeeId, array $scheduledDates): array
    {
        if (count($scheduledDates) === 0) {
            return [];
        }

        $scheduledSet = array_flip($scheduledDates);
        $minDate = $scheduledDates[0];
        $maxDate = $scheduledDates[count($scheduledDates) - 1];

        $rows = DB::table('absence_requests')
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('status', 'Approved')
            ->whereDate('date_start', '<=', $maxDate)
            ->whereDate('date_end', '>=', $minDate)
            ->where(function ($q) {
                $q->where('request_type', 'Cuti')
                    ->orWhereRaw('LOWER(COALESCE(reason, "")) LIKE ?', ['%cuti bersama%'])
                    ->orWhereRaw('LOWER(COALESCE(reason, "")) LIKE ?', ['%cuti lebaran%']);
            })
            ->get(['date_start', 'date_end']);

        $excused = [];
        foreach ($rows as $row) {
            $start = (string) ($row->date_start ?? '');
            $end = (string) ($row->date_end ?? '');
            if ($start === '' || $end === '') {
                continue;
            }
            if ($start > $end) {
                [$start, $end] = [$end, $start];
            }
            if ($start < $minDate) {
                $start = $minDate;
            }
            if ($end > $maxDate) {
                $end = $maxDate;
            }

            $cursor = new DateTime($start);
            $to = new DateTime($end);
            while ($cursor <= $to) {
                $date = $cursor->format('Y-m-d');
                if (isset($scheduledSet[$date])) {
                    $excused[$date] = true;
                }
                $cursor->modify('+1 day');
            }
        }

        // Tambahan: verifikasi bulanan (checklist Cuti/Izin) pada attendance_daily
        // juga dianggap hari berizin agar tidak kena potongan absensi payroll.
        if (self::attendanceDailyHasLeaveExcuseColumn()) {
            $leaveExcusedDates = DB::table('attendance_daily as d')
                ->join('employees as e2', 'e2.id', '=', 'd.employee_id')
                ->where('e2.company_id', $companyId)
                ->where('d.employee_id', $employeeId)
                ->whereIn('d.date', $scheduledDates)
                ->where('d.is_leave_excused', 1)
                ->distinct()
                ->pluck('d.date')
                ->map(static fn ($v) => (string) $v)
                ->filter(static fn ($v) => $v !== '')
                ->values()
                ->all();
            foreach ($leaveExcusedDates as $d) {
                $excused[$d] = true;
            }
        }

        // Checklist SK-SD (sakit dengan izin dokter) juga dianggap berizin
        // agar tidak kena potongan absensi.
        if (self::attendanceDailyHasSickExcuseColumn()) {
            $sickExcusedDates = DB::table('attendance_daily as d')
                ->join('employees as e2', 'e2.id', '=', 'd.employee_id')
                ->where('e2.company_id', $companyId)
                ->where('d.employee_id', $employeeId)
                ->whereIn('d.date', $scheduledDates)
                ->where('d.is_sick_doctor_excused', 1)
                ->distinct()
                ->pluck('d.date')
                ->map(static fn ($v) => (string) $v)
                ->filter(static fn ($v) => $v !== '')
                ->values()
                ->all();
            foreach ($sickExcusedDates as $d) {
                $excused[$d] = true;
            }
        }

        return array_keys($excused);
    }

    private static function resolveAttendanceRangeByPeriod(int $month, int $year): array
    {
        $periodEnd = DateTime::createFromFormat('Y-n-j', sprintf('%d-%d-%d', $year, $month, self::PAYROLL_CLOSING_DAY));
        if (!$periodEnd) {
            $periodEnd = new DateTime(sprintf('%04d-%02d-%02d', $year, $month, self::PAYROLL_CLOSING_DAY));
        }
        $periodStart = (clone $periodEnd)->modify('-1 month')->modify('+1 day');

        return [
            'start_date' => $periodStart->format('Y-m-d'),
            'end_date' => $periodEnd->format('Y-m-d'),
            'label' => $periodStart->format('d/m/Y') . ' - ' . $periodEnd->format('d/m/Y'),
        ];
    }

    public static function periodRangeByMonthYear(int $month, int $year): array
    {
        return self::resolveAttendanceRangeByPeriod($month, $year);
    }

    public static function periodRangeByPeriodRow(?object $period): array
    {
        if (!$period) {
            return ['start_date' => null, 'end_date' => null, 'label' => '-'];
        }

        $type = strtolower(trim((string) ($period->period_type ?? 'month_year')));
        if ($type === 'date_range') {
            $start = (string) ($period->start_date ?? '');
            $end = (string) ($period->end_date ?? '');
            if ($start !== '' && $end !== '') {
                if ($start > $end) {
                    [$start, $end] = [$end, $start];
                }
                return [
                    'start_date' => $start,
                    'end_date' => $end,
                    'label' => date('d/m/Y', strtotime($start)) . ' - ' . date('d/m/Y', strtotime($end)),
                ];
            }
        }

        return self::resolveAttendanceRangeByPeriod((int) ($period->month ?? 0), (int) ($period->year ?? 0));
    }

    public static function periods()
    {
        return DB::table('payroll_period')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();
    }

    public static function createPeriod(int $month, int $year, string $periodType = 'month_year', ?string $startDate = null, ?string $endDate = null): void
    {
        DB::table('payroll_period')->insert([
            'month' => $month,
            'year' => $year,
            'period_type' => $periodType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'Draft',
        ]);
    }

    public static function updatePeriod(int $id, int $month, int $year, string $status, string $periodType = 'month_year', ?string $startDate = null, ?string $endDate = null): void
    {
        DB::table('payroll_period')->where('id', $id)->update([
            'month' => $month,
            'year' => $year,
            'period_type' => $periodType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
        ]);
    }

    public static function deletePeriod(int $id): void
    {
        DB::table('payroll_period')->where('id', $id)->delete();
    }

    public static function run(int $companyId, int $periodId): void
    {
        $period = DB::table('payroll_period')->where('id', $periodId)->first();
        if (!$period) {
            return;
        }

        $range = self::periodRangeByPeriodRow($period);
        $startDate = $range['start_date'];
        $endDate = $range['end_date'];
        $company = DB::table('companies')
            ->select(
                'bpjs_health_pct',
                'bpjs_jht_pct',
                'bpjs_jp_pct',
                'work_days_json',
                'work_days_per_week',
                'work_time_start',
                'work_duration_hours',
                'allin_ot_rate_6_7',
                'allin_ot_rate_7_8',
                'allin_ot_rate_8_9',
                'allin_ot_rate_9_10'
            )
            ->where('id', $companyId)
            ->first();
        $scheduledDates = self::scheduledWorkDates($companyId, $startDate, $endDate, $company);
        $scheduledDateMap = array_flip($scheduledDates);
        $workdays = count($scheduledDates);
        $bpjsHealthPct = (float)($company->bpjs_health_pct ?? 1);
        $jhtPct = (float)($company->bpjs_jht_pct ?? 2);
        $jpPct = (float)($company->bpjs_jp_pct ?? 1);

        $employees = DB::table('employees')
            ->where('company_id', $companyId)
            ->where(function ($q) {
                $q->whereNull('active_status')
                    ->orWhereRaw("TRIM(COALESCE(active_status, '')) = ''")
                    ->orWhereRaw(
                        "LOWER(TRIM(COALESCE(active_status, ''))) NOT IN (?, ?, ?)",
                        [
                            strtolower(Employee::ACTIVE_STATUS_RESIGN),
                            strtolower(Employee::ACTIVE_STATUS_PHK),
                            strtolower(Employee::ACTIVE_STATUS_HABIS_KONTRAK),
                        ]
                    );
            })
            ->orderBy('name')
            ->get();

        foreach ($employees as $e) {
            $employeeId = (int) $e->id;

            $setting = DB::table('payroll_setting')->where('employee_id', $employeeId)->first();
            $basicSalary = (float)($setting->basic_salary ?? 0);
            $flatOvertimeRate = (float)($setting->a2_overtime_flat ?? 0);
            $absenceMode = 'auto';
            if (self::payrollSettingHasAbsenceModeColumn()) {
                $modeVal = strtolower(trim((string) ($setting->absence_mode ?? 'auto')));
                if ($modeVal === 'manual') {
                    $absenceMode = 'manual';
                }
            }
            $manualPresentDays = self::payrollSettingHasManualPresentDaysColumn()
                ? max(0.0, (float) ($setting->manual_present_days ?? 0))
                : 0.0;

            $employeeScheduledDates = self::effectiveScheduledWorkDatesForEmployee(
                $scheduledDates,
                (string) ($e->join_date ?? ''),
                (string) ($e->last_working_date ?? '')
            );
            $employeeWorkdays = count($employeeScheduledDates);
            if ($workdays > 0 && $employeeWorkdays <= 0) {
                DB::table('payroll')
                    ->where('period_id', $periodId)
                    ->where('employee_id', $employeeId)
                    ->delete();
                continue;
            }

            $absDays = 0;
            if ($employeeWorkdays > 0) {
                $presentDatesQuery = DB::table('attendance_daily as d')
                    ->join('employees as e2', 'e2.id', '=', 'd.employee_id')
                    ->where('e2.company_id', $companyId)
                    ->where('d.employee_id', $employeeId)
                    ->whereIn('d.date', $employeeScheduledDates)
                    ->where(function ($q) {
                        $q->whereNotNull('d.check_in')
                            ->orWhereNotNull('d.check_out')
                            ->orWhereRaw('COALESCE(d.work_hours, 0) > 0');
                    });
                $presentDates = $presentDatesQuery
                    ->distinct()
                    ->pluck('d.date')
                    ->map(static fn ($v) => (string) $v)
                    ->filter(static fn ($v) => $v !== '')
                    ->values()
                    ->all();

                $excusedDates = self::excusedScheduledDatesForEmployee($companyId, $employeeId, $employeeScheduledDates);
                $specialExcusedDates = [];
                if (self::attendanceDailyHasSpecialLeaveExcuseColumn()) {
                    $specialExcusedDates = DB::table('attendance_daily as d')
                        ->join('employees as e2', 'e2.id', '=', 'd.employee_id')
                        ->where('e2.company_id', $companyId)
                        ->where('d.employee_id', $employeeId)
                        ->whereIn('d.date', $employeeScheduledDates)
                        ->where('d.is_special_leave_excused', 1)
                        ->distinct()
                        ->pluck('d.date')
                        ->map(static fn ($v) => (string) $v)
                        ->filter(static fn ($v) => $v !== '')
                        ->values()
                        ->all();
                }

                $effectiveDates = array_values(array_unique(array_merge($presentDates, $excusedDates, $specialExcusedDates)));
                $effectivePresentDays = min($employeeWorkdays, count($effectiveDates));
                $absDays = max(0, $employeeWorkdays - $effectivePresentDays);
                if ($absenceMode === 'manual') {
                    $manualPresentDaysClamped = min((float) $employeeWorkdays, $manualPresentDays);
                    $absDays = max(0.0, (float) $employeeWorkdays - $manualPresentDaysClamped);
                }
            }

            $overtimeQuery = DB::table('attendance_daily as d')
                ->where('d.employee_id', $employeeId)
                ->whereRaw('d.date BETWEEN ? AND ?', [$startDate, $endDate])
                ->select(
                    'd.date',
                    'd.check_in',
                    'd.check_out',
                    'd.work_hours',
                    'd.no_overtime_permit',
                    'd.overtime_hours',
                    self::attendanceDailyHasManualOvertimeColumns() ? 'd.overtime_hours_manual' : DB::raw('NULL as overtime_hours_manual'),
                    self::attendanceDailyHasManualOvertimeColumns() ? 'd.overtime_hours_is_manual' : DB::raw('0 as overtime_hours_is_manual')
                )
                ->get();

            $overtimeHoursAuto = 0.0;
            $overtimeWeightedHoursAuto = 0.0;
            $overtimeHoursDaily = 0.0;
            $allInOvertimeHours = 0.0;
            $isSecurity = self::isSecurityPosition((string) ($e->position ?? ''));
            foreach ($overtimeQuery as $daily) {
                $noPermit = self::attendanceDailyHasNoOtPermitColumn()
                    ? ((int) ($daily->no_overtime_permit ?? 0) === 1)
                    : false;
                $manualOvertime = (int) ($daily->overtime_hours_is_manual ?? 0) === 1
                    ? max(0.0, (float) ($daily->overtime_hours_manual ?? 0))
                    : null;
                $allInOvertimeHours += self::resolveAllInOvertimeHours($daily, $companyId, $scheduledDateMap, $manualOvertime);
                $securityOvertimeByWorkHours = max(0.0, (float) ($daily->work_hours ?? 0) - 8.0);
                if (!$noPermit) {
                    $overtimeHoursDaily += $isSecurity
                        ? ($manualOvertime ?? $securityOvertimeByWorkHours)
                        : ($manualOvertime ?? max(0.0, (float) ($daily->overtime_hours ?? 0)));
                }
                $calc = OvertimeCalculator::calculateForRecord(
                    $companyId,
                    (string) $daily->date,
                    (string) ($daily->check_in ?? ''),
                    (string) ($daily->check_out ?? ''),
                    $noPermit
                );
                if ($isSecurity) {
                    $securityOvertimeHours = $manualOvertime ?? $securityOvertimeByWorkHours;
                    $overtimeHoursAuto += $noPermit ? 0.0 : $securityOvertimeHours;
                    // Shift security baseline 8 jam: lembur dihitung setelah lewat 8 jam kerja.
                    $overtimeWeightedHoursAuto += $noPermit ? 0.0 : $securityOvertimeHours;
                } else {
                    $overtimeHoursAuto += $manualOvertime ?? (float) ($calc['hours'] ?? 0);
                    $overtimeWeightedHoursAuto += $manualOvertime ?? (float) ($calc['weighted_hours'] ?? 0);
                }
            }

            $employmentStatus = strtoupper(trim((string)($e->employment_status ?? '')));
            $isHarian = $employmentStatus === 'HARIAN';
            $isKomisaris = $employmentStatus === 'KOMISARIS';
            $isAllIn = self::isAllInStatus($employmentStatus);
            $isFreelance = self::isFreelanceStatus($employmentStatus);
            $isProbation = self::isProbationStatus($employmentStatus);
            $isOfficeSupportException = $isHarian && self::isOfficeSupportHarianException((string) ($e->position ?? ''));

            $overtimeMode = 'auto';
            if (self::payrollSettingHasOvertimeModeColumn()) {
                $modeVal = strtolower(trim((string) ($setting->overtime_mode ?? 'auto')));
                if ($modeVal === 'manual') {
                    $overtimeMode = 'manual';
                }
            }
            $overtimeManualHours = 0.0;
            if (self::payrollSettingHasOvertimeManualHoursColumn()) {
                $overtimeManualHours = max(0, (float) ($setting->overtime_manual_hours ?? 0));
            }
            $overtimeHours = $overtimeMode === 'manual' ? $overtimeManualHours : $overtimeHoursAuto;
            // Status HARIAN: basic_salary diisi sebagai gaji per jam.
            // Status lain: rate lembur default = gaji pokok / 173.
            $hourlyRate = $isHarian ? max(0.0, $basicSalary) : 0.0;
            $baseOvertimeRate = $isHarian
                ? max(0.0, $basicSalary)
                : ($basicSalary > 0 ? ($basicSalary / 173) : 0.0);

            $harianNormalPaidHours = 0.0;
            if ($isHarian) {
                $harianStandardHours = $isSecurity ? 8.0 : (float) ($company->work_duration_hours ?? 8);
                $harianNormalPaidHours = self::resolveHarianNormalPaidHours(
                    $overtimeQuery,
                    $harianStandardHours,
                    (string) ($company->work_time_start ?? ''),
                    $isOfficeSupportException,
                    $isSecurity
                );
                if ($absenceMode === 'manual') {
                    // Manual absence mode for HARIAN: treat employee as present for configured days.
                    $manualPresentDaysClamped = min((float) $workdays, max(0.0, $manualPresentDays));
                    $harianNormalPaidHours = round($manualPresentDaysClamped * max(0.0, $harianStandardHours), 2);
                }
            }

            $calculatedBasicSalary = $isHarian
                ? round($hourlyRate * $harianNormalPaidHours, 2)
                : $basicSalary;
            $overtimeAmount = 0.0;
            $overtimeHoursApproved = 0.0;
            if ($isKomisaris || $isFreelance) {
                $overtimeAmount = 0.0;
                $overtimeHoursApproved = 0.0;
            } elseif ($isAllIn) {
                // Surat edaran all-in: hari kerja mulai dihitung lembur pukul 19:00.
                // Hari libur/off-day tetap mengikuti jam masuk dan jam pulang.
                $overtimeHoursApproved = round(max(0.0, $allInOvertimeHours), 2);
                $allInRate = self::resolveAllInOvertimeRate($basicSalary, $company);
                $overtimeAmount = round($allInRate * $overtimeHoursApproved, 2);
            } elseif ($overtimeMode === 'manual') {
                $hasBucketColumns =
                    self::payrollSettingHasOvertimeManualHour1Column()
                    && self::payrollSettingHasOvertimeManualHour2Column()
                    && self::payrollSettingHasOvertimeManualHoliday8Column()
                    && self::payrollSettingHasOvertimeManualHoliday9Column();

                if ($hasBucketColumns) {
                    $manualHour1 = max(0, (float) ($setting->overtime_manual_hour_1 ?? 0));
                    $manualHour2 = max(0, (float) ($setting->overtime_manual_hour_2 ?? 0));
                    $manualHoliday8 = max(0, (float) ($setting->overtime_manual_holiday_8 ?? 0));
                    $manualHoliday9 = max(0, (float) ($setting->overtime_manual_holiday_9 ?? 0));

                    $overtimeHours = $manualHour1 + $manualHour2 + $manualHoliday8 + $manualHoliday9;
                    $weightedManualHours =
                        ($manualHour1 * 1.5)
                        + ($manualHour2 * 2.0)
                        + ($manualHoliday8 * 2.0)
                        + ($manualHoliday9 * 3.0);
                    $overtimeHoursApproved = $overtimeHours;
                    $billableManualHours = $isHarian ? $overtimeHours : $weightedManualHours;
                    $overtimeAmount = round($baseOvertimeRate * $billableManualHours, 2);
                } else {
                    // Backward compatibility with old manual single-hour field.
                    $overtimeHoursApproved = $overtimeHours;
                    $manualRate = $isHarian
                        ? $baseOvertimeRate
                        : ($flatOvertimeRate > 0 ? $flatOvertimeRate : $baseOvertimeRate);
                    $overtimeAmount = round($manualRate * $overtimeHours, 2);
                }
            } else {
                $overtimeHoursApproved = $overtimeHoursAuto;
                $billableAutoHours = $isHarian ? $overtimeHoursAuto : $overtimeWeightedHoursAuto;
                $overtimeAmount = round($baseOvertimeRate * $billableAutoHours, 2);
            }

            $bpjsHealth = round($calculatedBasicSalary * $bpjsHealthPct / 100, 2);
            $jht = round($calculatedBasicSalary * $jhtPct / 100, 2);
            $jp = round($calculatedBasicSalary * $jpPct / 100, 2);
            if ($employmentStatus === 'HARIAN' || $isFreelance || $isProbation) {
                $bpjsHealth = 0.0;
                $jht = 0.0;
                $jp = 0.0;
            }

            $bpjsAllowance = (float)($setting->a13_bpjs_allowance ?? 0);
            $taxAllowance = (float)($setting->a12_tax_allowance ?? 0);
            $bpjsDeductionTotal = $bpjsHealth + $jht + $jp;
            if ($isFreelance) {
                $bpjsAllowance = 0.0;
                $taxAllowance = 0.0;
            }

            $allowances = [
                'a2_overtime' => $overtimeAmount,
                'a3_meal' => (float)($setting->a3_meal ?? 0),
                'a4_transport' => (float)($setting->a4_transport ?? 0),
                'a5_performance' => (float)($setting->a5_performance ?? 0),
                'a6_position' => (float)($setting->a6_position ?? 0),
                'a7_family' => (float)($setting->a7_family ?? 0),
                'a8_communication' => (float)($setting->a8_communication ?? 0),
                'a9_other' => (float)($setting->a9_other ?? 0),
                'a10_thr' => (float)($setting->a10_thr ?? 0),
                'a11_bonus' => (float)($setting->a11_bonus ?? 0),
                'a12_rapel_gaji' => (float)($setting->a12_rapel_gaji ?? 0),
                'a12_tax_allowance' => $taxAllowance,
                'a13_bpjs_allowance' => $bpjsAllowance,
            ];

            $taxDeduction = $isFreelance
                ? 0.0
                : Pph21Service::expectedDeductionForPayrollRun(
                    $e,
                    $period,
                    (float) $calculatedBasicSalary,
                    $allowances,
                    (float) $jht,
                    (float) $jp
                );
            $isAllInOrKomisaris = self::isAllInStatus($employmentStatus) || $isKomisaris;
            if ($isAllInOrKomisaris) {
                $bpjsAllowance = $bpjsDeductionTotal;
                $allowances['a13_bpjs_allowance'] = $bpjsAllowance;
                for ($i = 0; $i < 5; $i++) {
                    $allowances['a12_tax_allowance'] = $taxDeduction;
                    $nextTaxDeduction = Pph21Service::expectedDeductionForPayrollRun(
                        $e,
                        $period,
                        (float) $calculatedBasicSalary,
                        $allowances,
                        (float) $jht,
                        (float) $jp
                    );
                    if (abs($nextTaxDeduction - $taxDeduction) < 0.01) {
                        break;
                    }
                    $taxDeduction = $nextTaxDeduction;
                }
                $taxAllowance = $taxDeduction;
                $allowances['a12_tax_allowance'] = $taxAllowance;
                $allowances['a13_bpjs_allowance'] = $bpjsAllowance;
            }

            // Rumus potongan absen per hari: (A1 + A5 + A6 + A7) / 26.
            $absenceDeduction = 0.0;
            if (!$isHarian && !$isKomisaris && !$isFreelance && $absDays > 0) {
                $absenceDivisor = self::absenceDivisor($workdays, $employeeWorkdays);
                $dailyAbsenceBase = (
                    (float) $calculatedBasicSalary
                    + (float) ($allowances['a5_performance'] ?? 0)
                    + (float) ($allowances['a6_position'] ?? 0)
                    + (float) ($allowances['a7_family'] ?? 0)
                ) / $absenceDivisor;
                $absenceDeduction = round(max(0, $dailyAbsenceBase) * max(0, (float) $absDays), 2);
            }

            $deductions = [
                'b1_loan' => (float)($setting->b1_loan ?? 0),
                'b2_absence' => (float) $absenceDeduction,
                'b3_subsidy' => (float)($setting->b3_subsidy ?? 0),
                'b4_bpjs_health' => $bpjsHealth,
                'b5_jht' => $jht,
                'b6_jp' => $jp,
                'b7_pph21' => $taxDeduction,
                'b8_other' => (float)($setting->b8_other ?? 0),
            ];

            if ($isFreelance) {
                foreach ($allowances as $key => $val) {
                    $allowances[$key] = 0.0;
                }
                foreach ($deductions as $key => $val) {
                    $deductions[$key] = 0.0;
                }
            }

            $totalAllowance = array_sum($allowances);
            $totalDeduction = array_sum($deductions);
            $totalIncome = $calculatedBasicSalary + $totalAllowance;
            $netSalary = $totalIncome - $totalDeduction;
            $roundedNet = ceil($netSalary / 1000) * 1000;

            DB::table('payroll')->updateOrInsert(
                ['period_id' => $periodId, 'employee_id' => $employeeId],
                array_merge([
                    'company_id' => $companyId,
                    'basic_salary' => $calculatedBasicSalary,
                    'a2_overtime_flat' => $flatOvertimeRate,
                    'a2_overtime_hours' => round($overtimeHoursApproved, 2),
                    'total_penerimaan' => $totalIncome,
                    'total_potongan' => $totalDeduction,
                    'gaji_bersih' => $netSalary,
                    'pembulatan' => $roundedNet,
                ], $allowances, $deductions)
            );
        }
    }

    public static function syncLoanDeductionFromSetting(int $periodId, int $employeeId, ?int $companyId = null): void
    {
        if ($periodId <= 0 || $employeeId <= 0) {
            return;
        }

        $period = DB::table('payroll_period')->where('id', $periodId)->first();
        if (!$period) {
            return;
        }

        $status = strtolower(trim((string) ($period->status ?? '')));
        if (in_array($status, ['close', 'closed', 'final', 'finalized'], true)) {
            return;
        }

        $loan = (float) (DB::table('payroll_setting')
            ->where('employee_id', $employeeId)
            ->value('b1_loan') ?? 0);
        if ($loan <= 0) {
            return;
        }

        $query = DB::table('payroll')
            ->where('period_id', $periodId)
            ->where('employee_id', $employeeId);
        if ($companyId !== null && $companyId > 0) {
            $query->where('company_id', $companyId);
        }

        $row = $query->first();
        if (!$row) {
            return;
        }

        $currentLoan = (float) ($row->b1_loan ?? 0);
        if (abs($currentLoan - $loan) < 0.01) {
            return;
        }

        $totalIncome = (float) ($row->total_penerimaan ?? 0);
        $totalDeduction = max(0.0, (float) ($row->total_potongan ?? 0) - $currentLoan + $loan);
        $netSalary = $totalIncome - $totalDeduction;
        $roundedNet = ceil($netSalary / 1000) * 1000;

        DB::table('payroll')
            ->where('id', (int) $row->id)
            ->update([
                'b1_loan' => $loan,
                'total_potongan' => $totalDeduction,
                'gaji_bersih' => $netSalary,
                'pembulatan' => $roundedNet,
            ]);
    }

    public static function syncLoanDeductionsFromSettings(int $periodId, int $companyId): void
    {
        if ($periodId <= 0 || $companyId <= 0) {
            return;
        }

        $period = DB::table('payroll_period')->where('id', $periodId)->first();
        if (!$period) {
            return;
        }

        $status = strtolower(trim((string) ($period->status ?? '')));
        if (in_array($status, ['close', 'closed', 'final', 'finalized'], true)) {
            return;
        }

        $rows = DB::table('payroll as p')
            ->join('payroll_setting as ps', 'ps.employee_id', '=', 'p.employee_id')
            ->where('p.period_id', $periodId)
            ->where('p.company_id', $companyId)
            ->whereRaw('COALESCE(ps.b1_loan, 0) > 0')
            ->select(
                'p.id',
                'p.b1_loan as current_loan',
                'p.total_penerimaan',
                'p.total_potongan',
                'ps.b1_loan as setting_loan'
            )
            ->get();

        foreach ($rows as $row) {
            $loan = (float) ($row->setting_loan ?? 0);
            $currentLoan = (float) ($row->current_loan ?? 0);
            if ($loan <= 0 || abs($currentLoan - $loan) < 0.01) {
                continue;
            }

            $totalIncome = (float) ($row->total_penerimaan ?? 0);
            $totalDeduction = max(0.0, (float) ($row->total_potongan ?? 0) - $currentLoan + $loan);
            $netSalary = $totalIncome - $totalDeduction;
            $roundedNet = ceil($netSalary / 1000) * 1000;

            DB::table('payroll')
                ->where('id', (int) $row->id)
                ->update([
                    'b1_loan' => $loan,
                    'total_potongan' => $totalDeduction,
                    'gaji_bersih' => $netSalary,
                    'pembulatan' => $roundedNet,
                ]);
        }
    }

    public static function itemsByPeriodCompany(int $periodId, int $companyId)
    {
        self::syncLoanDeductionsFromSettings($periodId, $companyId);

        $query = DB::table('payroll as p')
            ->join('employees as e', 'e.id', '=', 'p.employee_id')
            ->where('p.period_id', $periodId)
            ->where('p.company_id', $companyId);

        return $query
            ->orderBy('e.name')
            ->select('p.*', 'e.name', 'e.nik', 'e.position', 'e.grade', 'e.employment_status', 'e.company_id')
            ->get();
    }

    public static function itemByEmployee(int $periodId, int $employeeId)
    {
        self::syncLoanDeductionFromSetting($periodId, $employeeId);

        $query = DB::table('payroll as p')
            ->join('employees as e', 'e.id', '=', 'p.employee_id')
            ->leftJoin('companies as c', 'c.id', '=', 'e.company_id')
            ->where('p.period_id', $periodId)
            ->where('p.employee_id', $employeeId);

        return $query
            ->select('p.*', 'e.name', 'e.nik', 'e.position', 'e.grade', 'e.employment_status', 'c.company_name', 'c.logo_path', 'e.company_id')
            ->first();
    }

    public static function itemByEmployeeCompany(int $periodId, int $employeeId, int $companyId)
    {
        self::syncLoanDeductionFromSetting($periodId, $employeeId, $companyId);

        $query = DB::table('payroll as p')
            ->join('employees as e', 'e.id', '=', 'p.employee_id')
            ->leftJoin('companies as c', 'c.id', '=', 'e.company_id')
            ->where('p.period_id', $periodId)
            ->where('p.employee_id', $employeeId)
            ->where('p.company_id', $companyId);

        return $query
            ->select('p.*', 'e.name', 'e.nik', 'e.position', 'e.grade', 'e.employment_status', 'c.company_name', 'c.logo_path', 'e.company_id')
            ->first();
    }

    public static function absencePreviewForEmployee(int $employeeId, float $basicSalary, array $allowances = []): array
    {
        $employee = DB::table('employees')->where('id', $employeeId)->first();
        if (!$employee) {
            return ['amount' => 0, 'absence_days' => 0, 'workdays' => 0, 'excused_days' => 0, 'period_label' => '-'];
        }
        $employmentStatus = strtoupper(trim((string) ($employee->employment_status ?? '')));
        if ($employmentStatus === 'KOMISARIS') {
            return ['amount' => 0, 'absence_days' => 0, 'workdays' => 0, 'excused_days' => 0, 'period_label' => 'KOMISARIS (Flat)'];
        }
        if (self::isFreelanceStatus($employmentStatus)) {
            return ['amount' => 0, 'absence_days' => 0, 'workdays' => 0, 'excused_days' => 0, 'period_label' => 'FREELANCE (All-In Basic Only)'];
        }
        $companyId = (int) $employee->company_id;
        $period = DB::table('payroll_period')->orderByDesc('year')->orderByDesc('month')->first();
        if (!$period) {
            return ['amount' => 0, 'absence_days' => 0, 'workdays' => 0, 'excused_days' => 0, 'period_label' => '-'];
        }

        $month = (int) $period->month;
        $year = (int) $period->year;
        $range = self::resolveAttendanceRangeByPeriod($month, $year);
        $startDate = $range['start_date'];
        $endDate = $range['end_date'];
        $company = DB::table('companies')
            ->select('work_days_json', 'work_days_per_week')
            ->where('id', $companyId)
            ->first();
        $setting = DB::table('payroll_setting')->where('employee_id', $employeeId)->first();
        $absenceMode = self::payrollSettingHasAbsenceModeColumn()
            ? strtolower(trim((string) ($setting->absence_mode ?? 'auto')))
            : 'auto';
        $manualPresentDays = self::payrollSettingHasManualPresentDaysColumn()
            ? max(0.0, (float) ($setting->manual_present_days ?? 0))
            : 0.0;
        $scheduledDates = self::scheduledWorkDates($companyId, $startDate, $endDate, $company);
        $fullWorkdays = count($scheduledDates);
        $employeeScheduledDates = self::effectiveScheduledWorkDatesForEmployee(
            $scheduledDates,
            (string) ($employee->join_date ?? ''),
            (string) ($employee->last_working_date ?? '')
        );
        $workdays = count($employeeScheduledDates);

        $presentDatesQuery = DB::table('attendance_daily as d')
            ->join('employees as e', 'e.id', '=', 'd.employee_id')
            ->where('e.company_id', $companyId)
            ->where('d.employee_id', $employeeId)
            ->whereIn('d.date', $employeeScheduledDates)
            ->where(function ($q) {
                $q->whereNotNull('d.check_in')
                    ->orWhereNotNull('d.check_out')
                    ->orWhereRaw('COALESCE(d.work_hours, 0) > 0');
            });
        $presentDates = $presentDatesQuery
            ->distinct()
            ->pluck('d.date')
            ->map(static fn ($v) => (string) $v)
            ->filter(static fn ($v) => $v !== '')
            ->values()
            ->all();

        $excusedDates = self::excusedScheduledDatesForEmployee($companyId, (int) $employeeId, $employeeScheduledDates);
        $specialExcusedDates = [];
        if (self::attendanceDailyHasSpecialLeaveExcuseColumn()) {
            $specialExcusedDates = DB::table('attendance_daily as d')
                ->join('employees as e', 'e.id', '=', 'd.employee_id')
                ->where('e.company_id', $companyId)
                ->where('d.employee_id', $employeeId)
                ->whereIn('d.date', $employeeScheduledDates)
                ->where('d.is_special_leave_excused', 1)
                ->distinct()
                ->pluck('d.date')
                ->map(static fn ($v) => (string) $v)
                ->filter(static fn ($v) => $v !== '')
                ->values()
                ->all();
        }

        $effectiveDates = array_values(array_unique(array_merge($presentDates, $excusedDates, $specialExcusedDates)));
        $effectivePresentDays = min($workdays, count($effectiveDates));
        $absenceDays = max(0, $workdays - $effectivePresentDays);
        if ($absenceMode === 'manual') {
            $absenceDays = max(0.0, (float) $workdays - min((float) $workdays, $manualPresentDays));
        }
        $amount = 0.0;
        $absenceDivisor = self::absenceDivisor($fullWorkdays, $workdays);
        if ($workdays > 0 && $absenceDays > 0) {
            $base = $basicSalary
                + parse_currency_id($allowances['a5_performance'] ?? 0)
                + parse_currency_id($allowances['a6_position'] ?? 0)
                + parse_currency_id($allowances['a7_family'] ?? 0);
            $amount = round((max(0.0, $base) / $absenceDivisor) * $absenceDays, 2);
        }

        $periodLabel = $range['label'];
        if ($absenceMode === 'manual') {
            $periodLabel .= ' (Manual Global)';
        }
        return [
            'amount' => $amount,
            'absence_days' => $absenceDays,
            'workdays' => $workdays,
            'full_workdays' => $fullWorkdays,
            'absence_divisor' => $absenceDivisor,
            'is_partial_period' => $workdays > 0 && $workdays < $fullWorkdays,
            'excused_days' => count(array_values(array_unique(array_merge($excusedDates, $specialExcusedDates)))),
            'period_label' => $periodLabel,
        ];
    }
}
