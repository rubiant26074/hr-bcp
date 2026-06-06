<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequestStep;
use App\Models\ApprovalSetting;
use App\Models\ApprovalStep;
use App\Models\Company;
use App\Models\Notification;
use App\Models\PayrollReportRequest;
use App\Models\PayrollPph21Request;
use App\Models\User;
use App\Models\Employee;
use App\Services\PayrollService;
use App\Services\Pph21Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Dompdf\Dompdf;

class PayrollController extends Controller
{
    private function overtimeBreakdownByPeriodEmployee(int $periodId, int $employeeId, float $fallbackTotalHours = 0.0): array
    {
        if ($periodId <= 0 || $employeeId <= 0) {
            return ['ta_il_hours' => 0.0, 'lembur_hours' => 0.0, 'has_ta_il' => false];
        }

        $period = DB::table('payroll_period')->where('id', $periodId)->first();
        if (!$period) {
            return ['ta_il_hours' => 0.0, 'lembur_hours' => 0.0, 'has_ta_il' => false];
        }

        $range = PayrollService::periodRangeByPeriodRow($period);
        $startDate = (string) ($range['start_date'] ?? '');
        $endDate = (string) ($range['end_date'] ?? '');
        if ($startDate === '' || $endDate === '') {
            return ['ta_il_hours' => 0.0, 'lembur_hours' => 0.0, 'has_ta_il' => false];
        }

        $taIlHours = (float) DB::table('attendance_daily')
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereRaw('COALESCE(no_overtime_permit, 0) = 1')
            ->sum('overtime_hours');

        $lemburHours = (float) DB::table('attendance_daily')
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereRaw('COALESCE(no_overtime_permit, 0) = 0')
            ->sum('overtime_hours');

        $taIlHours = round(max(0.0, $taIlHours), 2);
        $lemburHours = round(max(0.0, $lemburHours), 2);
        $fallbackTotalHours = round(max(0.0, $fallbackTotalHours), 2);

        // Fallback: beberapa data historis TA-IL lama sempat membuat overtime_hours daily menjadi 0,
        // sementara total jam lembur payroll (a2_overtime_hours) tetap benar.
        if ($taIlHours <= 0 && $lemburHours <= 0 && $fallbackTotalHours > 0) {
            $lemburHours = $fallbackTotalHours;
        }

        return [
            'ta_il_hours' => $taIlHours,
            'lembur_hours' => $lemburHours,
            'has_ta_il' => $taIlHours > 0,
        ];
    }

    private function normalizePeriodStatus(string $status): string
    {
        $v = strtolower(trim($status));
        if ($v === 'closed') {
            return 'Close';
        }
        if ($v === 'running') {
            return 'Running';
        }
        return 'Draft';
    }

    private function resolveSlipItem(array $user, int $periodId, int $employeeId)
    {
        if (current_user_has_global_scope($user)) {
            return PayrollService::itemByEmployee($periodId, $employeeId);
        }

        return PayrollService::itemByEmployeeCompany($periodId, $employeeId, current_company_id());
    }

    private function pushNotification(int $companyId, int $userId, string $title, string $message, string $link = ''): void
    {
        if ($userId <= 0 || !Schema::hasTable('notifications')) {
            return;
        }
        Notification::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'link' => $link !== '' ? $link : null,
            'is_read' => 0,
        ]);
    }

    private function approvalStepsFor(string $moduleKey, int $companyId, int $requesterUserId): array
    {
        if (!Schema::hasTable('approval_settings')) {
            return [];
        }

        $steps = [];
        if (Schema::hasTable('approval_steps')) {
            $steps = ApprovalStep::where('company_id', $companyId)
                ->where('module_key', $moduleKey)
                ->where('requester_user_id', $requesterUserId)
                ->orderBy('step_no')
                ->pluck('approver_user_id')
                ->filter()
                ->map(static fn ($v) => (int) $v)
                ->values()
                ->all();
        }

        if (empty($steps)) {
            $setting = ApprovalSetting::where('company_id', $companyId)
                ->where('module_key', $moduleKey)
                ->where('requester_user_id', $requesterUserId)
                ->first();
            if ($setting) {
                if (!empty($setting->approver1_user_id)) {
                    $steps[] = (int) $setting->approver1_user_id;
                }
                if (!empty($setting->approver2_user_id)) {
                    $steps[] = (int) $setting->approver2_user_id;
                }
            }
        }

        return $steps;
    }

    private function buildApprovalRequestSteps(string $moduleKey, int $requestId, int $companyId, int $requesterUserId): array
    {
        if (!Schema::hasTable('approval_request_steps')) {
            return [];
        }

        $steps = $this->approvalStepsFor($moduleKey, $companyId, $requesterUserId);
        if (empty($steps)) {
            return [];
        }

        ApprovalRequestStep::where('module_key', $moduleKey)
            ->where('request_id', $requestId)
            ->delete();

        foreach (array_values($steps) as $i => $approverId) {
            ApprovalRequestStep::create([
                'module_key' => $moduleKey,
                'request_id' => $requestId,
                'step_no' => $i + 1,
                'approver_user_id' => (int) $approverId,
                'status' => 'Pending',
            ]);
        }

        return $steps;
    }

    private function getApprovalRequestSteps(string $moduleKey, int $requestId): array
    {
        if (!Schema::hasTable('approval_request_steps')) {
            return [];
        }
        return ApprovalRequestStep::where('module_key', $moduleKey)
            ->where('request_id', $requestId)
            ->orderBy('step_no')
            ->get()
            ->all();
    }

    private function ensureApprovalRequestSteps(string $moduleKey, int $requestId, int $companyId, int $requesterUserId): array
    {
        $steps = $this->getApprovalRequestSteps($moduleKey, $requestId);
        if (empty($steps)) {
            $this->buildApprovalRequestSteps($moduleKey, $requestId, $companyId, $requesterUserId);
            $steps = $this->getApprovalRequestSteps($moduleKey, $requestId);
        }
        return $steps;
    }

    private function pendingApprovalStep(array $steps): ?ApprovalRequestStep
    {
        foreach ($steps as $s) {
            if (($s->status ?? '') === 'Pending') {
                return $s;
            }
        }
        return null;
    }

    private function canApproveStep(?ApprovalRequestStep $step, array $user): bool
    {
        if (!$step) {
            return false;
        }
        $approverId = (int) ($step->approver_user_id ?? 0);
        if ($approverId <= 0) {
            return false;
        }
        return (int) ($user['id'] ?? 0) === $approverId;
    }

    private function resolveBankType(?Company $company): ?string
    {
        if (!$company) {
            return null;
        }
        $bankName = strtoupper(trim((string) ($company->bank_name ?? '')));
        if ($bankName !== '') {
            if (strpos($bankName, 'BNI') !== false) {
                return 'BNI';
            }
            if (strpos($bankName, 'BSI') !== false || strpos($bankName, 'SYARIAH') !== false) {
                return 'BSI';
            }
        }
        $code = strtoupper(trim((string) ($company->company_code ?? '')));
        if (in_array($code, ['BK', 'BN'], true)) {
            return 'BNI';
        }
        return 'BSI';
    }

    private function buildBankTransferRows(int $companyId, int $periodId, ?string $bankType): array
    {
        $items = $periodId ? PayrollService::itemsByPeriodCompany($periodId, $companyId) : [];
        $employeeIds = [];
        foreach ($items as $row) {
            $employeeIds[] = (int) ($row->employee_id ?? 0);
        }
        $employeeIds = array_values(array_filter(array_unique($employeeIds)));
        $employeeMap = $employeeIds
            ? Employee::whereIn('id', $employeeIds)->get()->keyBy('id')
            : collect();

        $rows = [];
        $invalids = [];
        $totalAmount = 0;
        foreach ($items as $row) {
            $emp = $employeeMap[(int) ($row->employee_id ?? 0)] ?? null;
            $amount = (float) ($row->gaji_bersih ?? 0);
            $bankName = trim((string) ($emp->bank_name ?? ''));
            $bankAccount = trim((string) ($emp->bank_account_no ?? ''));
            $name = trim((string) ($row->name ?? ($emp->name ?? '')));
            $bankCode = '';
            $isBsi = false;
            if ($bankName !== '') {
                if (stripos($bankName, 'BSI') !== false) {
                    $isBsi = true;
                } elseif (stripos($bankName, 'SYARIAH INDONESIA') !== false) {
                    $isBsi = true;
                } elseif (stripos($bankName, 'BANK SYARIAH') !== false) {
                    $isBsi = true;
                }
            }
            if ($isBsi) {
                $bankCode = '451';
            }

            $issues = [];
            if ($amount <= 0) {
                $issues[] = 'Gaji bersih 0';
            }
            if ($bankAccount === '') {
                $issues[] = 'Rekening kosong';
            }
            if ($bankName === '') {
                $issues[] = 'Nama bank kosong';
            }
            if ($bankType === 'BNI' && $bankName !== '' && stripos($bankName, 'BNI') === false) {
                $issues[] = 'Bank bukan BNI';
            }
            if ($bankType === 'BSI' && $bankName !== '' && !$isBsi) {
                $issues[] = 'Bank bukan BSI';
            }

            if (!empty($issues)) {
                $invalids[] = [
                    'name' => $name,
                    'nik' => $row->nik ?? '',
                    'bank_name' => $bankName,
                    'bank_account_no' => $bankAccount,
                    'amount' => $amount,
                    'issues' => implode(', ', $issues),
                ];
                continue;
            }

            $totalAmount += $amount;
            $rows[] = [
                'employee_id' => (int) ($row->employee_id ?? 0),
                'nik' => $row->nik ?? '',
                'name' => $name,
                'bank_name' => $bankName,
                'bank_account_no' => $bankAccount,
                'bank_code' => $bankCode,
                'amount' => $amount,
            ];
        }

        return [$rows, $invalids, $totalAmount];
    }

    public function period(Request $request)
    {
        $messages = [];
        $edit = null;
        $monthValue = (int) date('n');
        $yearValue = (int) date('Y');
        $statusValue = 'Draft';
        $periodTypeValue = 'month_year';
        $startDateValue = '';
        $endDateValue = '';
        $showNoAttendanceModal = false;
        $noAttendanceMessage = '';

        if ($request->has('edit')) {
            $editId = (int) $request->query('edit');
            foreach (PayrollService::periods() as $p) {
                if ((int)$p->id === $editId) {
                    $edit = $p;
                    break;
                }
            }
            if ($edit) {
                $monthValue = (int) $edit->month;
                $yearValue = (int) $edit->year;
                $statusValue = $this->normalizePeriodStatus((string) ($edit->status ?? 'Draft'));
                $periodTypeValue = (string) ($edit->period_type ?? 'month_year');
                $startDateValue = (string) ($edit->start_date ?? '');
                $endDateValue = (string) ($edit->end_date ?? '');
            }
        }

        if ($request->isMethod('post')) {
            if ($request->input('action') === 'delete') {
                PayrollService::deletePeriod((int) $request->input('id'));
                return redirect()->route('payroll.period');
            }

            $monthValue = (int) $request->input('month', 0);
            $yearValue = (int) $request->input('year', 0);
            $statusValue = 'Draft';
            $periodTypeValue = (string) $request->input('period_type', 'month_year');
            $startDateValue = trim((string) $request->input('start_date', ''));
            $endDateValue = trim((string) $request->input('end_date', ''));

            if ($monthValue < 1 || $monthValue > 12) {
                $messages[] = 'Bulan harus antara 1 sampai 12.';
            }
            if ($yearValue < 2000 || $yearValue > 2100) {
                $messages[] = 'Tahun harus antara 2000 sampai 2100.';
            }
            if (!in_array($periodTypeValue, ['month_year', 'date_range'], true)) {
                $periodTypeValue = 'month_year';
            }
            if ($periodTypeValue === 'date_range') {
                if ($startDateValue === '' || $endDateValue === '') {
                    $messages[] = 'Tanggal mulai dan tanggal akhir wajib diisi untuk mode rentang tanggal.';
                } elseif ($startDateValue > $endDateValue) {
                    $messages[] = 'Tanggal mulai tidak boleh lebih besar dari tanggal akhir.';
                }
            } else {
                $startDateValue = null;
                $endDateValue = null;
            }

            if (empty($messages)) {
                try {
                    if (empty($request->input('id'))) {
                        $companyId = current_company_id();
                        $range = $periodTypeValue === 'date_range'
                            ? [
                                'start_date' => $startDateValue,
                                'end_date' => $endDateValue,
                                'label' => date('d/m/Y', strtotime((string) $startDateValue)) . ' - ' . date('d/m/Y', strtotime((string) $endDateValue)),
                            ]
                            : PayrollService::periodRangeByMonthYear($monthValue, $yearValue);
                        $attendanceCount = (int) DB::table('attendance_daily as d')
                            ->join('employees as e', 'e.id', '=', 'd.employee_id')
                            ->where('e.company_id', $companyId)
                            ->whereRaw('d.date BETWEEN ? AND ?', [$range['start_date'], $range['end_date']])
                            ->count();
                        if ($attendanceCount === 0) {
                            $showNoAttendanceModal = true;
                            $noAttendanceMessage = 'Belum ada data absensi untuk periode cut-off ' . ($range['label'] ?? '-') . '. Payroll period tidak bisa dibuat.';
                        } else {
                            PayrollService::createPeriod($monthValue, $yearValue, $periodTypeValue, $startDateValue, $endDateValue);
                            return redirect()->route('payroll.period', ['ok' => 1]);
                        }
                    } else {
                        $existingPeriod = DB::table('payroll_period')->where('id', (int) $request->input('id'))->first();
                        $statusValue = $this->normalizePeriodStatus((string) ($existingPeriod->status ?? 'Draft'));
                        PayrollService::updatePeriod((int) $request->input('id'), $monthValue, $yearValue, $statusValue, $periodTypeValue, $startDateValue, $endDateValue);
                        return redirect()->route('payroll.period', ['updated' => 1]);
                    }
                } catch (\Throwable $e) {
                    $error = strtolower($e->getMessage());
                    if (strpos($error, 'duplicate') !== false || strpos($error, 'uniq_period') !== false) {
                        $messages[] = 'Periode payroll untuk bulan dan tahun tersebut sudah ada.';
                    } else {
                        $messages[] = 'Gagal menyimpan periode payroll.';
                    }
                }
            }
        }

        $periods = PayrollService::periods()->map(function ($p) {
            $range = PayrollService::periodRangeByPeriodRow($p);
            $p->period_start_date = $range['start_date'] ?? null;
            $p->period_end_date = $range['end_date'] ?? null;
            $p->period_label = $range['label'] ?? '-';
            $p->status = $this->normalizePeriodStatus((string) ($p->status ?? 'Draft'));
            $p->period_type = (string) ($p->period_type ?? 'month_year');
            return $p;
        });
        return view('modules.payroll.period', compact('messages', 'edit', 'monthValue', 'yearValue', 'statusValue', 'periodTypeValue', 'startDateValue', 'endDateValue', 'periods', 'showNoAttendanceModal', 'noAttendanceMessage'));
    }

    public function run(Request $request)
    {
        $user = current_user();
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            return redirect()->route('payroll.run');
        }
        $companyId = current_company_id();
        $companies = Company::orderBy('id')->get();
        $periods = PayrollService::periods();

        $messages = [];
        if ($request->isMethod('post')) {
            $periodId = (int) $request->input('period_id');
            $period = DB::table('payroll_period')->where('id', $periodId)->first();
            if (!$period) {
                $messages[] = 'Periode payroll tidak ditemukan.';
                return view('modules.payroll.run', compact('user', 'companyId', 'companies', 'periods', 'messages'));
            }
            $periodStatus = $this->normalizePeriodStatus((string) ($period->status ?? 'Draft'));
            if ($periodStatus === 'Close') {
                $messages[] = 'Periode payroll sudah Close dan tidak bisa dijalankan ulang.';
                return view('modules.payroll.run', compact('user', 'companyId', 'companies', 'periods', 'messages'));
            }

            PayrollService::run($companyId, $periodId);
            DB::table('payroll_period')->where('id', $periodId)->update(['status' => 'Running']);
            $messages[] = 'Payroll berhasil dijalankan.';
            if ($periodId > 0 && Schema::hasTable('notifications')) {
                $period = DB::table('payroll_period')->where('id', $periodId)->first();
                $label = $period ? sprintf('%02d/%04d', (int) $period->month, (int) $period->year) : 'Periode';
                $targets = DB::table('users')
                    ->whereIn('role', ['Finance', 'CFA'])
                    ->where(function ($q) use ($companyId) {
                        $q->where('company_id', $companyId)->orWhereNull('company_id');
                    })
                    ->pluck('id')
                    ->all();
                foreach ($targets as $uid) {
                    $this->pushNotification(
                        $companyId,
                        (int) $uid,
                        'Payroll Selesai',
                        'Payroll periode ' . $label . ' sudah dijalankan. Silakan review.',
                        route('payroll.review', ['period_id' => $periodId])
                    );
                }
            }
        }

        return view('modules.payroll.run', compact('user', 'companyId', 'companies', 'periods', 'messages'));
    }

    public function review(Request $request)
    {
        $user = current_user();
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            $params = [];
            if ($request->query('period_id')) {
                $params['period_id'] = $request->query('period_id');
            }
            return redirect()->route('payroll.review', $params);
        }
        $companyId = current_company_id();
        $companies = Company::orderBy('id')->get();
        $periods = PayrollService::periods();
        $periodId = $request->query('period_id', $periods[0]->id ?? 0);
        $items = $periodId ? PayrollService::itemsByPeriodCompany((int)$periodId, $companyId) : [];

        return view('modules.payroll.review', compact('user', 'companyId', 'companies', 'periods', 'periodId', 'items'));
    }

    public function report(Request $request)
    {
        $user = current_user();
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            $params = [];
            if ($request->query('period_id')) {
                $params['period_id'] = $request->query('period_id');
            }
            return redirect()->route('payroll.report', $params);
        }
        $companyId = current_company_id();
        $companies = Company::orderBy('id')->get();
        $periods = PayrollService::periods();
        $defaultPeriodId = (int) ($periods[0]->id ?? 0);
        $periodId = (int) $request->input('period_id', $request->query('period_id', $defaultPeriodId));

        $moduleKey = 'payroll_report';
        if ($request->isMethod('post')) {
            $action = (string) $request->input('action', 'submit');
            if ($action === 'download_bni') {
                $approvalRequest = null;
                if ($periodId > 0 && Schema::hasTable('payroll_report_requests')) {
                    $approvalRequest = PayrollReportRequest::where('company_id', $companyId)
                        ->where('period_id', $periodId)
                        ->first();
                }
                if (!$approvalRequest || ($approvalRequest->status ?? '') !== 'Approved') {
                    return back()->withErrors(['bank' => 'Payroll report belum disetujui. Approval wajib sebelum download bank.'])->withInput();
                }
                $company = Company::find($companyId);
                $bankType = $this->resolveBankType($company);
                if ($bankType !== 'BNI') {
                    return back()->withErrors(['bank' => 'Company ini tidak menggunakan BNI.'])->withInput();
                }
                if (!$company || trim((string) ($company->bank_name ?? '')) === '') {
                    return back()->withErrors(['bank' => 'Nama bank perusahaan belum diisi di Master Company.'])->withInput();
                }
                [$rows, $invalids, $totalAmount] = $this->buildBankTransferRows($companyId, $periodId, $bankType);
                if (empty($rows)) {
                    return back()->withErrors(['bank' => 'Tidak ada data valid untuk diexport.'])->withInput();
                }

                $restricted = [',','`','~','!','@','#','$','%','^','&','*','_','{','}','<','>','[',']','=','\\',';'];
                $sanitize = static function (string $val) use ($restricted): string {
                    $clean = str_replace($restricted, ' ', $val);
                    $clean = preg_replace('/\\s+/', ' ', $clean);
                    return trim((string) $clean);
                };

                $remarkDefault = 'Payroll Maret 2026';
                $debitAccount = trim((string) ($company->bank_debit_account_no ?? ''));
                if ($debitAccount === '') {
                    return back()->withErrors(['bank' => 'No. rekening debet perusahaan belum diisi di Master Company.'])->withInput();
                }

                $handle = fopen('php://temp', 'r+');
                $totalRecord = count($rows);
                $totalAmountFormatted = number_format((float) $totalAmount, 0, '.', '');
                $headerRow = [
                    'P',
                    date('d/m/Y'),
                    $debitAccount,
                    (string) $totalRecord,
                    $totalAmountFormatted,
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                ];
                fputcsv($handle, $headerRow);

                foreach ($rows as $r) {
                    $amount = number_format((float) $r['amount'], 0, '.', '');
                    $remark = $sanitize($remarkDefault);
                    $name = $sanitize($r['name']);
                    $ref = substr(preg_replace('/[^0-9A-Za-z]/', '', (string) ($r['nik'] ?? '')), 0, 16);
                    fputcsv($handle, [
                        $sanitize((string) $r['bank_account_no']),
                        $name,
                        $amount,
                        $remark,
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        $ref,
                        '',
                    ]);
                }

                rewind($handle);
                $csv = stream_get_contents($handle);
                fclose($handle);

                $label = 'payroll_bni_' . date('Ymd_His') . '.csv';
                return response($csv)
                    ->header('Content-Type', 'text/csv')
                    ->header('Content-Disposition', 'attachment; filename="' . $label . '"');
            }
            if ($action === 'download_bsi') {
                $approvalRequest = null;
                if ($periodId > 0 && Schema::hasTable('payroll_report_requests')) {
                    $approvalRequest = PayrollReportRequest::where('company_id', $companyId)
                        ->where('period_id', $periodId)
                        ->first();
                }
                if (!$approvalRequest || ($approvalRequest->status ?? '') !== 'Approved') {
                    return back()->withErrors(['bank' => 'Payroll report belum disetujui. Approval wajib sebelum download bank.'])->withInput();
                }
                $company = Company::find($companyId);
                $bankType = $this->resolveBankType($company);
                if ($bankType !== 'BSI') {
                    return back()->withErrors(['bank' => 'Company ini tidak menggunakan BSI.'])->withInput();
                }
                if (!$company || trim((string) ($company->bank_name ?? '')) === '') {
                    return back()->withErrors(['bank' => 'Nama bank perusahaan belum diisi di Master Company.'])->withInput();
                }
                [$rows, $invalids, $totalAmount] = $this->buildBankTransferRows($companyId, $periodId, $bankType);
                if (empty($rows)) {
                    return back()->withErrors(['bank' => 'Tidak ada data valid untuk diexport.'])->withInput();
                }

                $monthNames = [
                    1 => 'Januari',
                    2 => 'Februari',
                    3 => 'Maret',
                    4 => 'April',
                    5 => 'Mei',
                    6 => 'Juni',
                    7 => 'Juli',
                    8 => 'Agustus',
                    9 => 'September',
                    10 => 'Oktober',
                    11 => 'November',
                    12 => 'Desember',
                ];
                $periodLabel = '';
                foreach ($periods as $p) {
                    if ((int) $p->id === (int) $periodId) {
                        $m = (int) $p->month;
                        $periodLabel = ($monthNames[$m] ?? $m) . ' ' . (int) $p->year;
                        break;
                    }
                }
                if ($periodLabel === '') {
                    $periodLabel = date('F Y');
                }
                $paymentSubject = 'Payroll ' . $periodLabel;

                $code = strtoupper(trim((string) ($company->company_code ?? '')));
                $code = preg_replace('/[^A-Z0-9]/', '', $code);
                $prefix = substr(str_pad($code !== '' ? $code : 'PAY', 4, 'X'), 0, 4);
                $uploadFileId = $prefix . date('mdy');

                $sanitizeText = static function (string $val): string {
                    $clean = str_replace(['|', "\r", "\n"], ' ', $val);
                    $clean = preg_replace('/\\s+/', ' ', $clean);
                    return trim((string) $clean);
                };

                $headerCols = [
                    'PAYMENT SUBJECT (50)',
                    'TRANSFER TYPE',
                    'BENEFICIARY COUNTRY ',
                    'BENEFICIARY TYPE ',
                    'DESTINATION',
                    'BENEFICIARY ACCT NAME ',
                    'BENEFICIARY NOTIF EMAIL(100)',
                    'CREDIT AMOUNT CCY',
                    'AMOUNT',
                    'USING SPECIAL RATE',
                    'TREASURY NUMBER',
                    'TRANSACTION TYPE',
                    'TRANSACTION PURPOSE CODE',
                    'UNDERLTYING DOC. TYPE CODE',
                    'DOC. UNDERLYING CODE',
                    'BANK NAME',
                    'BANK CODE',
                    'BENEFICIARY CITIZENSHIP',
                    'BENEFICIARY NATIONALITY',
                    'BENEFICIARY CATEGORY',
                    'BENEFICIARY IDENTIFICATION TYPE',
                    'BENEFICIARY IDENTIFICATION NUMBER',
                    'CITY',
                    'CHARGE TYPE',
                    'MESSAGE (65)',
                    'ADDITIONAL MESSAGE (16)',
                ];

                $lines = [];
                $totalRecord = count($rows);
                $totalAmountFormatted = number_format((float) $totalAmount, 0, '.', '');
                $lines[] = '0|' . $uploadFileId . '|' . date('Y-m-d') . '|' . $totalRecord . '|' . $totalAmountFormatted . '|';
                $lines[] = '0|' . implode('|', $headerCols) . '|';

                foreach ($rows as $r) {
                    $amount = number_format((float) $r['amount'], 0, '.', '');
                    $destination = $sanitizeText((string) $r['bank_account_no']);
                    $name = $sanitizeText((string) $r['name']);
                    $bankName = $sanitizeText((string) $r['bank_name']);
                    $bankCode = $sanitizeText((string) ($r['bank_code'] ?? ''));
                    $message = $sanitizeText($paymentSubject);
                    $cols = [
                        $message,
                        'PB',
                        'INDONESIA',
                        'BANK',
                        $destination,
                        $name,
                        '',
                        'IDR',
                        $amount,
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        $bankName,
                        $bankCode,
                        'ID',
                        'ID',
                        '',
                        '',
                        '',
                        '',
                        '',
                        $message,
                        '',
                    ];
                    $lines[] = '1|' . implode('|', $cols) . '|';
                }

                $content = implode("\r\n", $lines);
                $label = 'payroll_bsi_' . date('Ymd_His') . '.txt';
                return response($content)
                    ->header('Content-Type', 'text/plain')
                    ->header('Content-Disposition', 'attachment; filename="' . $label . '"');
            }
            if (!Schema::hasTable('payroll_report_requests')) {
                return back()->withErrors([
                    'approval' => 'Tabel payroll report request belum tersedia. Jalankan migrasi terlebih dahulu.',
                ])->withInput();
            }
            if ($periodId <= 0) {
                return redirect()->route('payroll.report');
            }

            $approvalRequest = PayrollReportRequest::where('company_id', $companyId)
                ->where('period_id', $periodId)
                ->first();

            if ($action === 'submit') {
                $requesterUserId = (int) ($user['id'] ?? 0);
                $steps = $this->approvalStepsFor($moduleKey, $companyId, $requesterUserId);
                if (empty($steps)) {
                    return back()->withErrors([
                        'approval' => 'Approval settings untuk Payroll Report belum diatur.',
                    ])->withInput();
                }

                $approvalRequest = PayrollReportRequest::firstOrNew([
                    'company_id' => $companyId,
                    'period_id' => $periodId,
                ]);
                $approvalRequest->requester_user_id = $requesterUserId;
                $approvalRequest->status = 'Pending Approval 1';
                $approvalRequest->submitted_at = now();
                $approvalRequest->approved_by = null;
                $approvalRequest->approved_at = null;
                $approvalRequest->rejected_by = null;
                $approvalRequest->rejected_at = null;
                $approvalRequest->rejected_note = null;
                $approvalRequest->save();

                $this->buildApprovalRequestSteps($moduleKey, (int) $approvalRequest->id, $companyId, $requesterUserId);
                $stepsRows = $this->getApprovalRequestSteps($moduleKey, (int) $approvalRequest->id);
                $firstPending = $this->pendingApprovalStep($stepsRows);
                if ($firstPending && (int) ($firstPending->approver_user_id ?? 0) > 0) {
                    $this->pushNotification(
                        $companyId,
                        (int) $firstPending->approver_user_id,
                        'Approval Payroll Report (Step 1)',
                        'Payroll report menunggu approval Anda.',
                        route('payroll.report_approval', ['period_id' => $periodId])
                    );
                }

                return redirect()->route('payroll.report', [
                    'period_id' => $periodId,
                    'approval_submitted' => 1,
                ]);
            }

            if (!$approvalRequest) {
                return redirect()->route('payroll.report', ['period_id' => $periodId]);
            }

            if ($action === 'approve_step') {
                $stepsRows = $this->ensureApprovalRequestSteps(
                    $moduleKey,
                    (int) $approvalRequest->id,
                    $companyId,
                    (int) ($approvalRequest->requester_user_id ?? 0)
                );
                $pending = $this->pendingApprovalStep($stepsRows);
                if (!$this->canApproveStep($pending, $user)) {
                    abort(403, 'Access denied.');
                }
                if ($pending) {
                    ApprovalRequestStep::where('id', (int) $pending->id)->update([
                        'status' => 'Approved',
                        'approved_by' => (int) ($user['id'] ?? 0),
                        'approved_at' => now(),
                        'signature' => 'Approved',
                    ]);
                }

                $stepsRows = $this->getApprovalRequestSteps($moduleKey, (int) $approvalRequest->id);
                $next = $this->pendingApprovalStep($stepsRows);
                if (!$next) {
                    $approvalRequest->status = 'Approved';
                    $approvalRequest->approved_by = (int) ($user['id'] ?? 0);
                    $approvalRequest->approved_at = now();
                    DB::table('payroll_period')
                        ->where('id', $periodId)
                        ->update(['status' => 'Close']);
                } else {
                    $approvalRequest->status = 'Pending Approval ' . (int) ($next->step_no ?? 1);
                }
                $approvalRequest->save();

                if ($next && (int) ($next->approver_user_id ?? 0) > 0) {
                    $this->pushNotification(
                        $companyId,
                        (int) $next->approver_user_id,
                        'Approval Payroll Report (Step ' . (int) ($next->step_no ?? 1) . ')',
                        'Payroll report menunggu approval Anda.',
                        route('payroll.report_approval', ['period_id' => $periodId])
                    );
                } else {
                    $requesterId = (int) ($approvalRequest->requester_user_id ?? 0);
                    if ($requesterId > 0) {
                        $this->pushNotification(
                            $companyId,
                            $requesterId,
                            'Payroll Report Disetujui',
                            'Payroll report Anda telah disetujui.',
                            route('payroll.report', ['period_id' => $periodId])
                        );
                    }
                }

                return redirect()->route('payroll.report', ['period_id' => $periodId]);
            }

            if ($action === 'reject') {
                $data = $request->validate([
                    'note' => ['nullable','string','max:255'],
                ]);
                $stepsRows = $this->ensureApprovalRequestSteps(
                    $moduleKey,
                    (int) $approvalRequest->id,
                    $companyId,
                    (int) ($approvalRequest->requester_user_id ?? 0)
                );
                $pending = $this->pendingApprovalStep($stepsRows);
                if (!$this->canApproveStep($pending, $user)) {
                    abort(403, 'Access denied.');
                }
                if ($pending) {
                    ApprovalRequestStep::where('id', (int) $pending->id)->update([
                        'status' => 'Rejected',
                    ]);
                }

                $approvalRequest->status = 'Rejected';
                $approvalRequest->rejected_by = (int) ($user['id'] ?? 0);
                $approvalRequest->rejected_at = now();
                $approvalRequest->rejected_note = $data['note'] ?? null;
                $approvalRequest->save();

                $requesterId = (int) ($approvalRequest->requester_user_id ?? 0);
                if ($requesterId > 0) {
                    $this->pushNotification(
                        $companyId,
                        $requesterId,
                        'Payroll Report Ditolak',
                        'Payroll report Anda ditolak. ' . trim((string) ($data['note'] ?? '')),
                        route('payroll.report', ['period_id' => $periodId])
                    );
                }

                return redirect()->route('payroll.report', ['period_id' => $periodId]);
            }
        }

        $items = $periodId ? PayrollService::itemsByPeriodCompany((int)$periodId, $companyId) : [];
        $company = Company::find($companyId);

        $bankType = $this->resolveBankType($company);
        [$bankRows, $bankInvalids, $bankTotalAmount] = $this->buildBankTransferRows($companyId, (int) $periodId, $bankType);
        $bankRemarkDefault = 'Payroll Maret 2026';
        $bankDebitAccount = trim((string) ($company->bank_debit_account_no ?? ''));
        $bankCompanyName = trim((string) ($company->bank_name ?? ''));

        $approvalRequest = null;
        if ($periodId > 0 && Schema::hasTable('payroll_report_requests')) {
            $approvalRequest = PayrollReportRequest::where('company_id', $companyId)
                ->where('period_id', $periodId)
                ->first();
        }
        $approvalSteps = [];
        $pendingStep = null;
        $pendingStepNo = null;
        $pendingApproverId = null;
        $canApprove = false;
        if ($approvalRequest) {
            $approvalSteps = $this->getApprovalRequestSteps($moduleKey, (int) $approvalRequest->id);
            if (empty($approvalSteps) && !in_array($approvalRequest->status, ['Approved', 'Rejected'], true)) {
                $approvalSteps = $this->ensureApprovalRequestSteps(
                    $moduleKey,
                    (int) $approvalRequest->id,
                    $companyId,
                    (int) ($approvalRequest->requester_user_id ?? 0)
                );
            }
            $pendingStep = $this->pendingApprovalStep($approvalSteps);
            if ($pendingStep) {
                $pendingStepNo = (int) ($pendingStep->step_no ?? 0);
                $pendingApproverId = (int) ($pendingStep->approver_user_id ?? 0);
            }
            $canApprove = $this->canApproveStep($pendingStep, $user);
        }

        $userMap = User::where('company_id', $companyId)
            ->orWhereIn('role', ['CEO', 'CFA', 'HR1', 'HR2'])
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $approvalStatusLabel = 'Belum diajukan';
        if ($approvalRequest) {
            if ($approvalRequest->status === 'Approved') {
                $approvalStatusLabel = 'Disetujui';
            } elseif ($approvalRequest->status === 'Rejected') {
                $approvalStatusLabel = 'Ditolak';
            } else {
                $approvalStatusLabel = (string) $approvalRequest->status;
            }
        }

        $pendingApproverLabel = null;
        if ($pendingApproverId > 0) {
            $pendingUser = $userMap[$pendingApproverId] ?? null;
            if ($pendingUser) {
                $pendingApproverLabel = $pendingUser->name . ' (' . $pendingUser->email . ')';
            }
        }

        $approvalRequesterLabel = null;
        $approvalApprovedLabel = null;
        $approvalRejectedLabel = null;
        if ($approvalRequest) {
            $reqUser = $userMap[(int) ($approvalRequest->requester_user_id ?? 0)] ?? null;
            if ($reqUser) {
                $approvalRequesterLabel = $reqUser->name . ' (' . $reqUser->email . ')';
            }
            $approvedUser = $userMap[(int) ($approvalRequest->approved_by ?? 0)] ?? null;
            if ($approvedUser) {
                $approvalApprovedLabel = $approvedUser->name . ' (' . $approvedUser->email . ')';
            }
            $rejectedUser = $userMap[(int) ($approvalRequest->rejected_by ?? 0)] ?? null;
            if ($rejectedUser) {
                $approvalRejectedLabel = $rejectedUser->name . ' (' . $rejectedUser->email . ')';
            }
        }

        $canExport = true;

        $allowanceCols = [
            'a2_overtime' => 'Lembur',
            'a3_meal' => 'Tunjangan Makan',
            'a4_transport' => 'Tunjangan Transport',
            'a5_performance' => 'Tunjangan Kinerja',
            'a6_position' => 'Tunjangan Jabatan',
            'a7_family' => 'Tunjangan Anak & Istri',
            'a8_communication' => 'Tunjangan Komunikasi',
            'a9_other' => 'Tunjangan Lain',
            'a10_thr' => 'THR',
            'a11_bonus' => 'Bonus',
            'a12_rapel_gaji' => 'Rapel Gaji',
            'a12_tax_allowance' => 'Tunjangan Pajak',
            'a13_bpjs_allowance' => 'Tunjangan BPJS'
        ];
        $deductionCols = [
            'b1_loan' => 'Pinjaman',
            'b2_absence' => 'Absensi',
            'b3_subsidy' => 'Subsidi',
            'b4_bpjs_health' => 'BPJS Kesehatan (1%)',
            'b5_jht' => 'JHT (2%)',
            'b6_jp' => 'JP (1%)',
            'b7_pph21' => 'PPH21',
            'b8_other' => 'Lain-lain'
        ];

        $totalBasic = 0.0;
        $totalIncome = 0.0;
        $totalDeduct = 0.0;
        $totalNet = 0.0;
        $totalAllowances = array_fill_keys(array_keys($allowanceCols), 0.0);
        $totalDeductions = array_fill_keys(array_keys($deductionCols), 0.0);
        foreach ($items as $i) {
            $totalBasic += (float)($i->basic_salary ?? 0);
            foreach ($allowanceCols as $key => $label) {
                $totalAllowances[$key] += (float)($i->$key ?? 0);
            }
            $totalIncome += (float)($i->total_penerimaan ?? 0);
            foreach ($deductionCols as $key => $label) {
                $totalDeductions[$key] += (float)($i->$key ?? 0);
            }
            $totalDeduct += (float)($i->total_potongan ?? 0);
            $totalNet += (float)($i->gaji_bersih ?? 0);
        }

        if ($request->query('format') === 'excel') {
            $label = 'periode';
            foreach ($periods as $p) {
                if ((int)$p->id === (int)$periodId) {
                    $label = sprintf('%02d-%04d', (int)$p->month, (int)$p->year);
                    break;
                }
            }
            $filename = 'payroll_report_' . $label . '_' . date('Ymd_His') . '.xls';
            $colspan = 3 + count($allowanceCols) + 1 + count($deductionCols) + 2;
            $periodLabel = $label;
            $html = "<table border=\"1\">";
            $html .= "<tr><td colspan=\"{$colspan}\"><strong>" . htmlspecialchars($company->company_name ?? 'Company') . "</strong><br>Periode: " . htmlspecialchars($periodLabel) . "</td></tr>";
            $html .= "<tr><td colspan=\"{$colspan}\"></td></tr>";
            $html .= "<thead><tr><th>NIK</th><th>Nama</th><th>Gaji Pokok</th>";
            foreach ($allowanceCols as $label) {
                $html .= "<th>" . htmlspecialchars($label) . "</th>";
            }
            $html .= "<th>Total Penerimaan</th>";
            foreach ($deductionCols as $label) {
                $html .= "<th>" . htmlspecialchars($label) . "</th>";
            }
            $html .= "<th>Total Potongan</th><th>Gaji Bersih</th></tr></thead><tbody>";
            foreach ($items as $i) {
                $html .= "<tr>";
                $html .= "<td>" . htmlspecialchars($i->nik) . "</td>";
                $html .= "<td>" . htmlspecialchars($i->name) . "</td>";
                $html .= "<td>" . htmlspecialchars(format_currency_id($i->basic_salary ?? 0, 2, false)) . "</td>";
                foreach ($allowanceCols as $key => $label) {
                    $html .= "<td>" . htmlspecialchars(format_currency_id($i->$key ?? 0, 2, false)) . "</td>";
                }
                $html .= "<td>" . htmlspecialchars(format_currency_id($i->total_penerimaan ?? 0, 2, false)) . "</td>";
                foreach ($deductionCols as $key => $label) {
                    $html .= "<td>" . htmlspecialchars(format_currency_id($i->$key ?? 0, 2, false)) . "</td>";
                }
                $html .= "<td>" . htmlspecialchars(format_currency_id($i->total_potongan ?? 0, 2, false)) . "</td>";
                $html .= "<td>" . htmlspecialchars(format_currency_id($i->gaji_bersih ?? 0, 2, false)) . "</td>";
                $html .= "</tr>";
            }
            $html .= "<tr><th colspan=\"2\">TOTAL</th>";
            $html .= "<th>" . htmlspecialchars(format_currency_id($totalBasic, 2, false)) . "</th>";
            foreach ($allowanceCols as $key => $label) {
                $html .= "<th>" . htmlspecialchars(format_currency_id($totalAllowances[$key], 2, false)) . "</th>";
            }
            $html .= "<th>" . htmlspecialchars(format_currency_id($totalIncome, 2, false)) . "</th>";
            foreach ($deductionCols as $key => $label) {
                $html .= "<th>" . htmlspecialchars(format_currency_id($totalDeductions[$key], 2, false)) . "</th>";
            }
            $html .= "<th>" . htmlspecialchars(format_currency_id($totalDeduct, 2, false)) . "</th>";
            $html .= "<th>" . htmlspecialchars(format_currency_id($totalNet, 2, false)) . "</th>";
            $html .= "</tr></tbody></table>";

            return response($html)
                ->header('Content-Type', 'application/vnd.ms-excel; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        }

        if ($request->query('format') === 'pdf') {
            $label = 'periode';
            foreach ($periods as $p) {
                if ((int)$p->id === (int)$periodId) {
                    $label = sprintf('%02d-%04d', (int)$p->month, (int)$p->year);
                    break;
                }
            }
            $colspan = 3 + count($allowanceCols) + 1 + count($deductionCols) + 2;
            $periodLabel = $label;
            $html = "<html><head><meta charset=\"UTF-8\"><style>
                @page { margin: 12px; }
                body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
                table { width: 100%; border-collapse: collapse; table-layout: fixed; }
                th, td { border: 1px solid #cfcfcf; padding: 4px; vertical-align: top; word-wrap: break-word; overflow-wrap: anywhere; }
                th { background: #f3f3f3; }
                .right { text-align: right; }
                .center { text-align: center; }
            </style></head><body>";
            $html .= "<table>";
            $html .= "<tr><td colspan=\"{$colspan}\"><strong>" . htmlspecialchars($company->company_name ?? 'Company') . "</strong><br>Periode: " . htmlspecialchars($periodLabel) . "</td></tr>";
            $html .= "<tr><td colspan=\"{$colspan}\"></td></tr>";
            $html .= "<thead><tr><th>NIK</th><th>Nama</th><th>Gaji Pokok</th>";
            foreach ($allowanceCols as $allowanceLabel) {
                $html .= "<th>" . htmlspecialchars($allowanceLabel) . "</th>";
            }
            $html .= "<th>Total Penerimaan</th>";
            foreach ($deductionCols as $deductionLabel) {
                $html .= "<th>" . htmlspecialchars($deductionLabel) . "</th>";
            }
            $html .= "<th>Total Potongan</th><th>Gaji Bersih</th></tr></thead><tbody>";
            foreach ($items as $i) {
                $html .= "<tr>";
                $html .= "<td>" . htmlspecialchars((string) $i->nik) . "</td>";
                $html .= "<td>" . htmlspecialchars((string) $i->name) . "</td>";
                $html .= "<td class=\"right\">" . htmlspecialchars(format_currency_id($i->basic_salary ?? 0, 2, false)) . "</td>";
                foreach ($allowanceCols as $key => $_) {
                    $html .= "<td class=\"right\">" . htmlspecialchars(format_currency_id($i->$key ?? 0, 2, false)) . "</td>";
                }
                $html .= "<td class=\"right\">" . htmlspecialchars(format_currency_id($i->total_penerimaan ?? 0, 2, false)) . "</td>";
                foreach ($deductionCols as $key => $_) {
                    $html .= "<td class=\"right\">" . htmlspecialchars(format_currency_id($i->$key ?? 0, 2, false)) . "</td>";
                }
                $html .= "<td class=\"right\">" . htmlspecialchars(format_currency_id($i->total_potongan ?? 0, 2, false)) . "</td>";
                $html .= "<td class=\"right\">" . htmlspecialchars(format_currency_id($i->gaji_bersih ?? 0, 2, false)) . "</td>";
                $html .= "</tr>";
            }
            $html .= "<tr><th colspan=\"2\">TOTAL</th>";
            $html .= "<th class=\"right\">" . htmlspecialchars(format_currency_id($totalBasic, 2, false)) . "</th>";
            foreach ($allowanceCols as $key => $_) {
                $html .= "<th class=\"right\">" . htmlspecialchars(format_currency_id($totalAllowances[$key], 2, false)) . "</th>";
            }
            $html .= "<th class=\"right\">" . htmlspecialchars(format_currency_id($totalIncome, 2, false)) . "</th>";
            foreach ($deductionCols as $key => $_) {
                $html .= "<th class=\"right\">" . htmlspecialchars(format_currency_id($totalDeductions[$key], 2, false)) . "</th>";
            }
            $html .= "<th class=\"right\">" . htmlspecialchars(format_currency_id($totalDeduct, 2, false)) . "</th>";
            $html .= "<th class=\"right\">" . htmlspecialchars(format_currency_id($totalNet, 2, false)) . "</th>";
            $html .= "</tr></tbody></table></body></html>";

            $dompdf = new Dompdf([
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ]);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A3', 'landscape');
            $dompdf->render();

            $filename = 'payroll_report_' . $label . '_' . date('Ymd_His') . '.pdf';
            return response($dompdf->output())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        }

        return view('modules.payroll.report', compact(
            'user',
            'companyId',
            'companies',
            'periods',
            'periodId',
            'items',
            'allowanceCols',
            'deductionCols',
            'totalBasic',
            'totalIncome',
            'totalDeduct',
            'totalNet',
            'approvalRequest',
            'approvalStatusLabel',
            'pendingStepNo',
            'pendingApproverLabel',
            'canApprove',
            'canExport',
            'approvalRequesterLabel',
            'approvalApprovedLabel',
            'approvalRejectedLabel',
            'bankType',
            'bankRows',
            'bankInvalids',
            'bankTotalAmount',
            'bankRemarkDefault',
            'bankDebitAccount',
            'bankCompanyName'
        ));
    }

    public function pph21(Request $request)
    {
        $user = current_user();
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            $params = [];
            if ($request->query('period_id')) {
                $params['period_id'] = $request->query('period_id');
            }
            if ($request->query('employee_id')) {
                $params['employee_id'] = $request->query('employee_id');
            }
            return redirect()->route('payroll.pph21', $params);
        }

        $companyId = current_company_id();
        $companies = Company::orderBy('id')->get();
        $periods = PayrollService::periods();
        $defaultPeriodId = (int) ($periods[0]->id ?? 0);
        $periodId = (int) $request->input('period_id', $request->query('period_id', $defaultPeriodId));
        $employeeId = (int) $request->query('employee_id', 0);

        $moduleKey = 'payroll_pph21';
        if ($request->isMethod('post')) {
            if (!Schema::hasTable('payroll_pph21_requests')) {
                return back()->withErrors([
                    'approval' => 'Tabel payroll PPh21 request belum tersedia. Jalankan migrasi terlebih dahulu.',
                ])->withInput();
            }
            $action = (string) $request->input('action', 'submit');
            if ($periodId <= 0) {
                return redirect()->route('payroll.pph21');
            }

            $approvalRequest = PayrollPph21Request::where('company_id', $companyId)
                ->where('period_id', $periodId)
                ->first();

            if ($action === 'submit') {
                $requesterUserId = (int) ($user['id'] ?? 0);
                $steps = $this->approvalStepsFor($moduleKey, $companyId, $requesterUserId);
                if (empty($steps)) {
                    return back()->withErrors([
                        'approval' => 'Approval settings untuk Payroll PPh21 belum diatur.',
                    ])->withInput();
                }

                $approvalRequest = PayrollPph21Request::firstOrNew([
                    'company_id' => $companyId,
                    'period_id' => $periodId,
                ]);
                $approvalRequest->requester_user_id = $requesterUserId;
                $approvalRequest->status = 'Pending Approval 1';
                $approvalRequest->submitted_at = now();
                $approvalRequest->approved_by = null;
                $approvalRequest->approved_at = null;
                $approvalRequest->rejected_by = null;
                $approvalRequest->rejected_at = null;
                $approvalRequest->rejected_note = null;
                $approvalRequest->save();

                $this->buildApprovalRequestSteps($moduleKey, (int) $approvalRequest->id, $companyId, $requesterUserId);
                $stepsRows = $this->getApprovalRequestSteps($moduleKey, (int) $approvalRequest->id);
                $firstPending = $this->pendingApprovalStep($stepsRows);
                if ($firstPending && (int) ($firstPending->approver_user_id ?? 0) > 0) {
                    $this->pushNotification(
                        $companyId,
                        (int) $firstPending->approver_user_id,
                        'Approval Payroll PPh21 (Step 1)',
                        'Payroll PPh21 menunggu approval Anda.',
                        route('payroll.pph21_approval', ['period_id' => $periodId])
                    );
                }

                return redirect()->route('payroll.pph21', [
                    'period_id' => $periodId,
                    'approval_submitted' => 1,
                ]);
            }

            if (!$approvalRequest) {
                return redirect()->route('payroll.pph21', ['period_id' => $periodId]);
            }

            if ($action === 'approve_step') {
                $stepsRows = $this->ensureApprovalRequestSteps(
                    $moduleKey,
                    (int) $approvalRequest->id,
                    $companyId,
                    (int) ($approvalRequest->requester_user_id ?? 0)
                );
                $pending = $this->pendingApprovalStep($stepsRows);
                if (!$this->canApproveStep($pending, $user)) {
                    abort(403, 'Access denied.');
                }
                if ($pending) {
                    ApprovalRequestStep::where('id', (int) $pending->id)->update([
                        'status' => 'Approved',
                        'approved_by' => (int) ($user['id'] ?? 0),
                        'approved_at' => now(),
                        'signature' => 'Approved',
                    ]);
                }

                $stepsRows = $this->getApprovalRequestSteps($moduleKey, (int) $approvalRequest->id);
                $next = $this->pendingApprovalStep($stepsRows);
                if (!$next) {
                    $approvalRequest->status = 'Approved';
                    $approvalRequest->approved_by = (int) ($user['id'] ?? 0);
                    $approvalRequest->approved_at = now();
                } else {
                    $approvalRequest->status = 'Pending Approval ' . (int) ($next->step_no ?? 1);
                }
                $approvalRequest->save();

                if ($next && (int) ($next->approver_user_id ?? 0) > 0) {
                    $this->pushNotification(
                        $companyId,
                        (int) $next->approver_user_id,
                        'Approval Payroll PPh21 (Step ' . (int) ($next->step_no ?? 1) . ')',
                        'Payroll PPh21 menunggu approval Anda.',
                        route('payroll.pph21_approval', ['period_id' => $periodId])
                    );
                } else {
                    $requesterId = (int) ($approvalRequest->requester_user_id ?? 0);
                    if ($requesterId > 0) {
                        $this->pushNotification(
                            $companyId,
                            $requesterId,
                            'Payroll PPh21 Disetujui',
                            'Payroll PPh21 Anda telah disetujui.',
                            route('payroll.pph21', ['period_id' => $periodId])
                        );
                    }
                }

                return redirect()->route('payroll.pph21', ['period_id' => $periodId]);
            }

            if ($action === 'reject') {
                $data = $request->validate([
                    'note' => ['nullable','string','max:255'],
                ]);
                $stepsRows = $this->ensureApprovalRequestSteps(
                    $moduleKey,
                    (int) $approvalRequest->id,
                    $companyId,
                    (int) ($approvalRequest->requester_user_id ?? 0)
                );
                $pending = $this->pendingApprovalStep($stepsRows);
                if (!$this->canApproveStep($pending, $user)) {
                    abort(403, 'Access denied.');
                }
                if ($pending) {
                    ApprovalRequestStep::where('id', (int) $pending->id)->update([
                        'status' => 'Rejected',
                    ]);
                }

                $approvalRequest->status = 'Rejected';
                $approvalRequest->rejected_by = (int) ($user['id'] ?? 0);
                $approvalRequest->rejected_at = now();
                $approvalRequest->rejected_note = $data['note'] ?? null;
                $approvalRequest->save();

                $requesterId = (int) ($approvalRequest->requester_user_id ?? 0);
                if ($requesterId > 0) {
                    $this->pushNotification(
                        $companyId,
                        $requesterId,
                        'Payroll PPh21 Ditolak',
                        'Payroll PPh21 Anda ditolak. ' . trim((string) ($data['note'] ?? '')),
                        route('payroll.pph21', ['period_id' => $periodId])
                    );
                }

                return redirect()->route('payroll.pph21', ['period_id' => $periodId]);
            }
        }

        $dataset = $periodId > 0
            ? Pph21Service::periodBreakdown($periodId, $companyId)
            : ['period' => null, 'rows' => collect(), 'summary' => [
                'employees' => 0,
                'total_bruto' => 0.0,
                'total_pph21_actual' => 0.0,
                'total_pph21_expected' => 0.0,
                'total_variance' => 0.0,
                'total_ytd_bruto' => 0.0,
            ]];

        $rows = $dataset['rows'];
        $summary = $dataset['summary'];
        $selected = null;
        if ($employeeId > 0) {
            $selected = Pph21Service::employeeBreakdown($periodId, $employeeId, $companyId);
        }

        $approvalRequest = null;
        if ($periodId > 0 && Schema::hasTable('payroll_pph21_requests')) {
            $approvalRequest = PayrollPph21Request::where('company_id', $companyId)
                ->where('period_id', $periodId)
                ->first();
        }
        $approvalSteps = [];
        $pendingStep = null;
        $pendingStepNo = null;
        $pendingApproverId = null;
        $canApprove = false;
        if ($approvalRequest) {
            $approvalSteps = $this->getApprovalRequestSteps($moduleKey, (int) $approvalRequest->id);
            if (empty($approvalSteps) && !in_array($approvalRequest->status, ['Approved', 'Rejected'], true)) {
                $approvalSteps = $this->ensureApprovalRequestSteps(
                    $moduleKey,
                    (int) $approvalRequest->id,
                    $companyId,
                    (int) ($approvalRequest->requester_user_id ?? 0)
                );
            }
            $pendingStep = $this->pendingApprovalStep($approvalSteps);
            if ($pendingStep) {
                $pendingStepNo = (int) ($pendingStep->step_no ?? 0);
                $pendingApproverId = (int) ($pendingStep->approver_user_id ?? 0);
            }
            $canApprove = $this->canApproveStep($pendingStep, $user);
        }

        $userMap = User::where('company_id', $companyId)
            ->orWhereIn('role', ['CEO', 'CFA', 'HR1', 'HR2'])
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $approvalStatusLabel = 'Belum diajukan';
        if ($approvalRequest) {
            if ($approvalRequest->status === 'Approved') {
                $approvalStatusLabel = 'Disetujui';
            } elseif ($approvalRequest->status === 'Rejected') {
                $approvalStatusLabel = 'Ditolak';
            } else {
                $approvalStatusLabel = (string) $approvalRequest->status;
            }
        }

        $pendingApproverLabel = null;
        if ($pendingApproverId > 0) {
            $pendingUser = $userMap[$pendingApproverId] ?? null;
            if ($pendingUser) {
                $pendingApproverLabel = $pendingUser->name . ' (' . $pendingUser->email . ')';
            }
        }

        $approvalRequesterLabel = null;
        $approvalApprovedLabel = null;
        $approvalRejectedLabel = null;
        if ($approvalRequest) {
            $reqUser = $userMap[(int) ($approvalRequest->requester_user_id ?? 0)] ?? null;
            if ($reqUser) {
                $approvalRequesterLabel = $reqUser->name . ' (' . $reqUser->email . ')';
            }
            $approvedUser = $userMap[(int) ($approvalRequest->approved_by ?? 0)] ?? null;
            if ($approvedUser) {
                $approvalApprovedLabel = $approvedUser->name . ' (' . $approvedUser->email . ')';
            }
            $rejectedUser = $userMap[(int) ($approvalRequest->rejected_by ?? 0)] ?? null;
            if ($rejectedUser) {
                $approvalRejectedLabel = $rejectedUser->name . ' (' . $rejectedUser->email . ')';
            }
        }

        return view('modules.payroll.pph21', compact(
            'user',
            'companyId',
            'companies',
            'periods',
            'periodId',
            'employeeId',
            'rows',
            'summary',
            'selected',
            'approvalRequest',
            'approvalStatusLabel',
            'pendingStepNo',
            'pendingApproverLabel',
            'canApprove',
            'approvalRequesterLabel',
            'approvalApprovedLabel',
            'approvalRejectedLabel'
        ));
    }

    public function reportApproval(Request $request)
    {
        $user = current_user();
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            $params = $request->query();
            unset($params['set_company']);
            return redirect()->route('payroll.report_approval', $params);
        }

        $companyId = current_company_id();
        $companies = Company::orderBy('id')->get();
        $periods = PayrollService::periods();
        $periodMap = collect($periods)->keyBy('id');

        $statusFilter = trim((string) $request->query('status', ''));
        $periodId = (int) $request->input('period_id', $request->query('period_id', 0));
        $messages = [];

        if ($request->isMethod('post')) {
            if (!Schema::hasTable('payroll_report_requests')) {
                return redirect()->route('payroll.report_approval');
            }
            if (!Schema::hasTable('approval_request_steps')) {
                return redirect()->route('payroll.report_approval');
            }
            $action = (string) $request->input('action', '');
            $data = $request->validate([
                'id' => ['required','integer','min:1'],
                'note' => ['nullable','string','max:255'],
            ]);
            $approvalRequest = PayrollReportRequest::find((int) $data['id']);
            if (!$approvalRequest || (int) $approvalRequest->company_id !== (int) $companyId) {
                return redirect()->route('payroll.report_approval');
            }

            $moduleKey = 'payroll_report';
            $stepsRows = $this->ensureApprovalRequestSteps(
                $moduleKey,
                (int) $approvalRequest->id,
                $companyId,
                (int) ($approvalRequest->requester_user_id ?? 0)
            );
            $pending = $this->pendingApprovalStep($stepsRows);
            if (!$this->canApproveStep($pending, $user)) {
                abort(403, 'Access denied.');
            }

            if ($action === 'approve_step') {
                if ($pending) {
                    ApprovalRequestStep::where('id', (int) $pending->id)->update([
                        'status' => 'Approved',
                        'approved_by' => (int) ($user['id'] ?? 0),
                        'approved_at' => now(),
                        'signature' => 'Approved',
                    ]);
                }

                $stepsRows = $this->getApprovalRequestSteps($moduleKey, (int) $approvalRequest->id);
                $next = $this->pendingApprovalStep($stepsRows);
                if (!$next) {
                    $approvalRequest->status = 'Approved';
                    $approvalRequest->approved_by = (int) ($user['id'] ?? 0);
                    $approvalRequest->approved_at = now();
                } else {
                    $approvalRequest->status = 'Pending Approval ' . (int) ($next->step_no ?? 1);
                }
                $approvalRequest->save();

                if ($next && (int) ($next->approver_user_id ?? 0) > 0) {
                    $this->pushNotification(
                        $companyId,
                        (int) $next->approver_user_id,
                        'Approval Payroll Report (Step ' . (int) ($next->step_no ?? 1) . ')',
                        'Payroll report menunggu approval Anda.',
                        route('payroll.report_approval', ['period_id' => (int) $approvalRequest->period_id])
                    );
                } else {
                    $requesterId = (int) ($approvalRequest->requester_user_id ?? 0);
                    if ($requesterId > 0) {
                        $this->pushNotification(
                            $companyId,
                            $requesterId,
                            'Payroll Report Disetujui',
                            'Payroll report Anda telah disetujui.',
                            route('payroll.report', ['period_id' => (int) $approvalRequest->period_id])
                        );
                    }
                }

                $messages[] = 'Approval berhasil disetujui.';
            } elseif ($action === 'reject') {
                if ($pending) {
                    ApprovalRequestStep::where('id', (int) $pending->id)->update([
                        'status' => 'Rejected',
                    ]);
                }

                $approvalRequest->status = 'Rejected';
                $approvalRequest->rejected_by = (int) ($user['id'] ?? 0);
                $approvalRequest->rejected_at = now();
                $approvalRequest->rejected_note = $data['note'] ?? null;
                $approvalRequest->save();

                $requesterId = (int) ($approvalRequest->requester_user_id ?? 0);
                if ($requesterId > 0) {
                    $this->pushNotification(
                        $companyId,
                        $requesterId,
                        'Payroll Report Ditolak',
                        'Payroll report Anda ditolak. ' . trim((string) ($data['note'] ?? '')),
                        route('payroll.report', ['period_id' => (int) $approvalRequest->period_id])
                    );
                }
                $messages[] = 'Approval ditolak.';
            }

            return redirect()->route('payroll.report_approval', [
                'period_id' => $periodId,
                'status' => $statusFilter,
            ]);
        }

        $requests = collect();
        if (Schema::hasTable('payroll_report_requests')) {
            $query = PayrollReportRequest::where('company_id', $companyId);
            if ($periodId > 0) {
                $query->where('period_id', $periodId);
            }
            if ($statusFilter !== '') {
                if ($statusFilter === 'pending') {
                    $query->where('status', 'like', 'Pending%');
                } else {
                    $query->where('status', $statusFilter);
                }
            }
            $requests = $query->orderByDesc('id')->get();
        }

        if ($requests->isNotEmpty() && Schema::hasTable('approval_request_steps')) {
            foreach ($requests as $row) {
                if (in_array($row->status, ['Approved', 'Rejected'], true)) {
                    continue;
                }
                $exists = ApprovalRequestStep::where('module_key', 'payroll_report')
                    ->where('request_id', (int) $row->id)
                    ->exists();
                if (!$exists) {
                    $this->buildApprovalRequestSteps(
                        'payroll_report',
                        (int) $row->id,
                        $companyId,
                        (int) ($row->requester_user_id ?? 0)
                    );
                }
            }
        }

        $stepMap = [];
        $pendingStepNo = [];
        $pendingApproverId = [];
        if ($requests->isNotEmpty() && Schema::hasTable('approval_request_steps')) {
            $rows = ApprovalRequestStep::where('module_key', 'payroll_report')
                ->whereIn('request_id', $requests->pluck('id')->all())
                ->orderBy('step_no')
                ->get();
            foreach ($rows as $r) {
                $stepMap[$r->request_id][] = $r;
                if (($r->status ?? '') === 'Pending' && !isset($pendingStepNo[$r->request_id])) {
                    $pendingStepNo[$r->request_id] = (int) $r->step_no;
                    $pendingApproverId[$r->request_id] = (int) ($r->approver_user_id ?? 0);
                }
            }
        }

        $userMap = User::where('company_id', $companyId)
            ->orWhereIn('role', ['CEO', 'CFA', 'HR1', 'HR2'])
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $pendingForUser = 0;
        $currentUserId = (int) ($user['id'] ?? 0);
        foreach ($pendingApproverId as $rid => $approverId) {
            if ($approverId === $currentUserId) {
                $pendingForUser++;
            }
        }

        $summaryRows = collect();
        if (Schema::hasTable('payroll_report_requests')) {
            $summaryRows = PayrollReportRequest::where('company_id', $companyId)
                ->select(
                    'period_id',
                    DB::raw("SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_total"),
                    DB::raw("SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_total"),
                    DB::raw("SUM(CASE WHEN status LIKE 'Pending%' THEN 1 ELSE 0 END) as pending_total"),
                    DB::raw("COUNT(*) as total")
                )
                ->groupBy('period_id')
                ->orderByDesc('period_id')
                ->get();
        }

        return view('modules.payroll.report_approval', compact(
            'user',
            'companyId',
            'companies',
            'periods',
            'periodMap',
            'requests',
            'statusFilter',
            'periodId',
            'messages',
            'stepMap',
            'pendingStepNo',
            'pendingApproverId',
            'userMap',
            'pendingForUser',
            'summaryRows'
        ));
    }

    public function pph21Approval(Request $request)
    {
        $user = current_user();
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            $params = $request->query();
            unset($params['set_company']);
            return redirect()->route('payroll.pph21_approval', $params);
        }

        $companyId = current_company_id();
        $companies = Company::orderBy('id')->get();
        $periods = PayrollService::periods();
        $periodMap = collect($periods)->keyBy('id');

        $statusFilter = trim((string) $request->query('status', ''));
        $periodId = (int) $request->input('period_id', $request->query('period_id', 0));
        $messages = [];

        if ($request->isMethod('post')) {
            if (!Schema::hasTable('payroll_pph21_requests')) {
                return redirect()->route('payroll.pph21_approval');
            }
            if (!Schema::hasTable('approval_request_steps')) {
                return redirect()->route('payroll.pph21_approval');
            }
            $action = (string) $request->input('action', '');
            $data = $request->validate([
                'id' => ['required','integer','min:1'],
                'note' => ['nullable','string','max:255'],
            ]);
            $approvalRequest = PayrollPph21Request::find((int) $data['id']);
            if (!$approvalRequest || (int) $approvalRequest->company_id !== (int) $companyId) {
                return redirect()->route('payroll.pph21_approval');
            }

            $moduleKey = 'payroll_pph21';
            $stepsRows = $this->ensureApprovalRequestSteps(
                $moduleKey,
                (int) $approvalRequest->id,
                $companyId,
                (int) ($approvalRequest->requester_user_id ?? 0)
            );
            $pending = $this->pendingApprovalStep($stepsRows);
            if (!$this->canApproveStep($pending, $user)) {
                abort(403, 'Access denied.');
            }

            if ($action === 'approve_step') {
                if ($pending) {
                    ApprovalRequestStep::where('id', (int) $pending->id)->update([
                        'status' => 'Approved',
                        'approved_by' => (int) ($user['id'] ?? 0),
                        'approved_at' => now(),
                        'signature' => 'Approved',
                    ]);
                }

                $stepsRows = $this->getApprovalRequestSteps($moduleKey, (int) $approvalRequest->id);
                $next = $this->pendingApprovalStep($stepsRows);
                if (!$next) {
                    $approvalRequest->status = 'Approved';
                    $approvalRequest->approved_by = (int) ($user['id'] ?? 0);
                    $approvalRequest->approved_at = now();
                } else {
                    $approvalRequest->status = 'Pending Approval ' . (int) ($next->step_no ?? 1);
                }
                $approvalRequest->save();

                if ($next && (int) ($next->approver_user_id ?? 0) > 0) {
                    $this->pushNotification(
                        $companyId,
                        (int) $next->approver_user_id,
                        'Approval Payroll PPh21 (Step ' . (int) ($next->step_no ?? 1) . ')',
                        'Payroll PPh21 menunggu approval Anda.',
                        route('payroll.pph21_approval', ['period_id' => (int) $approvalRequest->period_id])
                    );
                } else {
                    $requesterId = (int) ($approvalRequest->requester_user_id ?? 0);
                    if ($requesterId > 0) {
                        $this->pushNotification(
                            $companyId,
                            $requesterId,
                            'Payroll PPh21 Disetujui',
                            'Payroll PPh21 Anda telah disetujui.',
                            route('payroll.pph21', ['period_id' => (int) $approvalRequest->period_id])
                        );
                    }
                }

                $messages[] = 'Approval berhasil disetujui.';
            } elseif ($action === 'reject') {
                if ($pending) {
                    ApprovalRequestStep::where('id', (int) $pending->id)->update([
                        'status' => 'Rejected',
                    ]);
                }

                $approvalRequest->status = 'Rejected';
                $approvalRequest->rejected_by = (int) ($user['id'] ?? 0);
                $approvalRequest->rejected_at = now();
                $approvalRequest->rejected_note = $data['note'] ?? null;
                $approvalRequest->save();

                $requesterId = (int) ($approvalRequest->requester_user_id ?? 0);
                if ($requesterId > 0) {
                    $this->pushNotification(
                        $companyId,
                        $requesterId,
                        'Payroll PPh21 Ditolak',
                        'Payroll PPh21 Anda ditolak. ' . trim((string) ($data['note'] ?? '')),
                        route('payroll.pph21', ['period_id' => (int) $approvalRequest->period_id])
                    );
                }
                $messages[] = 'Approval ditolak.';
            }

            return redirect()->route('payroll.pph21_approval', [
                'period_id' => $periodId,
                'status' => $statusFilter,
            ]);
        }

        $requests = collect();
        if (Schema::hasTable('payroll_pph21_requests')) {
            $query = PayrollPph21Request::where('company_id', $companyId);
            if ($periodId > 0) {
                $query->where('period_id', $periodId);
            }
            if ($statusFilter !== '') {
                if ($statusFilter === 'pending') {
                    $query->where('status', 'like', 'Pending%');
                } else {
                    $query->where('status', $statusFilter);
                }
            }
            $requests = $query->orderByDesc('id')->get();
        }

        if ($requests->isNotEmpty() && Schema::hasTable('approval_request_steps')) {
            foreach ($requests as $row) {
                if (in_array($row->status, ['Approved', 'Rejected'], true)) {
                    continue;
                }
                $exists = ApprovalRequestStep::where('module_key', 'payroll_pph21')
                    ->where('request_id', (int) $row->id)
                    ->exists();
                if (!$exists) {
                    $this->buildApprovalRequestSteps(
                        'payroll_pph21',
                        (int) $row->id,
                        $companyId,
                        (int) ($row->requester_user_id ?? 0)
                    );
                }
            }
        }

        $stepMap = [];
        $pendingStepNo = [];
        $pendingApproverId = [];
        if ($requests->isNotEmpty() && Schema::hasTable('approval_request_steps')) {
            $rows = ApprovalRequestStep::where('module_key', 'payroll_pph21')
                ->whereIn('request_id', $requests->pluck('id')->all())
                ->orderBy('step_no')
                ->get();
            foreach ($rows as $r) {
                $stepMap[$r->request_id][] = $r;
                if (($r->status ?? '') === 'Pending' && !isset($pendingStepNo[$r->request_id])) {
                    $pendingStepNo[$r->request_id] = (int) $r->step_no;
                    $pendingApproverId[$r->request_id] = (int) ($r->approver_user_id ?? 0);
                }
            }
        }

        $userMap = User::where('company_id', $companyId)
            ->orWhereIn('role', ['CEO', 'CFA', 'HR1', 'HR2'])
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $pendingForUser = 0;
        $currentUserId = (int) ($user['id'] ?? 0);
        foreach ($pendingApproverId as $rid => $approverId) {
            if ($approverId === $currentUserId) {
                $pendingForUser++;
            }
        }

        $summaryRows = collect();
        if (Schema::hasTable('payroll_pph21_requests')) {
            $summaryRows = PayrollPph21Request::where('company_id', $companyId)
                ->select(
                    'period_id',
                    DB::raw("SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_total"),
                    DB::raw("SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_total"),
                    DB::raw("SUM(CASE WHEN status LIKE 'Pending%' THEN 1 ELSE 0 END) as pending_total"),
                    DB::raw("COUNT(*) as total")
                )
                ->groupBy('period_id')
                ->orderByDesc('period_id')
                ->get();
        }

        return view('modules.payroll.pph21_approval', compact(
            'user',
            'companyId',
            'companies',
            'periods',
            'periodMap',
            'requests',
            'statusFilter',
            'periodId',
            'messages',
            'stepMap',
            'pendingStepNo',
            'pendingApproverId',
            'userMap',
            'pendingForUser',
            'summaryRows'
        ));
    }

    public function bankTransfer(Request $request)
    {
        $user = current_user();
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            $params = $request->query();
            unset($params['set_company']);
            return redirect()->route('payroll.bank_transfer', $params);
        }

        $companyId = current_company_id();
        $companies = Company::orderBy('id')->get();
        $periods = PayrollService::periods();
        $periodId = (int) $request->input('period_id', $request->query('period_id', $periods[0]->id ?? 0));
        $companyId = (int) $request->input('company_id', $request->query('company_id', $companyId));
        if (!current_user_has_global_scope($user)) {
            $companyId = current_company_id();
        }
        $company = Company::find($companyId);

        $messages = [];
        $bankType = null;
        if ($company) {
            $code = strtoupper(trim((string) ($company->company_code ?? '')));
            if (in_array($code, ['BK', 'BN'], true)) {
                $bankType = 'BNI';
            } else {
                $bankType = 'BSI';
            }
        }

        $items = $periodId ? PayrollService::itemsByPeriodCompany($periodId, $companyId) : [];
        $employeeIds = [];
        foreach ($items as $row) {
            $employeeIds[] = (int) ($row->employee_id ?? 0);
        }
        $employeeIds = array_values(array_filter(array_unique($employeeIds)));
        $employeeMap = $employeeIds
            ? Employee::whereIn('id', $employeeIds)->get()->keyBy('id')
            : collect();

        $rows = [];
        $invalids = [];
        $totalAmount = 0;
        foreach ($items as $row) {
            $emp = $employeeMap[(int) ($row->employee_id ?? 0)] ?? null;
            $amount = (float) ($row->gaji_bersih ?? 0);
            $bankName = trim((string) ($emp->bank_name ?? ''));
            $bankAccount = trim((string) ($emp->bank_account_no ?? ''));
            $name = trim((string) ($row->name ?? ($emp->name ?? '')));

            $issues = [];
            if ($amount <= 0) {
                $issues[] = 'Gaji bersih 0';
            }
            if ($bankAccount === '') {
                $issues[] = 'Rekening kosong';
            }
            if ($bankName === '') {
                $issues[] = 'Nama bank kosong';
            }
            if ($bankType === 'BNI' && $bankName !== '' && stripos($bankName, 'BNI') === false) {
                $issues[] = 'Bank bukan BNI';
            }

            if (!empty($issues)) {
                $invalids[] = [
                    'name' => $name,
                    'nik' => $row->nik ?? '',
                    'bank_name' => $bankName,
                    'bank_account_no' => $bankAccount,
                    'amount' => $amount,
                    'issues' => implode(', ', $issues),
                ];
                continue;
            }

            $totalAmount += $amount;
            $rows[] = [
                'employee_id' => (int) ($row->employee_id ?? 0),
                'nik' => $row->nik ?? '',
                'name' => $name,
                'bank_name' => $bankName,
                'bank_account_no' => $bankAccount,
                'amount' => $amount,
            ];
        }

                $remarkDefault = 'Payroll Maret 2026';
                $debitAccount = trim((string) ($company->bank_debit_account_no ?? ''));
                if ($debitAccount === '') {
                    return back()->withErrors(['bank' => 'No. rekening debet perusahaan belum diisi di Master Company.'])->withInput();
                }

        if ($request->isMethod('post') && $request->input('action') === 'download_bni') {
            if ($bankType !== 'BNI') {
                return back()->withErrors(['bank' => 'Company ini tidak menggunakan BNI.'])->withInput();
            }
            if (empty($rows)) {
                return back()->withErrors(['bank' => 'Tidak ada data valid untuk diexport.'])->withInput();
            }

            $restricted = [',','`','~','!','@','#','$','%','^','&','*','_','{','}','<','>','[',']','=','\\',';'];
            $sanitize = static function (string $val) use ($restricted): string {
                $clean = str_replace($restricted, ' ', $val);
                $clean = preg_replace('/\\s+/', ' ', $clean);
                return trim((string) $clean);
            };

            $handle = fopen('php://temp', 'r+');
            $totalRecord = count($rows);
            $totalAmountFormatted = number_format((float) $totalAmount, 0, '.', '');
            $headerRow = [
                'P',
                date('d/m/Y'),
                $debitAccount,
                (string) $totalRecord,
                $totalAmountFormatted,
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
            ];
            fputcsv($handle, $headerRow);

            foreach ($rows as $r) {
                $amount = number_format((float) $r['amount'], 0, '.', '');
                $remark = $sanitize($remarkDefault);
                $name = $sanitize($r['name']);
                $ref = substr(preg_replace('/[^0-9A-Za-z]/', '', (string) ($r['nik'] ?? '')), 0, 16);
                fputcsv($handle, [
                    $sanitize((string) $r['bank_account_no']),
                    $name,
                    $amount,
                    $remark,
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    $ref,
                    '',
                ]);
            }

            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);

            $label = 'payroll_bni_' . date('Ymd_His') . '.csv';
            return response($csv)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $label . '"');
        }

        return view('modules.payroll.bank_transfer', compact(
            'user',
            'companyId',
            'companies',
            'periods',
            'periodId',
            'company',
            'bankType',
            'rows',
            'invalids',
            'totalAmount',
            'remarkDefault',
            'debitAccount',
            'messages'
        ));
    }

    public function slip(Request $request)
    {
        $user = current_user();
        $companyId = current_company_id();
        $companies = Company::orderBy('id')->get();
        $periods = PayrollService::periods();
        $periodId = $request->query('period_id', $periods[0]->id ?? 0);
        $currentPeriod = null;
        foreach ($periods as $p) {
            if ((int)$p->id === (int)$periodId) {
                $currentPeriod = $p;
                break;
            }
        }

        $employeeId = $request->query('employee_id');
        if ($user['role'] === 'Employee') {
            $sessionEmployeeId = (int)($user['employee_id'] ?? 0);
            if ($sessionEmployeeId <= 0) {
                abort(403, 'Akun Employee belum terhubung ke data karyawan.');
            }
            $employeeId = $sessionEmployeeId;
        }

        if ($request->query('format') === 'pdf') {
            $item = $this->resolveSlipItem($user, (int) $periodId, (int) $employeeId);
            if (!$item) {
                abort(404, 'Slip gaji belum tersedia untuk periode ini.');
            }
            $fallbackTotalHours = (float) ($item->a2_overtime_hours ?? 0);
            $overtimeBreakdown = $this->overtimeBreakdownByPeriodEmployee((int) $periodId, (int) $employeeId, $fallbackTotalHours);
            $taIlHours = (float) ($overtimeBreakdown['ta_il_hours'] ?? 0);
            $lemburHours = (float) ($overtimeBreakdown['lembur_hours'] ?? 0);
            $hasTaIl = (bool) ($overtimeBreakdown['has_ta_il'] ?? false);

            $gdAvailable = extension_loaded('gd');
            $logoDataUri = '';
            $logoFileUri = '';
            if (!empty($item->logo_path) && $gdAvailable) {
                $rawLogoPath = str_replace('\\', '/', trim((string)$item->logo_path));
                $logoCandidates = [];

                if (preg_match('#^([a-zA-Z]:/|/)#', $rawLogoPath) === 1) {
                    $logoCandidates[] = $rawLogoPath;
                } else {
                    $logoCandidates[] = public_path($rawLogoPath);
                    $logoCandidates[] = base_path($rawLogoPath);
                }

                foreach ($logoCandidates as $candidate) {
                    if (!is_file($candidate)) {
                        continue;
                    }
                    $realLogo = realpath($candidate);
                    if ($realLogo === false) {
                        continue;
                    }
                    $mime = 'image/png';
                    if (class_exists('finfo')) {
                        $finfo = new \finfo(FILEINFO_MIME_TYPE);
                        $detected = $finfo->file($realLogo);
                        if (is_string($detected) && strpos($detected, 'image/') === 0) {
                            $mime = $detected;
                        }
                    }
                    $logoContent = file_get_contents($realLogo);
                    if ($logoContent !== false) {
                        $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode($logoContent);
                    }
                    $logoFileUri = 'file:///' . str_replace('\\', '/', $realLogo);
                    break;
                }
            }

            ob_start();
            include resource_path('views/modules/payroll/slip_pdf.php');
            $html = ob_get_clean();

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
                ->header('Content-Disposition', 'attachment; filename="slip_gaji.pdf"');
        }

        $items = $periodId ? PayrollService::itemsByPeriodCompany((int)$periodId, $companyId) : collect();
        if (!$items instanceof \Illuminate\Support\Collection) {
            $items = collect($items);
        }
        if ($user['role'] === 'Employee') {
            $employeeIdInt = (int) $employeeId;
            $items = array_values(array_filter($items->all(), static function ($row) use ($employeeIdInt) {
                return (int)($row->employee_id ?? 0) === $employeeIdInt;
            }));
            $items = collect($items);
        }
        $item = null;
        if ($periodId && $employeeId) {
            $item = $this->resolveSlipItem($user, (int) $periodId, (int) $employeeId);
            if (!$item && ($user['role'] ?? '') !== 'Employee') {
                abort(403, 'Access denied');
            }
        }
        $overtimeBreakdown = $item
            ? $this->overtimeBreakdownByPeriodEmployee(
                (int) $periodId,
                (int) ($item->employee_id ?? $employeeId),
                (float) ($item->a2_overtime_hours ?? 0)
            )
            : ['ta_il_hours' => 0.0, 'lembur_hours' => 0.0, 'has_ta_il' => false];
        $taIlHours = (float) ($overtimeBreakdown['ta_il_hours'] ?? 0);
        $lemburHours = (float) ($overtimeBreakdown['lembur_hours'] ?? 0);
        $hasTaIl = (bool) ($overtimeBreakdown['has_ta_il'] ?? false);

        $messages = [];
        if (($user['role'] ?? '') === 'Employee' && $periodId && !$item) {
            $messages[] = 'Slip gaji belum tersedia untuk periode ini. Silakan hubungi HR untuk menjalankan payroll entitas Anda.';
        }

        return view('modules.payroll.slip', compact('user', 'companyId', 'companies', 'periods', 'periodId', 'currentPeriod', 'items', 'employeeId', 'item', 'messages', 'taIlHours', 'lemburHours', 'hasTaIl'));
    }

    public function qr(Request $request)
    {
        $periodId = (int) $request->query('period_id', 0);
        $employeeId = (int) $request->query('employee_id', 0);
        if ($periodId <= 0 || $employeeId <= 0) {
            abort(400, 'Invalid request');
        }

        $user = current_user();
        if (!current_user_has_global_scope($user)) {
            $item = PayrollService::itemByEmployeeCompany($periodId, $employeeId, current_company_id());
            if (!$item) {
                abort(403, 'Access denied');
            }
        }
        if (($user['role'] ?? '') === 'Employee') {
            $sessionEmployeeId = (int)($user['employee_id'] ?? 0);
            if ($sessionEmployeeId <= 0 || $sessionEmployeeId !== $employeeId) {
                abort(403, 'Access denied');
            }
        }

        $targetUrl = route('payroll.slip', ['period_id' => $periodId, 'employee_id' => $employeeId]);
        $qrCode = new QrCode($targetUrl);
        $qrCode->setSize(180);
        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        return response($result->getString())
            ->header('Content-Type', 'image/png');
    }
}
