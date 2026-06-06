<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Dompdf\Dompdf;

class PensionController extends Controller
{
    private const METHOD_GOVERNMENT = 'government';
    private const METHOD_COMPANY_POLICY = 'company_policy';

    private function retirementAgeForYear(int $year): int
    {
        if ($year < 2019) {
            return 56;
        }
        $age = 57 + (int) floor(($year - 2019) / 3);
        return min($age, 65);
    }

    private function retirementMethodOptions(): array
    {
        return [
            self::METHOD_GOVERNMENT => 'Peraturan Pemerintah',
            self::METHOD_COMPANY_POLICY => 'Kebijakan perusahaan',
        ];
    }

    private function normalizeRetirementMethod(?string $method): string
    {
        $method = trim((string) $method);
        return array_key_exists($method, $this->retirementMethodOptions())
            ? $method
            : self::METHOD_GOVERNMENT;
    }

    private function retirementInfo(?string $dob, string $method = self::METHOD_GOVERNMENT, ?int $customRetireAge = null): array
    {
        if (!$dob) {
            return ['age' => null, 'retire_age' => null, 'retire_year' => null];
        }

        $birth = Carbon::parse($dob)->startOfDay();
        $ageNow = $birth->age;
        $birthYear = (int) $birth->year;
        $method = $this->normalizeRetirementMethod($method);

        if ($method === self::METHOD_COMPANY_POLICY && $customRetireAge !== null) {
            return [
                'age' => $ageNow,
                'retire_age' => $customRetireAge,
                'retire_year' => $birthYear + $customRetireAge,
            ];
        }

        $retireYear = null;
        $retireAge = null;
        for ($year = $birthYear; $year <= $birthYear + 100; $year++) {
            $requiredAge = $this->retirementAgeForYear($year);
            $ageInYear = $year - $birthYear;
            if ($ageInYear >= $requiredAge) {
                $retireYear = $year;
                $retireAge = $requiredAge;
                break;
            }
        }

        return ['age' => $ageNow, 'retire_age' => $retireAge, 'retire_year' => $retireYear];
    }

    private function buildRows(int $companyId, string $retirementMethod, ?int $customRetireAge): array
    {
        $employees = Employee::where('company_id', $companyId)
            ->where('employment_status', 'like', '%TETAP%')
            ->orderBy('name')
            ->get();

        return $employees->map(function ($e) use ($retirementMethod, $customRetireAge) {
            $info = $this->retirementInfo($e->date_of_birth ?? null, $retirementMethod, $customRetireAge);
            return [
                'employee' => $e,
                'age' => $info['age'],
                'retire_age' => $info['retire_age'],
                'retire_year' => $info['retire_year'],
            ];
        })->all();
    }

    private function applyFilters(array $rows, ?int $ageMin, ?int $ageMax, ?int $retireYear): array
    {
        return array_values(array_filter($rows, function (array $row) use ($ageMin, $ageMax, $retireYear) {
            $age = $row['age'];
            $ry = $row['retire_year'];
            if ($ageMin !== null) {
                if ($age === null || $age < $ageMin) {
                    return false;
                }
            }
            if ($ageMax !== null) {
                if ($age === null || $age > $ageMax) {
                    return false;
                }
            }
            if ($retireYear !== null) {
                if ($ry === null || (int) $ry !== (int) $retireYear) {
                    return false;
                }
            }
            return true;
        }));
    }

    public function index(Request $request)
    {
        $user = current_user();
        $companyId = current_company_id();
        $companies = Company::orderBy('id')->get();
        if (($user['role'] ?? '') === 'Super Admin' && $request->query('company_id')) {
            $companyId = (int) $request->query('company_id');
        }

        $ageMin = $request->query('age_min') !== null && $request->query('age_min') !== ''
            ? (int) $request->query('age_min')
            : null;
        $ageMax = $request->query('age_max') !== null && $request->query('age_max') !== ''
            ? (int) $request->query('age_max')
            : null;
        $retireYear = $request->query('retire_year') !== null && $request->query('retire_year') !== ''
            ? (int) $request->query('retire_year')
            : null;
        $retirementMethod = $this->normalizeRetirementMethod($request->query('retirement_method'));
        $customRetireAge = $request->query('custom_retire_age') !== null && $request->query('custom_retire_age') !== ''
            ? (int) $request->query('custom_retire_age')
            : null;
        if ($retirementMethod === self::METHOD_COMPANY_POLICY) {
            if ($customRetireAge === null || $customRetireAge < 1 || $customRetireAge > 100) {
                $customRetireAge = 55;
            }
        } else {
            $customRetireAge = null;
        }

        $rows = $this->buildRows($companyId, $retirementMethod, $customRetireAge);
        $rows = $this->applyFilters($rows, $ageMin, $ageMax, $retireYear);
        $retirementMethodOptions = $this->retirementMethodOptions();

        $calc = null;
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'basis_salary' => ['nullable','numeric','min:0'],
                'severance_factor' => ['nullable','numeric','min:0'],
                'upmk_factor' => ['nullable','numeric','min:0'],
                'uph_percent' => ['nullable','numeric','min:0','max:100'],
                'manual_severance' => ['nullable','numeric','min:0'],
                'manual_upmk' => ['nullable','numeric','min:0'],
                'manual_uph' => ['nullable','numeric','min:0'],
            ]);

            $basis = (float) ($data['basis_salary'] ?? 0);
            $sevFactor = (float) ($data['severance_factor'] ?? 0);
            $upmkFactor = (float) ($data['upmk_factor'] ?? 0);
            $uphPercent = (float) ($data['uph_percent'] ?? 0);

            $autoSeverance = $basis * $sevFactor;
            $autoUpmk = $basis * $upmkFactor;
            $autoUph = ($autoSeverance + $autoUpmk) * ($uphPercent / 100);

            $manualSeverance = (float) ($data['manual_severance'] ?? 0);
            $manualUpmk = (float) ($data['manual_upmk'] ?? 0);
            $manualUph = (float) ($data['manual_uph'] ?? 0);

            $total = $manualSeverance + $manualUpmk + $manualUph;

            $calc = [
                'basis_salary' => $basis,
                'severance_factor' => $sevFactor,
                'upmk_factor' => $upmkFactor,
                'uph_percent' => $uphPercent,
                'auto_severance' => $autoSeverance,
                'auto_upmk' => $autoUpmk,
                'auto_uph' => $autoUph,
                'manual_severance' => $manualSeverance,
                'manual_upmk' => $manualUpmk,
                'manual_uph' => $manualUph,
                'total' => $total,
            ];
        }

        return view('modules.pension.index', compact(
            'user',
            'companyId',
            'companies',
            'rows',
            'calc',
            'ageMin',
            'ageMax',
            'retireYear',
            'retirementMethod',
            'customRetireAge',
            'retirementMethodOptions'
        ));
    }

    public function pdf(Request $request)
    {
        $user = current_user();
        $companyId = current_company_id();
        $companies = Company::orderBy('id')->get();
        if (($user['role'] ?? '') === 'Super Admin' && $request->query('company_id')) {
            $companyId = (int) $request->query('company_id');
        }

        $ageMin = $request->query('age_min') !== null && $request->query('age_min') !== ''
            ? (int) $request->query('age_min')
            : null;
        $ageMax = $request->query('age_max') !== null && $request->query('age_max') !== ''
            ? (int) $request->query('age_max')
            : null;
        $retireYear = $request->query('retire_year') !== null && $request->query('retire_year') !== ''
            ? (int) $request->query('retire_year')
            : null;
        $retirementMethod = $this->normalizeRetirementMethod($request->query('retirement_method'));
        $customRetireAge = $request->query('custom_retire_age') !== null && $request->query('custom_retire_age') !== ''
            ? (int) $request->query('custom_retire_age')
            : null;
        if ($retirementMethod === self::METHOD_COMPANY_POLICY) {
            if ($customRetireAge === null || $customRetireAge < 1 || $customRetireAge > 100) {
                $customRetireAge = 55;
            }
        } else {
            $customRetireAge = null;
        }

        $rows = $this->buildRows($companyId, $retirementMethod, $customRetireAge);
        $rows = $this->applyFilters($rows, $ageMin, $ageMax, $retireYear);

        $company = $companies->firstWhere('id', $companyId);
        $html = view('modules.pension.pdf', compact(
            'rows',
            'company',
            'ageMin',
            'ageMax',
            'retireYear',
            'retirementMethod',
            'customRetireAge'
        ))->render();

        $dompdf = new Dompdf([
            'defaultFont' => 'DejaVu Sans',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'chroot' => base_path(),
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename=\"pensiun.pdf\"');
    }
}
