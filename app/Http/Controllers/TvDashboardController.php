<?php

namespace App\Http\Controllers;

use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TvDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = current_user();
        $companyId = current_company_id();
        $isSuper = ($user['role'] ?? '') === 'Super Admin';

        $today = date('Y-m-d');
        $rangeStart = (new DateTime($today))->modify('-6 days');
        $rangeEnd = new DateTime($today);
        $rangeStartStr = $rangeStart->format('Y-m-d');
        $rangeEndStr = $rangeEnd->format('Y-m-d');
        $periodDays = (int) $rangeEnd->diff($rangeStart)->format('%a') + 1;

        $employeeQuery = DB::table('employees');
        if (!$isSuper) {
            $employeeQuery->where('company_id', $companyId);
        }
        $totalEmployees = (int) $employeeQuery->count();

        $presentToday = (int) DB::table('attendance_daily as d')
            ->join('employees as e', 'e.id', '=', 'd.employee_id')
            ->when(!$isSuper, function ($q) use ($companyId) {
                $q->where('e.company_id', $companyId);
            })
            ->whereBetween('d.date', [$rangeStartStr, $rangeEndStr])
            ->where(function ($q) {
                $q->whereNotNull('d.check_in')
                    ->orWhereNotNull('d.check_out')
                    ->orWhereRaw('COALESCE(d.work_hours, 0) > 0');
            })
            ->distinct('d.employee_id')
            ->count('d.employee_id');

        $lateToday = (int) DB::table('attendance_daily as d')
            ->join('employees as e', 'e.id', '=', 'd.employee_id')
            ->when(!$isSuper, function ($q) use ($companyId) {
                $q->where('e.company_id', $companyId);
            })
            ->whereBetween('d.date', [$rangeStartStr, $rangeEndStr])
            ->whereRaw("TIME(d.check_in) > '09:00:00'")
            ->distinct('d.employee_id')
            ->count('d.employee_id');

        $overtimeToday = (int) DB::table('attendance_daily as d')
            ->join('employees as e', 'e.id', '=', 'd.employee_id')
            ->when(!$isSuper, function ($q) use ($companyId) {
                $q->where('e.company_id', $companyId);
            })
            ->whereBetween('d.date', [$rangeStartStr, $rangeEndStr])
            ->where('d.overtime_hours', '>', 0)
            ->distinct('d.employee_id')
            ->count('d.employee_id');

        $absentToday = max(0, $totalEmployees - $presentToday);
        $attendancePct = $totalEmployees > 0 ? round(($presentToday / $totalEmployees) * 100) : 0;

        $presentByDate = [];
        $presentRows = DB::table('attendance_daily as d')
            ->join('employees as e', 'e.id', '=', 'd.employee_id')
            ->when(!$isSuper, function ($q) use ($companyId) {
                $q->where('e.company_id', $companyId);
            })
            ->whereBetween('d.date', [$rangeStartStr, $rangeEndStr])
            ->where(function ($q) {
                $q->whereNotNull('d.check_in')
                  ->orWhereNotNull('d.check_out')
                  ->orWhereRaw('COALESCE(d.work_hours, 0) > 0');
            })
            ->select('d.date', DB::raw('COUNT(DISTINCT d.employee_id) AS present_count'))
            ->groupBy('d.date')
            ->get();
        foreach ($presentRows as $row) {
            $presentByDate[$row->date] = (int) $row->present_count;
        }

        $labels = [];
        $presentSeries = [];
        $presentTotal = 0;
        $cur = clone $rangeStart;
        while ($cur <= $rangeEnd) {
            $d = $cur->format('Y-m-d');
            $labels[] = $cur->format('d/m');
            $present = $presentByDate[$d] ?? 0;
            $presentTotal += $present;
            $presentSeries[] = $present;
            $cur->modify('+1 day');
        }
        $expectedTotal = $totalEmployees * $periodDays;
        $absentTotal = max(0, $expectedTotal - $presentTotal);
        $attendancePctRange = $expectedTotal > 0 ? round(($presentTotal / $expectedTotal) * 100) : 0;

        $overtimeSumRange = (float) DB::table('attendance_daily as d')
            ->join('employees as e', 'e.id', '=', 'd.employee_id')
            ->when(!$isSuper, function ($q) use ($companyId) {
                $q->where('e.company_id', $companyId);
            })
            ->whereBetween('d.date', [$rangeStartStr, $rangeEndStr])
            ->sum('d.overtime_hours');

        $lateCountRange = (int) DB::table('attendance_daily as d')
            ->join('employees as e', 'e.id', '=', 'd.employee_id')
            ->when(!$isSuper, function ($q) use ($companyId) {
                $q->where('e.company_id', $companyId);
            })
            ->whereBetween('d.date', [$rangeStartStr, $rangeEndStr])
            ->whereRaw("TIME(d.check_in) > '09:00:00'")
            ->count();

        $end30 = (clone (new DateTime($today)))->modify('+30 days')->format('Y-m-d');
        $end7 = (clone (new DateTime($today)))->modify('+7 days')->format('Y-m-d');
        $contractSub = DB::table('employee_contracts')
            ->select('employee_id', DB::raw('MAX(end_date) AS end_date'))
            ->whereNotNull('end_date')
            ->groupBy('employee_id');

        $pkwt30Count = (int) DB::query()
            ->fromSub($contractSub, 'c')
            ->join('employees as e', 'e.id', '=', 'c.employee_id')
            ->when(!$isSuper, function ($q) use ($companyId) {
                $q->where('e.company_id', $companyId);
            })
            ->whereBetween('c.end_date', [$today, $end30])
            ->count();

        $pkwt7Count = (int) DB::query()
            ->fromSub($contractSub, 'c')
            ->join('employees as e', 'e.id', '=', 'c.employee_id')
            ->when(!$isSuper, function ($q) use ($companyId) {
                $q->where('e.company_id', $companyId);
            })
            ->whereBetween('c.end_date', [$today, $end7])
            ->count();

        $new30Count = (int) DB::table('employees')
            ->when(!$isSuper, function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->whereNotNull('join_date')
            ->whereBetween('join_date', [(new DateTime($today))->modify('-30 days')->format('Y-m-d'), $today])
            ->count();

        $lastActivity = DB::table('attendance_logs as l')
            ->leftJoin('employees as e', 'e.id', '=', 'l.employee_id')
            ->when(!$isSuper, function ($q) use ($companyId) {
                $q->where('l.company_id', $companyId);
            })
            ->orderByDesc('l.scan_time')
            ->select('l.scan_time', 'e.name')
            ->first();

        $deptRows = DB::table('attendance_daily as d')
            ->join('employees as e', 'e.id', '=', 'd.employee_id')
            ->when(!$isSuper, function ($q) use ($companyId) {
                $q->where('e.company_id', $companyId);
            })
            ->whereBetween('d.date', [$rangeStartStr, $rangeEndStr])
            ->groupBy('e.position')
            ->orderByDesc('ot')
            ->limit(5)
            ->select('e.position as dept', DB::raw('SUM(d.overtime_hours) as ot'))
            ->get();

        $lateDaysExpr = "COUNT(DISTINCT CASE WHEN (d.check_in IS NOT NULL AND TIME(d.check_in) > '09:00:00') THEN d.date END) as late_days";
        if (Schema::hasColumn('attendance_daily', 'late_minutes')) {
            $lateDaysExpr = "COUNT(DISTINCT CASE WHEN (COALESCE(d.late_minutes, 0) > 0 OR (d.late_minutes IS NULL AND d.check_in IS NOT NULL AND TIME(d.check_in) > '09:00:00')) THEN d.date END) as late_days";
        }

        $attendanceRankBase = DB::table('employees as e')
            ->leftJoin('attendance_daily as d', function ($join) use ($rangeStartStr, $rangeEndStr) {
                $join->on('d.employee_id', '=', 'e.id')
                    ->whereBetween('d.date', [$rangeStartStr, $rangeEndStr]);
            })
            ->when(!$isSuper, function ($q) use ($companyId) {
                $q->where('e.company_id', $companyId);
            })
            ->groupBy('e.id', 'e.name', 'e.nik', 'e.position')
            ->select(
                'e.id',
                'e.name',
                'e.nik',
                'e.position',
                DB::raw("COUNT(DISTINCT CASE WHEN (d.check_in IS NOT NULL OR d.check_out IS NOT NULL OR COALESCE(d.work_hours, 0) > 0) THEN d.date END) as present_days"),
                DB::raw($lateDaysExpr)
            );

        $attendanceRankSub = DB::query()->fromSub($attendanceRankBase, 'r');

        $topAttendancePeople = (clone $attendanceRankSub)
            ->orderByDesc('present_days')
            ->orderBy('late_days')
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(function ($row) use ($periodDays) {
                $row->present_days = (int) $row->present_days;
                $row->late_days = (int) $row->late_days;
                $row->absent_days = max(0, $periodDays - $row->present_days);
                return $row;
            });

        $bottomAttendancePeople = (clone $attendanceRankSub)
            ->orderByRaw('(? - r.present_days) DESC', [$periodDays])
            ->orderByDesc('late_days')
            ->orderBy('present_days')
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(function ($row) use ($periodDays) {
                $row->present_days = (int) $row->present_days;
                $row->late_days = (int) $row->late_days;
                $row->absent_days = max(0, $periodDays - $row->present_days);
                return $row;
            });

        $companyHeadcount = DB::table('companies as c')
            ->leftJoin('employees as e', 'e.company_id', '=', 'c.id')
            ->when(!$isSuper, function ($q) use ($companyId) {
                $q->where('c.id', $companyId);
            })
            ->groupBy('c.id', 'c.company_code', 'c.company_name')
            ->orderByDesc('total')
            ->select('c.company_code', 'c.company_name', DB::raw('COUNT(e.id) as total'))
            ->get();

        $attendanceByCompany = DB::table('attendance_daily as d')
            ->join('employees as e', 'e.id', '=', 'd.employee_id')
            ->join('companies as c', 'c.id', '=', 'e.company_id')
            ->when(!$isSuper, function ($q) use ($companyId) {
                $q->where('c.id', $companyId);
            })
            ->whereBetween('d.date', [$rangeStartStr, $rangeEndStr])
            ->where(function ($q) {
                $q->whereNotNull('d.check_in')
                    ->orWhereNotNull('d.check_out')
                    ->orWhereRaw('COALESCE(d.work_hours, 0) > 0');
            })
            ->groupBy('c.id', 'c.company_code', 'c.company_name')
            ->orderByDesc('present_count')
            ->select('c.company_code', 'c.company_name', DB::raw('COUNT(DISTINCT d.employee_id) as present_count'))
            ->get();

        $statusRows = DB::table('employees')
            ->when(!$isSuper, function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->whereNotNull('employment_status')
            ->where('employment_status', '<>', '')
            ->groupBy('employment_status')
            ->orderByDesc('total')
            ->limit(6)
            ->select('employment_status as label', DB::raw('COUNT(*) as total'))
            ->get();

        $typeRows = DB::table('employees')
            ->when(!$isSuper, function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->whereNotNull('employee_type')
            ->where('employee_type', '<>', '')
            ->groupBy('employee_type')
            ->orderByDesc('total')
            ->limit(6)
            ->select('employee_type as label', DB::raw('COUNT(*) as total'))
            ->get();

        $positionRows = DB::table('employees')
            ->when(!$isSuper, function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->whereNotNull('position')
            ->where('position', '<>', '')
            ->groupBy('position')
            ->orderByDesc('total')
            ->limit(6)
            ->select('position as label', DB::raw('COUNT(*) as total'))
            ->get();

        $gradeRows = DB::table('employees')
            ->when(!$isSuper, function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->whereNotNull('grade')
            ->where('grade', '<>', '')
            ->groupBy('grade')
            ->orderByDesc('total')
            ->limit(6)
            ->select('grade as label', DB::raw('COUNT(*) as total'))
            ->get();

        $latestPeriod = DB::table('payroll_period')->orderByDesc('year')->orderByDesc('month')->first();
        $closedPeriod = DB::table('payroll_period')
            ->where('status', 'Closed')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();

        $payrollTotal = 0;
        $payrollByCompany = [];
        if ($closedPeriod) {
            $base = DB::table('payroll as p')
                ->join('employees as e', 'e.id', '=', 'p.employee_id')
                ->join('companies as c', 'c.id', '=', 'e.company_id')
                ->where('p.period_id', $closedPeriod->id)
                ->select('c.company_code', 'c.company_name', DB::raw('SUM(p.gaji_bersih) as total'))
                ->groupBy('c.id', 'c.company_code', 'c.company_name')
                ->orderBy('c.company_code');
            if (!$isSuper) {
                $base->where('c.id', $companyId);
            }
            $payrollByCompany = $base->get();
            $payrollTotal = (float) $payrollByCompany->sum('total');
        }

        return view('modules.tv.index', [
            'user' => $user,
            'today' => $today,
            'rangeStartStr' => $rangeStartStr,
            'rangeEndStr' => $rangeEndStr,
            'labels' => $labels,
            'presentSeries' => $presentSeries,
            'totalEmployees' => $totalEmployees,
            'presentToday' => $presentToday,
            'absentToday' => $absentToday,
            'lateToday' => $lateToday,
            'overtimeToday' => $overtimeToday,
            'attendancePct' => $attendancePct,
            'attendancePctRange' => $attendancePctRange,
            'presentTotal' => $presentTotal,
            'absentTotal' => $absentTotal,
            'overtimeSumRange' => $overtimeSumRange,
            'lateCountRange' => $lateCountRange,
            'pkwt7Count' => $pkwt7Count,
            'pkwt30Count' => $pkwt30Count,
            'new30Count' => $new30Count,
            'lastActivity' => $lastActivity,
            'deptRows' => $deptRows,
            'topAttendancePeople' => $topAttendancePeople,
            'bottomAttendancePeople' => $bottomAttendancePeople,
            'companyHeadcount' => $companyHeadcount,
            'attendanceByCompany' => $attendanceByCompany,
            'statusRows' => $statusRows,
            'typeRows' => $typeRows,
            'positionRows' => $positionRows,
            'gradeRows' => $gradeRows,
            'latestPeriod' => $latestPeriod,
            'closedPeriod' => $closedPeriod,
            'payrollTotal' => $payrollTotal,
            'payrollByCompany' => $payrollByCompany,
        ]);
    }
}
