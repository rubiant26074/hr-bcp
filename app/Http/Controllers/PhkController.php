<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PhkController extends Controller
{
    private function serviceMonths(?string $joinDate): ?int
    {
        if (!$joinDate) {
            return null;
        }
        try {
            $start = Carbon::parse($joinDate)->startOfDay();
            $now = Carbon::now()->startOfDay();
            if ($start->gt($now)) {
                return 0;
            }
            return $start->diffInMonths($now);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function pesangonMonths(float $years): int
    {
        if ($years < 1) return 1;
        if ($years < 2) return 2;
        if ($years < 3) return 3;
        if ($years < 4) return 4;
        if ($years < 5) return 5;
        if ($years < 6) return 6;
        if ($years < 7) return 7;
        if ($years < 8) return 8;
        return 9;
    }

    private function upmkMonths(float $years): int
    {
        if ($years < 3) return 0;
        if ($years < 6) return 2;
        if ($years < 9) return 3;
        if ($years < 12) return 4;
        if ($years < 15) return 5;
        if ($years < 18) return 6;
        if ($years < 21) return 7;
        if ($years < 24) return 8;
        return 10;
    }

    public function index(Request $request)
    {
        $user = current_user();
        $companyId = current_company_id();
        $companies = Company::orderBy('id')->get();
        if (($user['role'] ?? '') === 'Super Admin' && $request->query('company_id')) {
            $companyId = (int) $request->query('company_id');
        }

        $employees = Employee::where('company_id', $companyId)->orderBy('name')->get();
        $rows = $employees->map(function ($e) {
            $months = $this->serviceMonths($e->join_date ?? null);
            $years = $months !== null ? (int) floor($months / 12) : null;
            $remainMonths = $months !== null ? (int) ($months % 12) : null;
            $serviceText = $months === null ? '-' : ($years . ' th ' . $remainMonths . ' bln');
            return [
                'employee' => $e,
                'service_months' => $months,
                'service_years_float' => $months !== null ? $months / 12 : null,
                'service_text' => $serviceText,
            ];
        });

        $calc = null;
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'basis_salary' => ['nullable','numeric','min:0'],
                'service_years' => ['nullable','numeric','min:0'],
                'pesangon_multiplier' => ['nullable','numeric','min:0'],
                'upmk_multiplier' => ['nullable','numeric','min:0'],
                'uph_percent' => ['nullable','numeric','min:0','max:100'],
                'manual_pesangon' => ['nullable','numeric','min:0'],
                'manual_upmk' => ['nullable','numeric','min:0'],
                'manual_uph' => ['nullable','numeric','min:0'],
            ]);

            $basis = (float) ($data['basis_salary'] ?? 0);
            $serviceYears = (float) ($data['service_years'] ?? 0);
            $pesangonMult = (float) ($data['pesangon_multiplier'] ?? 1);
            $upmkMult = (float) ($data['upmk_multiplier'] ?? 1);
            $uphPercent = (float) ($data['uph_percent'] ?? 0);

            $basePesangonMonths = $this->pesangonMonths($serviceYears);
            $baseUpmkMonths = $this->upmkMonths($serviceYears);

            $autoPesangon = $basis * $basePesangonMonths * $pesangonMult;
            $autoUpmk = $basis * $baseUpmkMonths * $upmkMult;
            $autoUph = ($autoPesangon + $autoUpmk) * ($uphPercent / 100);
            $autoTotal = $autoPesangon + $autoUpmk + $autoUph;

            $manualPesangon = (float) ($data['manual_pesangon'] ?? 0);
            $manualUpmk = (float) ($data['manual_upmk'] ?? 0);
            $manualUph = (float) ($data['manual_uph'] ?? 0);
            $manualTotal = $manualPesangon + $manualUpmk + $manualUph;

            $calc = [
                'basis_salary' => $basis,
                'service_years' => $serviceYears,
                'pesangon_multiplier' => $pesangonMult,
                'upmk_multiplier' => $upmkMult,
                'uph_percent' => $uphPercent,
                'base_pesangon_months' => $basePesangonMonths,
                'base_upmk_months' => $baseUpmkMonths,
                'auto_pesangon' => $autoPesangon,
                'auto_upmk' => $autoUpmk,
                'auto_uph' => $autoUph,
                'auto_total' => $autoTotal,
                'manual_pesangon' => $manualPesangon,
                'manual_upmk' => $manualUpmk,
                'manual_uph' => $manualUph,
                'manual_total' => $manualTotal,
            ];
        }

        return view('modules.phk.index', compact(
            'user',
            'companyId',
            'companies',
            'rows',
            'calc'
        ));
    }
}
