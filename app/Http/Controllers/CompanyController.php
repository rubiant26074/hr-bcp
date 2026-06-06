<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $messages = [];
        $errors = [];
        if (session()->has('company_messages')) {
            $messages = (array) session('company_messages');
        }
        if (session()->has('company_errors')) {
            $errors = (array) session('company_errors');
        }
        if ($request->isMethod('post') && $request->input('action') === 'delete') {
            $company = Company::find((int) $request->input('id'));
            if ($company) {
                $blockers = [];
                $companyId = (int) $company->id;
                $checks = [
                    ['table' => 'employees', 'label' => 'Employees'],
                    ['table' => 'payroll', 'label' => 'Payroll'],
                    ['table' => 'absence_requests', 'label' => 'Perizinan (Tidak Masuk)'],
                    ['table' => 'out_office_requests', 'label' => 'Perizinan (Keluar Kantor)'],
                    ['table' => 'overtime_requests', 'label' => 'Perizinan (Lembur)'],
                    ['table' => 'holidays', 'label' => 'Libur Nasional'],
                    ['table' => 'approval_settings', 'label' => 'Approval Settings'],
                    ['table' => 'approval_steps', 'label' => 'Approval Steps'],
                    ['table' => 'approval_request_steps', 'label' => 'Approval Request Steps'],
                    ['table' => 'payroll_report_requests', 'label' => 'Approval Payroll Report'],
                    ['table' => 'payroll_pph21_requests', 'label' => 'Approval Payroll PPh21'],
                    ['table' => 'notifications', 'label' => 'Notifications'],
                    ['table' => 'attendance_logs', 'label' => 'Attendance Logs'],
                    ['table' => 'attendance_daily', 'label' => 'Attendance Daily'],
                    ['table' => 'attendance_locations', 'label' => 'Attendance Locations'],
                    ['table' => 'org_structures', 'label' => 'Org Structures'],
                ];
                foreach ($checks as $check) {
                    if (!Schema::hasTable($check['table'])) {
                        continue;
                    }
                    $exists = DB::table($check['table'])
                        ->where('company_id', $companyId)
                        ->exists();
                    if ($exists) {
                        $blockers[] = $check['label'];
                    }
                }
                if (Schema::hasTable('contracts')) {
                    $hasContracts = Contract::whereHas('employee', function ($q) use ($companyId) {
                        $q->where('company_id', $companyId);
                    })->exists();
                    if ($hasContracts) {
                        $blockers[] = 'Contracts';
                    }
                }
                if (!empty($blockers)) {
                    $errors[] = 'Company tidak bisa dihapus. Masih ada data terkait: ' . implode(', ', $blockers) . '.';
                    return redirect()->route('company.index')->with('company_errors', $errors);
                }
                $company->delete();
                $messages[] = 'Company berhasil dihapus.';
                return redirect()->route('company.index')->with('company_messages', $messages);
            }
            return redirect()->route('company.index');
        }

        $q = trim((string) $request->query('q', ''));
        $companies = $q !== ''
            ? Company::where('company_name', 'like', '%' . $q . '%')
                ->orWhere('company_code', 'like', '%' . $q . '%')
                ->orderBy('id')
                ->get()
            : Company::orderBy('id')->get();

        return view('modules.company.index', compact('companies', 'q', 'messages', 'errors'));
    }

    public function form(Request $request)
    {
        $id = (int) ($request->query('id') ?? $request->input('id') ?? 0);
        $edit = $id ? Company::find($id) : null;
        if ($id && !$edit) {
            abort(404, 'Company not found');
        }

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'company_name' => ['required','string','max:255'],
                'company_code' => ['required','string','max:20'],
                'address' => ['nullable','string','max:255'],
                'npwp' => ['nullable','string','max:50'],
                'bank_name' => ['nullable','string','max:120'],
                'bank_debit_account_no' => ['nullable','string','max:50'],
                'bpjs_health_pct' => ['nullable','numeric','min:0','max:100'],
                'bpjs_jht_pct' => ['nullable','numeric','min:0','max:100'],
                'bpjs_jp_pct' => ['nullable','numeric','min:0','max:100'],
                'payroll_day' => ['nullable','integer','min:1','max:31'],
                'work_days_per_week' => ['nullable','integer','min:1','max:7'],
                'work_time_start' => ['nullable','regex:/^\\d{2}:\\d{2}(:\\d{2})?$/'],
                'work_time_end' => ['nullable','regex:/^\\d{2}:\\d{2}(:\\d{2})?$/'],
                'work_duration_hours' => ['nullable','numeric','min:0.5','max:24'],
                'work_days' => ['nullable','array'],
                'work_days.*' => ['string'],
                'logo' => ['nullable','file','mimes:jpg,jpeg,png','max:5120'],
            ]);

            $logoPath = $edit->logo_path ?? null;
            if ($request->hasFile('logo')) {
                $file = $request->file('logo');
                if (!$file->isValid()) {
                    abort(400, 'Upload logo gagal.');
                }
                $ext = strtolower($file->getClientOriginalExtension());
                $dir = public_path('uploads/companies');
                if (!File::exists($dir)) {
                    File::makeDirectory($dir, 0755, true);
                }
                $filename = 'logo_' . uniqid('', true) . '.' . $ext;
                $file->move($dir, $filename);
                $logoPath = 'uploads/companies/' . $filename;
            }

            $workDays = array_values(array_filter(array_map('trim', (array) ($data['work_days'] ?? [])), static function ($val) {
                return $val !== '';
            }));
            $daysPerWeek = (int) ($data['work_days_per_week'] ?? 0);
            if ($daysPerWeek <= 0) {
                $daysPerWeek = count($workDays) > 0 ? count($workDays) : 5;
            }

            $timeStart = $data['work_time_start'] ?? null;
            $timeEnd = $data['work_time_end'] ?? null;
            if ($timeStart !== null && $timeStart !== '') {
                $timeStart = strlen($timeStart) === 5 ? $timeStart . ':00' : $timeStart;
            } else {
                $timeStart = null;
            }
            if ($timeEnd !== null && $timeEnd !== '') {
                $timeEnd = strlen($timeEnd) === 5 ? $timeEnd . ':00' : $timeEnd;
            } else {
                $timeEnd = null;
            }

            $payload = [
                'company_name' => $data['company_name'],
                'company_code' => $data['company_code'],
                'address' => $data['address'] ?? '',
                'npwp' => $data['npwp'] ?? '',
                'bank_name' => $data['bank_name'] ?? '',
                'bank_debit_account_no' => $data['bank_debit_account_no'] ?? '',
                'logo_path' => $logoPath,
                'bpjs_health_pct' => (float) ($data['bpjs_health_pct'] ?? 1),
                'bpjs_jht_pct' => (float) ($data['bpjs_jht_pct'] ?? 2),
                'bpjs_jp_pct' => (float) ($data['bpjs_jp_pct'] ?? 1),
                'payroll_day' => (int) ($data['payroll_day'] ?? 25),
                'work_days_per_week' => $daysPerWeek,
                'work_time_start' => $timeStart,
                'work_time_end' => $timeEnd,
                'work_duration_hours' => (float) ($data['work_duration_hours'] ?? 8),
                'work_days_json' => count($workDays) > 0 ? json_encode($workDays) : null,
            ];

            if ($id) {
                $edit->fill($payload);
                $edit->save();
            } else {
                Company::create($payload);
            }
            return redirect()->route('company.index');
        }

        return view('modules.company.form', ['messages' => [], 'edit' => $edit]);
    }

    public function detail(int $id)
    {
        $company = Company::find($id);
        if (!$company) {
            abort(404, 'Company not found');
        }

        return view('modules.company.detail', compact('company'));
    }
}
