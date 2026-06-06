<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MobileAppController extends Controller
{
    private function authUserOrRedirect()
    {
        if (!is_logged_in()) {
            return redirect()->route('mobile.login');
        }

        return null;
    }

    public function loginForm()
    {
        if (is_logged_in()) {
            return redirect()->route('mobile.home');
        }

        return view('mobile.login', [
            'error' => session('login_error', ''),
            'success' => session('auth_success', ''),
        ]);
    }

    public function login(Request $request)
    {
        $email = trim((string) $request->input('email'));
        $password = (string) $request->input('password');

        $user = User::where('email', $email)->first();
        $storedHash = (string) ($user->password_hash ?? $user->password ?? '');
        if (!$user || $storedHash === '' || !password_verify($password, $storedHash)) {
            return back()->with('login_error', 'Email atau password salah.');
        }
        if (isset($user->is_active) && (int) $user->is_active !== 1) {
            return back()->with('login_error', 'Akun belum aktif. Menunggu aktivasi Admin.');
        }

        $userArr = [
            'id' => $user->id,
            'company_id' => $user->company_id,
            'employee_id' => $user->employee_id ?? null,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];
        session(['user' => $userArr]);
        if (!empty($user->company_id)) {
            session(['company_id' => (int) $user->company_id]);
        }
        $request->session()->regenerate();

        return redirect()->route('mobile.home');
    }

    public function registerForm()
    {
        if (is_logged_in()) {
            return redirect()->route('mobile.home');
        }

        $companies = Company::orderBy('id')->get(['id', 'company_name']);
        $employees = Employee::query()
            ->orderBy('name')
            ->get(['id', 'company_id', 'nik', 'name']);

        return view('mobile.register', [
            'companies' => $companies,
            'employees' => $employees,
            'error' => session('register_error', ''),
            'success' => session('register_success', ''),
        ]);
    }

    public function register(Request $request)
    {
        if (is_logged_in()) {
            return redirect()->route('mobile.home');
        }

        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $employee = Employee::find((int) $data['employee_id']);
        if (!$employee || (int) $employee->company_id !== (int) $data['company_id']) {
            return back()->withErrors(['employee_id' => 'Employee tidak sesuai dengan entitas yang dipilih.'])->withInput();
        }

        $existingEmployeeUser = User::where('employee_id', (int) $employee->id)->first();
        if ($existingEmployeeUser) {
            return back()->withErrors(['employee_id' => 'Employee ini sudah terhubung ke akun user.'])->withInput();
        }

        $passwordHash = password_hash((string) $data['password'], PASSWORD_DEFAULT);
        $payload = [
            'company_id' => (int) $employee->company_id,
            'employee_id' => (int) $employee->id,
            'name' => (string) $employee->name,
            'email' => strtolower(trim((string) $data['email'])),
            'role' => 'Employee',
            'password_hash' => $passwordHash,
        ];
        if (Schema::hasColumn('users', 'password')) {
            $payload['password'] = $passwordHash;
        }
        if (Schema::hasColumn('users', 'must_verify_email')) {
            $payload['must_verify_email'] = 0;
        }
        if (Schema::hasColumn('users', 'is_active')) {
            $payload['is_active'] = 0;
        }

        User::create($payload);

        return redirect()->route('mobile.login')
            ->with('auth_success', 'Registrasi berhasil. Akun Anda menunggu aktivasi Administrator sebelum bisa login.');
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('mobile.login');
    }

    public function home()
    {
        if ($redirect = $this->authUserOrRedirect()) {
            return $redirect;
        }

        $user = current_user();
        $company = Company::find(current_company_id());
        $employeeId = (int) ($user['employee_id'] ?? 0);
        $today = date('Y-m-d');

        $todayLogs = collect();
        if ($employeeId > 0) {
            $todayLogs = DB::table('attendance_logs')
                ->where('company_id', current_company_id())
                ->where('employee_id', $employeeId)
                ->whereDate('scan_time', $today)
                ->orderBy('scan_time')
                ->get();
        }

        return view('mobile.home', compact('user', 'company', 'todayLogs'));
    }

    public function attendance()
    {
        if ($redirect = $this->authUserOrRedirect()) {
            return $redirect;
        }

        $user = current_user();
        $employeeId = (int) ($user['employee_id'] ?? 0);
        $logs = collect();

        if ($employeeId > 0) {
            $logs = DB::table('attendance_logs')
                ->where('company_id', current_company_id())
                ->where('employee_id', $employeeId)
                ->orderByDesc('scan_time')
                ->limit(20)
                ->get();
        }

        return view('mobile.attendance', compact('user', 'logs'));
    }

    public function recap(Request $request)
    {
        if ($redirect = $this->authUserOrRedirect()) {
            return $redirect;
        }

        $user = current_user();
        $employeeId = (int) ($user['employee_id'] ?? 0);
        $month = (int) $request->query('month', (int) date('n'));
        $year = (int) $request->query('year', (int) date('Y'));
        $mode = (string) $request->query('mode', 'cutoff');
        $startDateInput = (string) $request->query('start_date', '');
        $endDateInput = (string) $request->query('end_date', '');

        if ($mode === 'date_range' && $startDateInput !== '' && $endDateInput !== '') {
            $start = $startDateInput;
            $end = $endDateInput;
        } else {
            // Cut-off 20-21: periode 21 bulan sebelumnya sampai 20 bulan berjalan.
            $periodAnchor = sprintf('%04d-%02d-01', $year, $month);
            $start = date('Y-m-21', strtotime($periodAnchor . ' -1 month'));
            $end = date('Y-m-20', strtotime($periodAnchor));
            $mode = 'cutoff';
        }

        $rows = collect();
        if ($employeeId > 0) {
            $rows = DB::table('attendance_daily')
                ->where('employee_id', $employeeId)
                ->whereBetween('date', [$start, $end])
                ->orderByDesc('date')
                ->get();
        }

        $summary = [
            'hari_tercatat' => (int) $rows->count(),
            'total_jam_kerja' => (float) $rows->sum('work_hours'),
            'total_lembur' => (float) $rows->sum('overtime_hours'),
        ];

        return view('mobile.recap', compact(
            'user',
            'rows',
            'summary',
            'month',
            'year',
            'mode',
            'start',
            'end',
            'startDateInput',
            'endDateInput'
        ));
    }

    public function payslip(Request $request)
    {
        if ($redirect = $this->authUserOrRedirect()) {
            return $redirect;
        }

        $user = current_user();
        $companyId = current_company_id();
        $employeeId = (int) ($user['employee_id'] ?? 0);
        $periods = PayrollService::periods();
        $periodId = (int) $request->query('period_id', $periods[0]->id ?? 0);
        $taIlFlag = false;
        $taIlHours = 0.0;
        $validOvertimeHours = 0.0;

        $item = null;
        if ($employeeId > 0 && $periodId > 0) {
            $item = PayrollService::itemByEmployeeCompany($periodId, $employeeId, $companyId);
            $period = DB::table('payroll_period')->where('id', $periodId)->first();
            if ($period) {
                $range = PayrollService::periodRangeByPeriodRow($period);
                $startDate = (string) ($range['start_date'] ?? '');
                $endDate = (string) ($range['end_date'] ?? '');
                if ($startDate !== '' && $endDate !== '') {
                    $taIlHours = (float) DB::table('attendance_daily')
                        ->where('employee_id', $employeeId)
                        ->whereBetween('date', [$startDate, $endDate])
                        ->whereRaw('COALESCE(overtime_hours, 0) > 0')
                        ->whereRaw('COALESCE(no_overtime_permit, 0) = 1')
                        ->sum('overtime_hours');
                    $validOvertimeHours = (float) DB::table('attendance_daily')
                        ->where('employee_id', $employeeId)
                        ->whereBetween('date', [$startDate, $endDate])
                        ->whereRaw('COALESCE(overtime_hours, 0) > 0')
                        ->whereRaw('COALESCE(no_overtime_permit, 0) = 0')
                        ->sum('overtime_hours');
                    $taIlFlag = $taIlHours > 0;
                }
            }
        }

        return view('mobile.payslip', compact('user', 'periods', 'periodId', 'item', 'taIlFlag', 'taIlHours', 'validOvertimeHours'));
    }
}
