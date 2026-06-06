<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Pph21Service
{
    public static function periodBreakdown(int $periodId, int $companyId): array
    {
        $period = DB::table('payroll_period')->where('id', $periodId)->first();
        if (!$period) {
            return [
                'period' => null,
                'rows' => collect(),
                'summary' => self::emptySummary(),
            ];
        }

        $items = DB::table('payroll as p')
            ->join('employees as e', 'e.id', '=', 'p.employee_id')
            ->where('p.period_id', $periodId)
            ->where('p.company_id', $companyId)
            ->orderBy('e.name')
            ->select(
                'p.*',
                'e.name',
                'e.nik',
                'e.position',
                'e.grade',
                'e.ptkp_status',
                'e.company_id'
            )
            ->get();

        $rows = collect();
        $summary = self::emptySummary();
        foreach ($items as $item) {
            $row = self::buildRow($item, (int) $period->year, (int) $period->month);
            $rows->push($row);

            $summary['employees']++;
            $summary['total_bruto'] += $row['bruto_monthly'];
            $summary['total_pph21_actual'] += $row['pph21_actual'];
            $summary['total_pph21_expected'] += $row['pph21_expected'];
            $summary['total_variance'] += $row['pph21_variance'];
            $summary['total_ytd_bruto'] += $row['bruto_ytd'];
        }

        return [
            'period' => $period,
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    public static function employeeBreakdown(int $periodId, int $employeeId, int $companyId): ?array
    {
        $dataset = self::periodBreakdown($periodId, $companyId);
        foreach ($dataset['rows'] as $row) {
            if ((int) $row['employee_id'] === $employeeId) {
                return $row;
            }
        }

        return null;
    }

    private static function emptySummary(): array
    {
        return [
            'employees' => 0,
            'total_bruto' => 0.0,
            'total_pph21_actual' => 0.0,
            'total_pph21_expected' => 0.0,
            'total_variance' => 0.0,
            'total_ytd_bruto' => 0.0,
        ];
    }

    private static function buildRow(object $item, int $year, int $month): array
    {
        $brutoMonthly = (float) hitungBrutoPajak((float) ($item->basic_salary ?? 0), self::allowancesFromItem($item));
        $ptkpStatus = normalize_ptkp_status((string) ($item->ptkp_status ?? 'TK/0'));
        $terCategory = ptkp_ter_category($ptkpStatus);
        $terRate = ter_rate($terCategory, $brutoMonthly);
        $terPph21 = round(calc_pph21_monthly_ter($brutoMonthly, $ptkpStatus), 2);

        $ytd = self::yearToDateTotals((int) $item->employee_id, $year, $month);
        $annualPph21 = round(calc_pph21_annual_progressive($ytd['bruto'], $ptkpStatus, $ytd['jht'], $ytd['jp']), 2);
        $decemberAdjustment = round($annualPph21 - $ytd['pph_before_current'], 2);
        $pph21Expected = $month === 12 ? $decemberAdjustment : $terPph21;
        $pph21Actual = (float) ($item->b7_pph21 ?? 0);

        return [
            'employee_id' => (int) $item->employee_id,
            'nik' => (string) ($item->nik ?? ''),
            'name' => (string) ($item->name ?? ''),
            'position' => (string) ($item->position ?? ''),
            'grade' => (string) ($item->grade ?? ''),
            'ptkp_status' => $ptkpStatus,
            'ter_category' => $terCategory,
            'ter_rate' => $terRate,
            'bruto_monthly' => $brutoMonthly,
            'pph21_ter' => $terPph21,
            'pph21_actual' => $pph21Actual,
            'pph21_expected' => $pph21Expected,
            'pph21_variance' => round($pph21Actual - $pph21Expected, 2),
            'bruto_ytd' => $ytd['bruto'],
            'jht_ytd' => $ytd['jht'],
            'jp_ytd' => $ytd['jp'],
            'pph21_ytd_before' => $ytd['pph_before_current'],
            'annual_pph21' => $annualPph21,
            'december_adjustment' => $decemberAdjustment,
            'job_expense' => min($ytd['bruto'] * 0.05, 6000000),
            'ptkp_amount' => ptkp_amount($ptkpStatus),
            'pkp' => self::pkpValue($ytd['bruto'], $ptkpStatus, $ytd['jht'], $ytd['jp']),
            'source' => $item,
        ];
    }

    private static function allowancesFromItem(object $item): array
    {
        return [
            (float) ($item->a2_overtime ?? 0),
            (float) ($item->a3_meal ?? 0),
            (float) ($item->a4_transport ?? 0),
            (float) ($item->a5_performance ?? 0),
            (float) ($item->a6_position ?? 0),
            (float) ($item->a7_family ?? 0),
            (float) ($item->a8_communication ?? 0),
            (float) ($item->a9_other ?? 0),
            (float) ($item->a10_thr ?? 0),
            (float) ($item->a11_bonus ?? 0),
            (float) ($item->a12_tax_allowance ?? 0),
            (float) ($item->a13_bpjs_allowance ?? 0),
        ];
    }

    private static function yearToDateTotals(int $employeeId, int $year, int $month): array
    {
        $rows = DB::table('payroll as p')
            ->join('payroll_period as pp', 'pp.id', '=', 'p.period_id')
            ->where('p.employee_id', $employeeId)
            ->where('pp.year', $year)
            ->where('pp.month', '<=', $month)
            ->orderBy('pp.month')
            ->select('pp.month', 'p.total_penerimaan', 'p.b5_jht', 'p.b6_jp', 'p.b7_pph21')
            ->get();

        $bruto = (float) $rows->sum('total_penerimaan');
        $jht = (float) $rows->sum('b5_jht');
        $jp = (float) $rows->sum('b6_jp');
        $pphBeforeCurrent = (float) $rows
            ->filter(static fn ($row) => (int) $row->month < $month)
            ->sum('b7_pph21');

        return [
            'bruto' => $bruto,
            'jht' => $jht,
            'jp' => $jp,
            'pph_before_current' => $pphBeforeCurrent,
        ];
    }

    private static function pkpValue(float $annualBruto, string $ptkpStatus, float $annualJht, float $annualJp): float
    {
        $jobExpense = min($annualBruto * 0.05, 6000000);
        $neto = $annualBruto - $jobExpense - $annualJht - $annualJp;
        $pkp = $neto - ptkp_amount($ptkpStatus);
        if ($pkp <= 0) {
            return 0.0;
        }

        return floor($pkp / 1000) * 1000;
    }
}
