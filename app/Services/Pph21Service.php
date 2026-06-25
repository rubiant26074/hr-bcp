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
            ->whereRaw(
                "UPPER(TRIM(COALESCE(e.employment_status, ''))) NOT IN (?, ?)",
                ['FREELANCE', 'FRELANCE']
            )
            ->orderBy('e.name')
            ->select(
                'p.*',
                'e.name',
                'e.nik',
                'e.nik_ktp',
                'e.npwp',
                'e.position',
                'e.grade',
                'e.employment_status',
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

    public static function expectedDeductionForPayrollRun(
        object $employee,
        object $period,
        float $basicSalary,
        array $allowances,
        float $jht,
        float $jp
    ): float {
        $ptkpStatus = normalize_ptkp_status((string) ($employee->ptkp_status ?? 'TK/0'));
        $brutoMonthly = (float) hitungBrutoPajak($basicSalary, $allowances);
        $month = (int) ($period->month ?? 0);

        if ($month === 12) {
            $before = self::yearToDateTotalsBefore((int) ($employee->id ?? 0), (int) ($period->year ?? 0), $month);
            $annualBruto = $before['bruto'] + $brutoMonthly;
            $annualJht = $before['jht'] + $jht;
            $annualJp = $before['jp'] + $jp;
            $annualPph21 = tax_round(calc_pph21_annual_progressive($annualBruto, $ptkpStatus, $annualJht, $annualJp), 2);

            return max(0.0, tax_round($annualPph21 - $before['pph_before_current'], 2));
        }

        return tax_round(calc_pph21_monthly_ter($brutoMonthly, $ptkpStatus), 2);
    }

    public static function bpmpRows(int $periodId, int $companyId): array
    {
        $dataset = self::periodBreakdown($periodId, $companyId);
        $period = $dataset['period'];
        if (!$period) {
            return [];
        }

        $company = DB::table('companies')->where('id', $companyId)->first();
        $tin = self::digitsOnly((string) ($company->npwp ?? ''));
        $idTku = str_pad($tin, 22, '0');
        $withholdingDate = sprintf('%04d-%02d-01', (int) $period->year, (int) $period->month);

        $rows = [];
        foreach ($dataset['rows'] as $row) {
            $source = $row['source'];
            $taxObjectCode = self::taxObjectCodeForRow($row);
            $rows[] = [
                'TIN' => $tin,
                'TaxPeriodMonth' => (int) $period->month,
                'TaxPeriodYear' => (int) $period->year,
                'CounterpartOpt' => 'Resident',
                'CounterpartTin' => self::counterpartTin($source, $row),
                'CounterpartPassport' => '',
                'StatusTaxExemption' => $row['ptkp_status'],
                'Position' => $row['position'] ?: 'Staff',
                'TaxCertificate' => 'N/A',
                'TaxObjectCode' => $taxObjectCode,
                'Gross' => round((float) $row['bruto_monthly'], 2),
                'Rate' => round((float) $row['ter_rate'] * 100, 4),
                'IDPlaceOfBusinessActivity' => $idTku,
                'WithholdingDate' => $withholdingDate,
                'Pph21' => round((float) $row['pph21_actual'], 2),
            ];
        }

        return $rows;
    }

    public static function bpmpXml(int $periodId, int $companyId): string
    {
        $rows = self::bpmpRows($periodId, $companyId);
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><BPMP/>');
        foreach ($rows as $row) {
            $item = $xml->addChild('WithholdingTax');
            foreach ($row as $key => $value) {
                $item->addChild($key, htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            }
        }

        return $xml->asXML() ?: '';
    }

    public static function syncExpectedToPayroll(int $periodId, int $companyId): int
    {
        $dataset = self::periodBreakdown($periodId, $companyId);
        if (!$dataset['period']) {
            return 0;
        }

        $status = strtolower(trim((string) ($dataset['period']->status ?? '')));
        if (in_array($status, ['close', 'closed', 'final', 'finalized'], true)) {
            return 0;
        }

        $updated = 0;
        foreach ($dataset['rows'] as $row) {
            $source = $row['source'];
            $expected = max(0.0, round((float) $row['pph21_expected'], 2));
            $current = (float) ($source->b7_pph21 ?? 0);
            $currentTaxAllowance = (float) ($source->a12_tax_allowance ?? 0);
            $taxAllowanceEligible = self::taxAllowanceEligible((string) ($source->employment_status ?? ''));
            if (abs($current - $expected) < 0.005 && (!$taxAllowanceEligible || abs($currentTaxAllowance - $expected) < 0.005)) {
                continue;
            }

            $totalIncome = (float) ($source->total_penerimaan ?? 0);
            if ($taxAllowanceEligible) {
                $totalIncome = max(0.0, $totalIncome - $currentTaxAllowance + $expected);
            }
            $totalDeduction = max(0.0, (float) ($source->total_potongan ?? 0) - $current + $expected);
            $netSalary = $totalIncome - $totalDeduction;
            $roundedNet = ceil($netSalary / 1000) * 1000;

            $updates = [
                'b7_pph21' => $expected,
                'total_penerimaan' => $totalIncome,
                'total_potongan' => $totalDeduction,
                'gaji_bersih' => $netSalary,
                'pembulatan' => $roundedNet,
            ];
            if ($taxAllowanceEligible) {
                $updates['a12_tax_allowance'] = $expected;
            }

            DB::table('payroll')
                ->where('id', (int) ($source->id ?? 0))
                ->where('period_id', $periodId)
                ->where('company_id', $companyId)
                ->update($updates);
            $updated++;
        }

        return $updated;
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
        $terPph21 = tax_round(calc_pph21_monthly_ter($brutoMonthly, $ptkpStatus), 2);

        $ytd = self::yearToDateTotals((int) $item->employee_id, $year, $month);
        $annualPph21 = tax_round(calc_pph21_annual_progressive($ytd['bruto'], $ptkpStatus, $ytd['jht'], $ytd['jp']), 2);
        $decemberAdjustment = tax_round($annualPph21 - $ytd['pph_before_current'], 2);
        $pph21Expected = $month === 12 ? $decemberAdjustment : $terPph21;
        $pph21Actual = round((float) ($item->b7_pph21 ?? 0), 2);
        $pph21Variance = round($pph21Actual - $pph21Expected, 2);
        if (abs($pph21Variance) <= 0.01) {
            $pph21Variance = 0.0;
        }

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
            'pph21_variance' => $pph21Variance,
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

    private static function yearToDateTotalsBefore(int $employeeId, int $year, int $month): array
    {
        $rows = DB::table('payroll as p')
            ->join('payroll_period as pp', 'pp.id', '=', 'p.period_id')
            ->where('p.employee_id', $employeeId)
            ->where('pp.year', $year)
            ->where('pp.month', '<', $month)
            ->select('p.total_penerimaan', 'p.b5_jht', 'p.b6_jp', 'p.b7_pph21')
            ->get();

        return [
            'bruto' => (float) $rows->sum('total_penerimaan'),
            'jht' => (float) $rows->sum('b5_jht'),
            'jp' => (float) $rows->sum('b6_jp'),
            'pph_before_current' => (float) $rows->sum('b7_pph21'),
        ];
    }

    private static function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private static function counterpartTin(object $source, array $row): string
    {
        $npwp = self::digitsOnly((string) ($source->npwp ?? ''));
        if ($npwp !== '') {
            return $npwp;
        }

        $nikKtp = self::digitsOnly((string) ($source->nik_ktp ?? ''));
        if ($nikKtp !== '') {
            return $nikKtp;
        }

        return self::digitsOnly((string) ($row['nik'] ?? ''));
    }

    private static function taxObjectCodeForRow(array $row): string
    {
        $position = strtoupper((string) ($row['position'] ?? ''));
        if (str_contains($position, 'PENSIUN')) {
            return '21-100-02';
        }

        return '21-100-01';
    }

    private static function taxAllowanceEligible(string $employmentStatus): bool
    {
        $status = strtoupper(trim($employmentStatus));
        return $status === 'KOMISARIS' || str_contains($status, 'ALL-IN');
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
