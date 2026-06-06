<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class OvertimeCalculator
{
    private const BREAK_WINDOWS = [
        ['12:00', '13:00'],
        ['18:00', '19:00'],
        ['00:00', '01:00'], // equivalent of 24:00-01:00
    ];

    private static array $companyCache = [];
    private static array $holidayCache = [];

    public static function calculateForRecord(
        int $companyId,
        string $workDate,
        ?string $checkIn,
        ?string $checkOut,
        bool $noOvertimePermit = false
    ): array {
        if ($noOvertimePermit || empty($checkIn) || empty($checkOut)) {
            return self::zeroResult();
        }

        try {
            $start = new DateTimeImmutable($checkIn);
            $end = new DateTimeImmutable($checkOut);
        } catch (\Throwable $e) {
            return self::zeroResult();
        }

        if ($end <= $start) {
            return self::zeroResult();
        }

        $isHoliday = self::isHolidayOrOffDay($companyId, $workDate);
        if ($isHoliday) {
            return self::calculateHoliday($start, $end);
        }

        return self::calculateRegular($start, $end, $workDate);
    }

    private static function calculateRegular(DateTimeImmutable $start, DateTimeImmutable $end, string $workDate): array
    {
        $window1Start = new DateTimeImmutable($workDate . ' 16:00:00');
        $window1End = new DateTimeImmutable($workDate . ' 17:00:00');
        $window2Start = new DateTimeImmutable($workDate . ' 19:00:00');

        $minutes1 = self::effectiveOverlapMinutes($start, $end, $window1Start, $window1End);
        $minutes2 = $end > $window2Start
            ? self::effectiveOverlapMinutes($start, $end, $window2Start, $end)
            : 0.0;

        $hours1 = $minutes1 / 60;
        $hours2 = $minutes2 / 60;
        $totalHours = $hours1 + $hours2;
        $weighted = ($hours1 * 1.5) + ($hours2 * 2.0);

        return [
            'hours' => round($totalHours, 4),
            'weighted_hours' => round($weighted, 4),
            'is_holiday' => false,
        ];
    }

    private static function calculateHoliday(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $effectiveMinutes = self::effectiveMinutes($start, $end);
        $effectiveHours = $effectiveMinutes / 60;

        $first8 = min($effectiveMinutes, 480.0) / 60;
        $ninth = min(max($effectiveMinutes - 480.0, 0.0), 60.0) / 60;
        $after10 = max($effectiveMinutes - 540.0, 0.0) / 60;

        $weighted = ($first8 * 2.0) + ($ninth * 3.0) + ($after10 * 4.0);

        return [
            'hours' => round($effectiveHours, 4),
            'weighted_hours' => round($weighted, 4),
            'is_holiday' => true,
        ];
    }

    private static function effectiveOverlapMinutes(
        DateTimeImmutable $aStart,
        DateTimeImmutable $aEnd,
        DateTimeImmutable $bStart,
        DateTimeImmutable $bEnd
    ): float {
        $start = $aStart > $bStart ? $aStart : $bStart;
        $end = $aEnd < $bEnd ? $aEnd : $bEnd;
        if ($end <= $start) {
            return 0.0;
        }
        return self::effectiveMinutes($start, $end);
    }

    private static function effectiveMinutes(DateTimeImmutable $start, DateTimeImmutable $end): float
    {
        $total = max(0, ($end->getTimestamp() - $start->getTimestamp()) / 60);
        if ($total <= 0) {
            return 0.0;
        }

        $deduction = 0.0;
        foreach (self::breakWindowsBetween($start, $end) as $window) {
            [$bStart, $bEnd] = $window;
            $ovStart = $start > $bStart ? $start : $bStart;
            $ovEnd = $end < $bEnd ? $end : $bEnd;
            if ($ovEnd > $ovStart) {
                $deduction += ($ovEnd->getTimestamp() - $ovStart->getTimestamp()) / 60;
            }
        }

        return max(0.0, $total - $deduction);
    }

    private static function breakWindowsBetween(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $windows = [];
        $cursor = new DateTimeImmutable($start->format('Y-m-d') . ' 00:00:00');
        $last = new DateTimeImmutable($end->format('Y-m-d') . ' 00:00:00');

        while ($cursor <= $last) {
            $day = $cursor->format('Y-m-d');
            foreach (self::BREAK_WINDOWS as [$from, $to]) {
                $fromAt = new DateTimeImmutable($day . ' ' . $from . ':00');
                $toAt = new DateTimeImmutable($day . ' ' . $to . ':00');
                if ($toAt < $fromAt) {
                    $toAt = $toAt->modify('+1 day');
                }
                if ($toAt <= $fromAt) {
                    continue;
                }
                $windows[] = [$fromAt, $toAt];
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $windows;
    }

    private static function isHolidayOrOffDay(int $companyId, string $workDate): bool
    {
        if (self::isNationalHoliday($companyId, $workDate)) {
            return true;
        }
        return !self::isWorkingDay($companyId, $workDate);
    }

    private static function isNationalHoliday(int $companyId, string $date): bool
    {
        $key = '0:' . $date;
        if (array_key_exists($key, self::$holidayCache)) {
            return self::$holidayCache[$key];
        }

        $exists = DB::table('holidays')
            ->where('company_id', 0)
            ->whereDate('holiday_date', $date)
            ->exists();
        self::$holidayCache[$key] = $exists;
        return $exists;
    }

    private static function isWorkingDay(int $companyId, string $date): bool
    {
        $company = self::companyProfile($companyId);
        $dow = (new DateTimeImmutable($date))->format('D');

        $workDays = [];
        if (!empty($company->work_days_json)) {
            $decoded = json_decode((string) $company->work_days_json, true);
            if (is_array($decoded)) {
                foreach ($decoded as $d) {
                    $val = trim((string) $d);
                    if ($val !== '') {
                        $workDays[] = $val;
                    }
                }
            }
        }

        if (count($workDays) === 0) {
            $perWeek = (int) ($company->work_days_per_week ?? 6);
            if ($perWeek <= 5) {
                $workDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
            } elseif ($perWeek === 6) {
                $workDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            } else {
                $workDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            }
        }

        return in_array($dow, $workDays, true);
    }

    private static function companyProfile(int $companyId): object
    {
        if (!isset(self::$companyCache[$companyId])) {
            self::$companyCache[$companyId] = DB::table('companies')
                ->where('id', $companyId)
                ->select('id', 'work_days_json', 'work_days_per_week')
                ->first() ?: (object) ['id' => $companyId, 'work_days_json' => null, 'work_days_per_week' => 6];
        }
        return self::$companyCache[$companyId];
    }

    private static function zeroResult(): array
    {
        return [
            'hours' => 0.0,
            'weighted_hours' => 0.0,
            'is_holiday' => false,
        ];
    }
}
