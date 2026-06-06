<?php

namespace App\Http\Controllers;

use DateTime;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    private function applyExcludeAbsenceExcludedStatuses($query, string $alias = 'e')
    {
        $employmentStatusColumn = $alias !== '' ? ($alias . '.employment_status') : 'employment_status';
        $activeStatusColumn = $alias !== '' ? ($alias . '.active_status') : 'active_status';
        $archivedActiveStatuses = collect(Employee::archiveActiveStatuses())
            ->map(fn ($v) => strtolower(trim((string) $v)))
            ->filter(fn ($v) => $v !== '')
            ->values()
            ->all();

        $query->where(function ($q) use ($employmentStatusColumn) {
            $q->whereNull($employmentStatusColumn)
                ->orWhereRaw("TRIM(COALESCE($employmentStatusColumn, '')) = ''")
                ->orWhereRaw(
                    "LOWER(TRIM(COALESCE($employmentStatusColumn, ''))) NOT IN (?, ?, ?, ?)",
                    ['komisaris', 'freelance', 'frelance', 'frelancer']
                );
        });

        return $query->where(function ($q) use ($activeStatusColumn, $archivedActiveStatuses) {
            $q->whereNull($activeStatusColumn)
                ->orWhereRaw("TRIM(COALESCE($activeStatusColumn, '')) = ''");

            if (!empty($archivedActiveStatuses)) {
                $placeholders = implode(', ', array_fill(0, count($archivedActiveStatuses), '?'));
                $q->orWhereRaw(
                    "LOWER(TRIM(COALESCE($activeStatusColumn, ''))) NOT IN ($placeholders)",
                    $archivedActiveStatuses
                );
            }
        });
    }

    public function index(Request $request)
    {
        $hasSpecialLeaveExcusedColumn = Schema::hasColumn('attendance_daily', 'is_special_leave_excused');
        $user = current_user();
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            $params = $request->query();
            unset($params['set_company']);
            return redirect()->route('dashboard', $params);
        }
        $companyId = current_company_id();
        $companies = Company::orderBy('id')->get();
        $companyCodeSummary = $companies
            ->pluck('company_code')
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter(fn ($code) => $code !== '')
            ->values()
            ->implode(' / ');
        if ($companyCodeSummary === '') {
            $companyCodeSummary = $companies
                ->pluck('company_name')
                ->map(fn ($name) => trim((string) $name))
                ->filter(fn ($name) => $name !== '')
                ->values()
                ->implode(' / ');
        }
        $currentUserId = (int) ($user['id'] ?? 0);

        $totalEmployeesQuery = DB::table('employees')
            ->where('company_id', $companyId);
        $this->applyExcludeAbsenceExcludedStatuses($totalEmployeesQuery, '');
        $totalEmployees = (int) $totalEmployeesQuery->count();

        $today = date('Y-m-d');
        $range = $request->query('range', 'week');
        if (!in_array($range, ['day', 'week', 'month'], true)) {
            $range = 'week';
        }
        $customFrom = (string) $request->query('from', '');
        $customTo = (string) $request->query('to', '');
        $customFromDb = date_input_to_db($customFrom);
        $customToDb = date_input_to_db($customTo);
        $hasManualDateInput = trim($customFrom) !== '' || trim($customTo) !== '';
        $customValid = $customFromDb && $customToDb;
        if ($range === 'month' && !$hasManualDateInput) {
            // Bulanan default selalu periode cut-off payroll: 21 bulan lalu s/d 20 bulan ini.
            $rangeEnd = (new DateTime($today))
                ->modify('first day of this month')
                ->modify('+19 days');
            $rangeStart = (clone $rangeEnd)
                ->modify('first day of this month')
                ->modify('-1 month')
                ->modify('+20 days');
        } elseif ($customValid) {
            $rangeStart = new DateTime($customFromDb);
            $rangeEnd = new DateTime($customToDb);
            if ($rangeEnd < $rangeStart) {
                $tmp = $rangeStart;
                $rangeStart = $rangeEnd;
                $rangeEnd = $tmp;
            }
        } else {
            $rangeStart = new DateTime($today);
            if ($range === 'day') {
                $rangeEnd = new DateTime($today);
            } else {
                $rangeStart = (new DateTime($today))->modify('-6 days');
                $rangeEnd = new DateTime($today);
            }
        }
        $rangeStartStr = $rangeStart->format('Y-m-d');
        $rangeEndStr = $rangeEnd->format('Y-m-d');
        $holidayDateSet = DB::table('holidays')
            ->where('company_id', 0)
            ->whereBetween('holiday_date', [$rangeStartStr, $rangeEndStr])
            ->pluck('holiday_date')
            ->map(fn ($v) => (string) $v)
            ->flip()
            ->all();

        $workDateKeys = [];
        $workDateLabels = [];
        $cursor = clone $rangeStart;
        while ($cursor <= $rangeEnd) {
            $dateKey = $cursor->format('Y-m-d');
            $isSunday = $cursor->format('D') === 'Sun';
            $isNationalHoliday = isset($holidayDateSet[$dateKey]);
            if (!$isSunday && !$isNationalHoliday) {
                $workDateKeys[] = $dateKey;
                $workDateLabels[$dateKey] = $cursor->format('d/m');
            }
            $cursor->modify('+1 day');
        }

        $todayIsWorkday = in_array($today, $workDateKeys, true);

        $hadir = 0;
        $terlambat = 0;
        $lemburHariIni = 0;
        $tidakHadir = 0;
        $attendancePct = 0;
        if ($todayIsWorkday) {
            $hadirQuery = DB::table('attendance_daily as d')
                ->join('employees as e', 'e.id', '=', 'd.employee_id')
                ->where('e.company_id', $companyId)
                ->where('d.date', $today)
                ->where(function ($q) use ($hasSpecialLeaveExcusedColumn) {
                    $q->whereNotNull('d.check_in')
                        ->orWhereNotNull('d.check_out')
                        ->orWhereRaw('COALESCE(d.work_hours, 0) > 0')
                        ->orWhere('d.is_sick_doctor_excused', 1);
                    if ($hasSpecialLeaveExcusedColumn) {
                        $q->orWhere('d.is_special_leave_excused', 1);
                    }
                })
                ->distinct('d.employee_id');
            $this->applyExcludeAbsenceExcludedStatuses($hadirQuery, 'e');
            $hadir = (int) $hadirQuery->count('d.employee_id');

            $terlambatQuery = DB::table('attendance_daily as d')
                ->join('employees as e', 'e.id', '=', 'd.employee_id')
                ->where('e.company_id', $companyId)
                ->where('d.date', $today)
                ->whereRaw("TIME(d.check_in) > '09:00:00'");
            $this->applyExcludeAbsenceExcludedStatuses($terlambatQuery, 'e');
            $terlambat = (int) $terlambatQuery->count();

            $lemburHariIniQuery = DB::table('attendance_daily as d')
                ->join('employees as e', 'e.id', '=', 'd.employee_id')
                ->where('e.company_id', $companyId)
                ->where('d.date', $today)
                ->where('d.overtime_hours', '>', 0);
            $this->applyExcludeAbsenceExcludedStatuses($lemburHariIniQuery, 'e');
            $lemburHariIni = (int) $lemburHariIniQuery->count();

            $tidakHadir = max(0, $totalEmployees - $hadir);
            $attendancePct = $totalEmployees > 0 ? round(($hadir / $totalEmployees) * 100) : 0;
        }

        $periodDays = count($workDateKeys);
        $presentByDate = [];
        $presentRows = DB::table('attendance_daily as d')
            ->join('employees as e', 'e.id', '=', 'd.employee_id')
            ->where('e.company_id', $companyId)
            ->whereIn('d.date', $workDateKeys)
            ->where(function ($q) use ($hasSpecialLeaveExcusedColumn) {
                $q->whereNotNull('d.check_in')
                  ->orWhereNotNull('d.check_out')
                  ->orWhereRaw('COALESCE(d.work_hours, 0) > 0')
                  ->orWhere('d.is_sick_doctor_excused', 1);
                if ($hasSpecialLeaveExcusedColumn) {
                    $q->orWhere('d.is_special_leave_excused', 1);
                }
            });
        $this->applyExcludeAbsenceExcludedStatuses($presentRows, 'e');
        $presentRows = $presentRows
            ->select('d.date', DB::raw('COUNT(DISTINCT d.employee_id) AS present_count'))
            ->groupBy('d.date')
            ->get();
        foreach ($presentRows as $row) {
            $presentByDate[$row->date] = (int) $row->present_count;
        }

        $labels = [];
        $presentSeries = [];
        $absentSeries = [];
        $presentTotal = 0;
        foreach ($workDateKeys as $d) {
            $labels[] = $workDateLabels[$d] ?? $d;
            $present = $presentByDate[$d] ?? 0;
            $presentTotal += $present;
            $absent = max(0, $totalEmployees - $present);
            $presentSeries[] = $present;
            $absentSeries[] = $absent;
        }
        $expectedTotal = $totalEmployees * $periodDays;
        $absentTotal = max(0, $expectedTotal - $presentTotal);

        $overtimeSum = DB::table('attendance_daily as d')
            ->join('employees as e', 'e.id', '=', 'd.employee_id')
            ->where('e.company_id', $companyId)
            ->whereIn('d.date', $workDateKeys);
        $this->applyExcludeAbsenceExcludedStatuses($overtimeSum, 'e');
        $overtimeSum = (float) $overtimeSum->sum('d.overtime_hours');

        $lateCount = DB::table('attendance_daily as d')
            ->join('employees as e', 'e.id', '=', 'd.employee_id')
            ->where('e.company_id', $companyId)
            ->whereIn('d.date', $workDateKeys)
            ->whereRaw("TIME(d.check_in) > '09:00:00'");
        $this->applyExcludeAbsenceExcludedStatuses($lateCount, 'e');
        $lateCount = (int) $lateCount->count();

        $cuti = 0;
        $izin = 0;
        if (Schema::hasTable('absence_requests')) {
            $pendingAbsence = DB::table('absence_requests')
                ->where('company_id', $companyId)
                ->where('status', 'like', 'Pending%');
            $cuti = (int) (clone $pendingAbsence)
                ->whereRaw("LOWER(COALESCE(request_type, '')) LIKE 'cuti%'")
                ->count();
            $pendingAbsenceTotal = (int) (clone $pendingAbsence)->count();
            $izin = max(0, $pendingAbsenceTotal - $cuti);
        }
        $reimburse = 0;
        if ($currentUserId > 0 && Schema::hasTable('approval_request_steps')) {
            $reimburse = (int) DB::table('approval_request_steps')
                ->whereIn('module_key', ['reimburse', 'reimbursement'])
                ->where('status', 'Pending')
                ->where('approver_user_id', $currentUserId)
                ->count();
        }
        $approvalPending = $cuti + $izin + $lemburHariIni + $reimburse;

        $periodActive = '-';
        $periodActiveLabel = 'Payroll Status';
        $latestPeriod = DB::table('payroll_period')->orderByDesc('year')->orderByDesc('month')->first();
        if ($latestPeriod) {
            $periodActive = $latestPeriod->month . '/' . $latestPeriod->year;
            $periodActiveLabel = $latestPeriod->status;
        }

        $todayDt = new DateTime($today);
        $end30 = (clone $todayDt)->modify('+30 days')->format('Y-m-d');
        $end7 = (clone $todayDt)->modify('+7 days')->format('Y-m-d');
        $contractSub = DB::table('employee_contracts')
            ->select('employee_id', DB::raw('MAX(end_date) AS end_date'))
            ->whereNotNull('end_date')
            ->groupBy('employee_id');

        $pkwt30Count = (int) DB::query()
            ->fromSub($contractSub, 'c')
            ->join('employees as e', 'e.id', '=', 'c.employee_id')
            ->where('e.company_id', $companyId)
            ->whereBetween('c.end_date', [$today, $end30])
            ->count();

        $pkwt7Count = (int) DB::query()
            ->fromSub($contractSub, 'c')
            ->join('employees as e', 'e.id', '=', 'c.employee_id')
            ->where('e.company_id', $companyId)
            ->whereBetween('c.end_date', [$today, $end7])
            ->count();

        $new30Count = (int) DB::table('employees')
            ->where('company_id', $companyId)
            ->whereNotNull('join_date')
            ->whereBetween('join_date', [(clone $todayDt)->modify('-30 days')->format('Y-m-d'), $today])
            ->where(function ($q) {
                $q->whereNull('employment_status')
                    ->orWhereRaw("TRIM(COALESCE(employment_status, '')) = ''")
                    ->orWhereRaw(
                        "LOWER(TRIM(COALESCE(employment_status, ''))) NOT IN (?, ?, ?, ?)",
                        ['komisaris', 'freelance', 'frelance', 'frelancer']
                    );
            })
            ->count();

        $mutasi = 0;
        if (Schema::hasTable('employee_mutations')) {
            $mutasi = (int) DB::table('employee_mutations')
                ->where('from_company_id', $companyId)
                ->whereBetween(DB::raw('DATE(mutated_at)'), [(clone $todayDt)->modify('-30 days')->format('Y-m-d'), $today])
                ->count();
        }
        $notif = 0;
        if ($currentUserId > 0 && \Illuminate\Support\Facades\Schema::hasTable('notifications')) {
            $notif = (int) \Illuminate\Support\Facades\DB::table('notifications')
                ->where('company_id', $companyId)
                ->where('user_id', $currentUserId)
                ->count();
        }
        $unread = 0;
        if ($currentUserId > 0 && \Illuminate\Support\Facades\Schema::hasTable('notifications')) {
            $unread = (int) \Illuminate\Support\Facades\DB::table('notifications')
                ->where('company_id', $companyId)
                ->where('user_id', $currentUserId)
                ->where('is_read', 0)
                ->count();
        }
        $workflow7 = 0;
        if ($currentUserId > 0 && Schema::hasTable('approval_request_steps')) {
            $workflowQuery = DB::table('approval_request_steps')
                ->where('approver_user_id', $currentUserId)
                ->where('status', 'Pending');
            if (Schema::hasColumn('approval_request_steps', 'created_at')) {
                $workflowQuery->where('created_at', '>=', now()->subDays(7));
            }
            $workflow7 = (int) $workflowQuery->count();
        }

        $lastActivity = DB::table('attendance_logs as l')
            ->leftJoin('employees as e', 'e.id', '=', 'l.employee_id')
            ->where('l.company_id', $companyId)
            ->where(function ($q) {
                $q->whereNull('e.id')
                    ->orWhereRaw(
                        "LOWER(TRIM(COALESCE(e.employment_status, ''))) NOT IN (?, ?, ?, ?)",
                        ['komisaris', 'freelance', 'frelance', 'frelancer']
                    );
            })
            ->orderByDesc('l.scan_time')
            ->select('l.scan_time', 'e.name')
            ->first();

        $deptRows = DB::table('attendance_daily as d')
            ->join('employees as e', 'e.id', '=', 'd.employee_id')
            ->where('e.company_id', $companyId)
            ->whereBetween('d.date', [$rangeStartStr, $rangeEndStr]);
        $this->applyExcludeAbsenceExcludedStatuses($deptRows, 'e');
        $deptRows = $deptRows
            ->groupBy('e.position')
            ->orderByDesc('ot')
            ->limit(5)
            ->select('e.position as dept', DB::raw('SUM(d.overtime_hours) as ot'))
            ->get();

        $lateDaysExpr = "COUNT(DISTINCT CASE WHEN (d.check_in IS NOT NULL AND TIME(d.check_in) > '09:00:00') THEN d.date END) as late_days";
        $lateLt15Expr = "COUNT(DISTINCT CASE WHEN (d.check_in IS NOT NULL AND TIMESTAMPDIFF(MINUTE, CONCAT(d.date, ' 09:00:00'), d.check_in) > 0 AND TIMESTAMPDIFF(MINUTE, CONCAT(d.date, ' 09:00:00'), d.check_in) < 15) THEN d.date END) as late_lt_15_days";
        $lateGte15Expr = "COUNT(DISTINCT CASE WHEN (d.check_in IS NOT NULL AND TIMESTAMPDIFF(MINUTE, CONCAT(d.date, ' 09:00:00'), d.check_in) >= 15) THEN d.date END) as late_gte_15_days";
        $presentDaysExpr = "COUNT(DISTINCT CASE WHEN (d.check_in IS NOT NULL OR d.check_out IS NOT NULL OR COALESCE(d.work_hours, 0) > 0 OR COALESCE(d.is_sick_doctor_excused, 0) = 1) THEN d.date END) as present_days";
        if ($hasSpecialLeaveExcusedColumn) {
            $presentDaysExpr = "COUNT(DISTINCT CASE WHEN (d.check_in IS NOT NULL OR d.check_out IS NOT NULL OR COALESCE(d.work_hours, 0) > 0 OR COALESCE(d.is_sick_doctor_excused, 0) = 1 OR COALESCE(d.is_special_leave_excused, 0) = 1) THEN d.date END) as present_days";
        }
        if (Schema::hasColumn('attendance_daily', 'late_minutes')) {
            $lateDaysExpr = "COUNT(DISTINCT CASE WHEN (COALESCE(d.late_minutes, 0) > 0 OR (d.late_minutes IS NULL AND d.check_in IS NOT NULL AND TIME(d.check_in) > '09:00:00')) THEN d.date END) as late_days";
            $lateLt15Expr = "COUNT(DISTINCT CASE WHEN (COALESCE(d.late_minutes, 0) > 0 AND COALESCE(d.late_minutes, 0) < 15) THEN d.date END) as late_lt_15_days";
            $lateGte15Expr = "COUNT(DISTINCT CASE WHEN (COALESCE(d.late_minutes, 0) >= 15) THEN d.date END) as late_gte_15_days";
        }

        $attendanceRankBase = DB::table('employees as e')
            ->leftJoin('attendance_daily as d', function ($join) use ($workDateKeys) {
                $join->on('d.employee_id', '=', 'e.id')
                    ->whereIn('d.date', $workDateKeys);
            })
            ->where('e.company_id', $companyId);
        $this->applyExcludeAbsenceExcludedStatuses($attendanceRankBase, 'e');
        $attendanceRankBase = $attendanceRankBase
            ->groupBy('e.id', 'e.name', 'e.nik', 'e.position')
            ->select(
                'e.id',
                'e.name',
                'e.nik',
                'e.position',
                DB::raw($presentDaysExpr),
                DB::raw($lateDaysExpr),
                DB::raw($lateLt15Expr),
                DB::raw($lateGte15Expr)
            );

        $attendanceRankSub = DB::query()->fromSub($attendanceRankBase, 'r');

        $topAttendancePeople = (clone $attendanceRankSub)
            ->orderByDesc('present_days')
            ->orderBy('late_days')
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(function ($row) use ($periodDays) {
                $row->present_days = (int) $row->present_days;
                $row->late_days = (int) $row->late_days;
                $row->late_lt_15_days = (int) ($row->late_lt_15_days ?? 0);
                $row->late_gte_15_days = (int) ($row->late_gte_15_days ?? 0);
                $row->absent_days = max(0, $periodDays - $row->present_days);
                return $row;
            });

        $bottomAttendancePeople = (clone $attendanceRankSub)
            ->orderByRaw('(? - r.present_days) DESC', [$periodDays])
            ->orderByDesc('late_days')
            ->orderBy('present_days')
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(function ($row) use ($periodDays) {
                $row->present_days = (int) $row->present_days;
                $row->late_days = (int) $row->late_days;
                $row->late_lt_15_days = (int) ($row->late_lt_15_days ?? 0);
                $row->late_gte_15_days = (int) ($row->late_gte_15_days ?? 0);
                $row->absent_days = max(0, $periodDays - $row->present_days);
                return $row;
            });

        $closedPeriod = DB::table('payroll_period')
            ->where('status', 'Closed')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();

        $payrollByCompany = [];
        if ($closedPeriod) {
            $base = DB::table('payroll as p')
                ->join('employees as e', 'e.id', '=', 'p.employee_id')
                ->join('companies as c', 'c.id', '=', 'e.company_id')
                ->where('p.period_id', $closedPeriod->id)
                ->select('c.company_code', 'c.company_name', DB::raw('SUM(p.gaji_bersih) as total'))
                ->groupBy('c.id', 'c.company_code', 'c.company_name')
                ->orderBy('c.company_code');

            if (!current_user_has_global_scope($user)) {
                $base->where('e.company_id', $companyId);
            }
            $payrollByCompany = $base->get();
        }

        $payrollReportPendingCount = 0;
        $payrollReportPendingList = collect();
        $payrollReportPeriodMap = collect();
        $payrollReportRequesterMap = collect();
        if ($currentUserId > 0 && Schema::hasTable('payroll_report_requests') && Schema::hasTable('approval_request_steps')) {
            $pendingRows = DB::table('approval_request_steps as s')
                ->join('payroll_report_requests as r', 'r.id', '=', 's.request_id')
                ->where('s.module_key', 'payroll_report')
                ->where('s.status', 'Pending')
                ->where('s.approver_user_id', $currentUserId)
                ->where('r.company_id', $companyId)
                ->select('r.id', 'r.period_id', 'r.requester_user_id', 'r.status', 's.step_no')
                ->orderByDesc('r.id')
                ->get();

            $payrollReportPendingCount = (int) $pendingRows->count();
            $payrollReportPendingList = $pendingRows->take(5);

            $periodIds = $pendingRows->pluck('period_id')->filter()->unique()->values()->all();
            if (!empty($periodIds)) {
                $payrollReportPeriodMap = DB::table('payroll_period')
                    ->whereIn('id', $periodIds)
                    ->get()
                    ->keyBy('id');
            }

            $requesterIds = $pendingRows->pluck('requester_user_id')->filter()->unique()->values()->all();
            if (!empty($requesterIds)) {
                $payrollReportRequesterMap = DB::table('users')
                    ->whereIn('id', $requesterIds)
                    ->get()
                    ->keyBy('id');
            }
        }

        $payrollPph21PendingCount = 0;
        $payrollPph21PendingList = collect();
        $payrollPph21PeriodMap = collect();
        $payrollPph21RequesterMap = collect();
        if ($currentUserId > 0 && Schema::hasTable('payroll_pph21_requests') && Schema::hasTable('approval_request_steps')) {
            $pendingRows = DB::table('approval_request_steps as s')
                ->join('payroll_pph21_requests as r', 'r.id', '=', 's.request_id')
                ->where('s.module_key', 'payroll_pph21')
                ->where('s.status', 'Pending')
                ->where('s.approver_user_id', $currentUserId)
                ->where('r.company_id', $companyId)
                ->select('r.id', 'r.period_id', 'r.requester_user_id', 'r.status', 's.step_no')
                ->orderByDesc('r.id')
                ->get();

            $payrollPph21PendingCount = (int) $pendingRows->count();
            $payrollPph21PendingList = $pendingRows->take(5);

            $periodIds = $pendingRows->pluck('period_id')->filter()->unique()->values()->all();
            if (!empty($periodIds)) {
                $payrollPph21PeriodMap = DB::table('payroll_period')
                    ->whereIn('id', $periodIds)
                    ->get()
                    ->keyBy('id');
            }

            $requesterIds = $pendingRows->pluck('requester_user_id')->filter()->unique()->values()->all();
            if (!empty($requesterIds)) {
                $payrollPph21RequesterMap = DB::table('users')
                    ->whereIn('id', $requesterIds)
                    ->get()
                    ->keyBy('id');
            }
        }

        $dashboardLabels = [];
        if (Schema::hasTable('dashboard_labels')) {
            $rows = DB::table('dashboard_labels')
                ->whereIn('company_id', [0, $companyId])
                ->orderBy('company_id')
                ->orderBy('label_key')
                ->get(['company_id', 'label_key', 'label_value']);
            foreach ($rows as $row) {
                $dashboardLabels[(string) $row->label_key] = (string) $row->label_value;
            }
        }

        return view('modules.dashboard.index', compact(
            'user',
            'companies',
            'companyCodeSummary',
            'dashboardLabels',
            'totalEmployees',
            'today',
            'range',
            'customFromDb',
            'customToDb',
            'rangeStartStr',
            'rangeEndStr',
            'hadir',
            'terlambat',
            'lemburHariIni',
            'tidakHadir',
            'attendancePct',
            'labels',
            'presentSeries',
            'absentSeries',
            'presentTotal',
            'absentTotal',
            'overtimeSum',
            'lateCount',
            'cuti',
            'izin',
            'reimburse',
            'approvalPending',
            'periodActive',
            'periodActiveLabel',
            'pkwt30Count',
            'pkwt7Count',
            'new30Count',
            'mutasi',
            'notif',
            'unread',
            'workflow7',
            'lastActivity',
            'deptRows',
            'topAttendancePeople',
            'bottomAttendancePeople',
            'closedPeriod',
            'payrollByCompany',
            'payrollReportPendingCount',
            'payrollReportPendingList',
            'payrollReportPeriodMap',
            'payrollReportRequesterMap',
            'payrollPph21PendingCount',
            'payrollPph21PendingList',
            'payrollPph21PeriodMap',
            'payrollPph21RequesterMap'
        ));
    }
}
