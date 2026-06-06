<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayrollSettingService
{
    private static ?bool $hasOvertimeModeColumn = null;
    private static ?bool $hasOvertimeManualHoursColumn = null;
    private static ?bool $hasOvertimeManualHour1Column = null;
    private static ?bool $hasOvertimeManualHour2Column = null;
    private static ?bool $hasOvertimeManualHoliday8Column = null;
    private static ?bool $hasOvertimeManualHoliday9Column = null;
    private static ?bool $hasAbsenceModeColumn = null;
    private static ?bool $hasManualPresentDaysColumn = null;

    private static function hasOvertimeModeColumn(): bool
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

    private static function hasOvertimeManualHoursColumn(): bool
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

    private static function hasOvertimeManualHour1Column(): bool
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

    private static function hasOvertimeManualHour2Column(): bool
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

    private static function hasOvertimeManualHoliday8Column(): bool
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

    private static function hasOvertimeManualHoliday9Column(): bool
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

    private static function hasAbsenceModeColumn(): bool
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

    private static function hasManualPresentDaysColumn(): bool
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

    private static function buildPayload(array $data): array
    {
        $payload = [
            'basic_salary' => (float) $data['basic_salary'],
            'a2_overtime' => (float) $data['a2_overtime'],
            'a2_overtime_flat' => (float) $data['a2_overtime_flat'],
            'a3_meal' => (float) $data['a3_meal'],
            'a4_transport' => (float) $data['a4_transport'],
            'a5_performance' => (float) $data['a5_performance'],
            'a6_position' => (float) $data['a6_position'],
            'a7_family' => (float) $data['a7_family'],
            'a8_communication' => (float) $data['a8_communication'],
            'a9_other' => (float) $data['a9_other'],
            'a10_thr' => (float) $data['a10_thr'],
            'a11_bonus' => (float) $data['a11_bonus'],
            'a12_rapel_gaji' => (float) ($data['a12_rapel_gaji'] ?? 0),
            'a12_tax_allowance' => (float) $data['a12_tax_allowance'],
            'a13_bpjs_allowance' => (float) $data['a13_bpjs_allowance'],
            'b1_loan' => (float) $data['b1_loan'],
            'b2_absence' => (float) $data['b2_absence'],
            'b3_subsidy' => (float) $data['b3_subsidy'],
            'b4_bpjs_health' => (float) $data['b4_bpjs_health'],
            'b5_jht' => (float) $data['b5_jht'],
            'b6_jp' => (float) $data['b6_jp'],
            'b7_pph21' => (float) $data['b7_pph21'],
            'b8_other' => (float) $data['b8_other'],
        ];

        if (self::hasOvertimeModeColumn()) {
            $mode = strtolower(trim((string) ($data['overtime_mode'] ?? 'auto')));
            $payload['overtime_mode'] = $mode === 'manual' ? 'manual' : 'auto';
        }
        if (self::hasOvertimeManualHoursColumn()) {
            $payload['overtime_manual_hours'] = max(0, (float) ($data['overtime_manual_hours'] ?? 0));
        }
        if (self::hasOvertimeManualHour1Column()) {
            $payload['overtime_manual_hour_1'] = max(0, (float) ($data['overtime_manual_hour_1'] ?? 0));
        }
        if (self::hasOvertimeManualHour2Column()) {
            $payload['overtime_manual_hour_2'] = max(0, (float) ($data['overtime_manual_hour_2'] ?? 0));
        }
        if (self::hasOvertimeManualHoliday8Column()) {
            $payload['overtime_manual_holiday_8'] = max(0, (float) ($data['overtime_manual_holiday_8'] ?? 0));
        }
        if (self::hasOvertimeManualHoliday9Column()) {
            $payload['overtime_manual_holiday_9'] = max(0, (float) ($data['overtime_manual_holiday_9'] ?? 0));
        }
        if (self::hasAbsenceModeColumn()) {
            $mode = strtolower(trim((string) ($data['absence_mode'] ?? 'auto')));
            $payload['absence_mode'] = $mode === 'manual' ? 'manual' : 'auto';
        }
        if (self::hasManualPresentDaysColumn()) {
            $payload['manual_present_days'] = max(0, (float) ($data['manual_present_days'] ?? 0));
        }

        return $payload;
    }

    public static function getByEmployee(int $employeeId)
    {
        return DB::table('payroll_setting')
            ->where('employee_id', $employeeId)
            ->first();
    }

    public static function upsert(int $employeeId, array $data): void
    {
        $exists = DB::table('payroll_setting')
            ->where('employee_id', $employeeId)
            ->exists();
        $payload = self::buildPayload($data);

        if ($exists) {
            DB::table('payroll_setting')->where('employee_id', $employeeId)->update($payload);
            return;
        }

        DB::table('payroll_setting')->insert(array_merge(
            ['employee_id' => $employeeId],
            $payload
        ));
    }
}
