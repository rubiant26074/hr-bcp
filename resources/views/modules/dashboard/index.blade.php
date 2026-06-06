@extends('layouts.app')

@section('content')
@php
  $txt = function (string $key) use ($dashboardLabels) {
      return $dashboardLabels[$key] ?? $key;
  };
  $heroSub = str_replace(':companies', $companyCodeSummary ?: '-', $txt('hero.sub_template'));
  $monthFromRangeEnd = (int) date('n', strtotime($rangeEndStr));
  $yearFromRangeEnd = (int) date('Y', strtotime($rangeEndStr));
  $employeeIndexUrl = route('employees.index');
  $attendanceDailyUrl = route('attendance.daily', ['date' => $today]);
  $attendanceLogsRangeUrl = route('attendance.logs', ['date_from' => $rangeStartStr, 'date_to' => $rangeEndStr]);
  $attendanceMonthlyUrl = route('attendance.monthly', ['month' => $monthFromRangeEnd, 'year' => $yearFromRangeEnd]);
  $attendanceMonthlyEmployeeUrl = route('attendance.monthly_employee', ['month' => $monthFromRangeEnd, 'year' => $yearFromRangeEnd]);
  $permissionsAbsenceUrl = route('permissions.absence');
  $permissionsOvertimeUrl = route('permissions.overtime');
  $contractsUrl = route('contracts.index');
@endphp
<div class="hero-card mb-3">
  <div class="hero-left">
    <div class="hero-kicker">{{ $txt('hero.kicker') }}</div>
    <div class="hero-title">{{ $txt('hero.title') }}</div>
    <div class="hero-sub">{{ $heroSub }}</div>
  </div>
  <div class="hero-tags">
    <span class="tag-pill">{{ $txt('hero.tag.mobile_desktop') }}</span>
    <span class="tag-pill">{{ $txt('hero.tag.stack') }}</span>
  </div>
</div>

<form class="mb-3" method="get">
  <div class="row g-2 align-items-end">
    @if (current_user_has_global_scope($user))
    <div class="col-md-3">
      <label class="form-label">{{ $txt('filter.company') }}</label>
      <select class="form-select" name="set_company" onchange="this.form.submit()">
        @foreach ($companies as $c)
          <option value="{{ $c->id }}" {{ current_company_id() == $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
        @endforeach
      </select>
    </div>
    @endif
    <div class="col-md-3">
      <label class="form-label">{{ $txt('filter.range') }}</label>
      <select class="form-select" name="range" onchange="this.form.submit()">
        <option value="day" {{ $range === 'day' ? 'selected' : '' }}>{{ $txt('filter.range.day') }}</option>
        <option value="week" {{ $range === 'week' ? 'selected' : '' }}>{{ $txt('filter.range.week') }}</option>
        <option value="month" {{ $range === 'month' ? 'selected' : '' }}>{{ $txt('filter.range.month') }}</option>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">{{ $txt('filter.period') }}</label>
      <div class="form-control bg-white">{{ format_date_id($rangeStartStr) }} - {{ format_date_id($rangeEndStr) }}</div>
    </div>
    <div class="col-md-5">
      <label class="form-label">{{ $txt('filter.date_range') }}</label>
      <div class="d-flex gap-2">
        <input type="date" class="form-control" name="from" value="{{ $customFromDb ?? '' }}" autocomplete="off">
        <input type="date" class="form-control" name="to" value="{{ $customToDb ?? '' }}" autocomplete="off">
        <button class="btn btn-outline-secondary" type="submit">{{ $txt('filter.apply') }}</button>
      </div>
    </div>
  </div>
</form>

<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="metric-neo">
      <div class="metric-neo-top">
        <div class="metric-neo-value"><a class="dash-number-link" href="{{ $employeeIndexUrl }}">{{ $totalEmployees }}</a></div>
        <div class="metric-neo-label">{{ $txt('metric.total_employees') }}</div>
      </div>
      <div class="metric-neo-foot">{{ $txt('metric.total_employees_foot') }}</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="metric-neo">
      <div class="metric-neo-top">
        <div class="metric-neo-value"><a class="dash-number-link" href="{{ $attendanceDailyUrl }}">{{ $attendancePct }}%</a></div>
        <div class="metric-neo-label">{{ $txt('metric.attendance_today') }}</div>
      </div>
      <div class="metric-neo-foot">{{ $txt('metric.attendance_today_foot') }}</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="metric-neo light">
      <div class="metric-neo-top">
        <div class="metric-neo-value"><a class="dash-number-link" href="{{ $attendanceLogsRangeUrl }}">{{ $lateCount }}</a></div>
        <div class="metric-neo-label">{{ $txt('metric.late_range') }} ({{ strtoupper($range) }})</div>
      </div>
      <div class="metric-neo-foot">{{ $txt('metric.late_range_foot') }}</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="metric-neo">
      <div class="metric-neo-top">
        <div class="metric-neo-value"><a class="dash-number-link" href="{{ $attendanceMonthlyUrl }}">{{ number_format($overtimeSum, 2) }}</a></div>
        <div class="metric-neo-label">{{ $txt('metric.overtime_range') }} ({{ strtoupper($range) }})</div>
      </div>
      <div class="metric-neo-foot">{{ $txt('metric.overtime_range_foot') }}</div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-8">
    <div class="panel-card">
      <div class="panel-header">{{ $txt('panel.trend') }} ({{ strtoupper($range) }})</div>
      <div class="panel-body">
        <canvas id="attendanceTrend" height="110"></canvas>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="panel-card">
      <div class="panel-header">{{ $txt('panel.composition') }}</div>
      <div class="panel-body">
        <canvas id="attendanceDonut" height="170"></canvas>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-4">
    <div class="panel-card">
      <div class="panel-header">{{ $txt('panel.attendance_summary') }}</div>
      <div class="panel-body">
        <div class="kv"><span>{{ $txt('common.present') }}</span><strong><a class="dash-inline-link" href="{{ $attendanceDailyUrl }}">{{ $hadir }}</a></strong></div>
        <div class="kv"><span>{{ $txt('common.late') }}</span><strong><a class="dash-inline-link" href="{{ $attendanceLogsRangeUrl }}">{{ $terlambat }}</a></strong></div>
        <div class="kv"><span>{{ $txt('common.absent') }}</span><strong><a class="dash-inline-link" href="{{ $attendanceMonthlyEmployeeUrl }}">{{ $tidakHadir }}</a></strong></div>
        <div class="kv"><span>{{ $txt('common.overtime_today') }}</span><strong><a class="dash-inline-link" href="{{ $permissionsOvertimeUrl }}">{{ $lemburHariIni }}</a></strong></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="panel-card">
      <div class="panel-header">{{ $txt('panel.approval_queue') }}</div>
      <div class="panel-body">
        <div class="kv"><span>{{ $txt('common.leave') }}</span><strong><a class="dash-inline-link" href="{{ $permissionsAbsenceUrl }}">{{ $cuti }}</a></strong></div>
        <div class="kv"><span>{{ $txt('common.permission') }}</span><strong><a class="dash-inline-link" href="{{ $permissionsAbsenceUrl }}">{{ $izin }}</a></strong></div>
        <div class="kv"><span>{{ $txt('common.overtime') }}</span><strong><a class="dash-inline-link" href="{{ $permissionsOvertimeUrl }}">{{ $lemburHariIni }}</a></strong></div>
        <div class="kv"><span>{{ $txt('common.reimbursement') }}</span><strong>{{ $reimburse }}</strong></div>
        <div class="kv"><span>{{ $txt('common.payroll_report') }}</span><strong><a class="dash-inline-link" href="{{ route('payroll.report_approval') }}">{{ $payrollReportPendingCount }}</a></strong></div>
        <div class="kv"><span>{{ $txt('common.payroll_pph21') }}</span><strong><a class="dash-inline-link" href="{{ route('payroll.pph21_approval') }}">{{ $payrollPph21PendingCount }}</a></strong></div>
        <div class="mt-2">
          <a class="btn btn-outline-primary btn-sm" href="{{ route('payroll.report_approval') }}">{{ $txt('common.open_approval_report') }}</a>
          <a class="btn btn-outline-primary btn-sm" href="{{ route('payroll.pph21_approval') }}">{{ $txt('common.open_approval_pph21') }}</a>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="panel-card">
      <div class="panel-header">{{ $txt('panel.contract') }}</div>
      <div class="panel-body">
        <div class="kv"><span>{{ $txt('common.pkwt_30') }}</span><strong><a class="dash-inline-link" href="{{ $contractsUrl }}">{{ $pkwt30Count }}</a></strong></div>
        <div class="kv"><span>{{ $txt('common.pkwt_7') }}</span><strong><a class="dash-inline-link" href="{{ $contractsUrl }}">{{ $pkwt7Count }}</a></strong></div>
        <div class="kv"><span>{{ $txt('common.new_employee_30') }}</span><strong><a class="dash-inline-link" href="{{ $employeeIndexUrl }}">{{ $new30Count }}</a></strong></div>
        <div class="kv"><span>{{ $txt('common.mutation_company') }}</span><strong><a class="dash-inline-link" href="{{ $employeeIndexUrl }}?view=mutasi">{{ $mutasi }}</a></strong></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-md-4">
    <div class="panel-card">
      <div class="panel-header">{{ $txt('panel.monitoring') }}</div>
      <div class="panel-body">
        <div class="kv"><span>{{ $txt('common.hr_notifications') }}</span><strong>{{ $notif }}</strong></div>
        <div class="kv"><span>{{ $txt('common.unread') }}</span><strong>{{ $unread }}</strong></div>
        <div class="kv"><span>{{ $txt('common.workflow_7days') }}</span><strong>{{ $workflow7 }}</strong></div>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="panel-card">
      <div class="panel-header d-flex justify-content-between">
        <span>{{ $txt('panel.activity') }}</span>
        <span class="text-muted small">{{ $txt('panel.activity_subtitle') }}</span>
      </div>
      <div class="panel-body">
        @if ($lastActivity)
          <div class="fw-semibold">{{ $txt('common.activity_hr') }}</div>
          <div class="small text-muted">{{ format_datetime_id($lastActivity->scan_time) }}</div>
          <div class="small text-muted">{{ $lastActivity->name ?? '-' }} - {{ $txt('common.activity_logged_suffix') }}</div>
        @else
          <div class="small text-muted">{{ $txt('common.empty_activity') }}</div>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-md-6">
    <div class="panel-card">
      <div class="panel-header d-flex justify-content-between">
        <span>5 Personel Terbaik Absensi</span>
        <span class="text-muted small">{{ format_date_id($rangeStartStr) }} - {{ format_date_id($rangeEndStr) }}</span>
      </div>
      <div class="panel-body">
        @if ($topAttendancePeople->isEmpty())
          <div class="small text-muted">Belum ada data absensi.</div>
        @else
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead>
                <tr>
                  <th>Nama</th>
                  <th>Jabatan</th>
                  <th class="text-end">Hadir</th>
                  <th class="text-end">Terlambat < 15m</th>
                  <th class="text-end">Terlambat >= 15m</th>
                  <th class="text-end">Tidak Hadir</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($topAttendancePeople as $row)
                  <tr>
                    <td>{{ $row->name ?: '-' }}</td>
                    <td>{{ $row->position ?: '-' }}</td>
                    <td class="text-end">{{ $row->present_days }}</td>
                    <td class="text-end">{{ $row->present_days > 0 ? $row->late_lt_15_days : '-' }}</td>
                    <td class="text-end">{{ $row->present_days > 0 ? $row->late_gte_15_days : '-' }}</td>
                    <td class="text-end">{{ $row->absent_days }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="panel-card">
      <div class="panel-header d-flex justify-content-between">
        <span>5 Personel Terburuk Absensi</span>
        <span class="text-muted small">{{ format_date_id($rangeStartStr) }} - {{ format_date_id($rangeEndStr) }}</span>
      </div>
      <div class="panel-body">
        @if ($bottomAttendancePeople->isEmpty())
          <div class="small text-muted">Belum ada data absensi.</div>
        @else
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead>
                <tr>
                  <th>Nama</th>
                  <th>Jabatan</th>
                  <th class="text-end">Tidak Hadir</th>
                  <th class="text-end">Terlambat < 15m</th>
                  <th class="text-end">Terlambat >= 15m</th>
                  <th class="text-end">Hadir</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($bottomAttendancePeople as $row)
                  <tr>
                    <td>{{ $row->name ?: '-' }}</td>
                    <td>{{ $row->position ?: '-' }}</td>
                    <td class="text-end">{{ $row->absent_days }}</td>
                    <td class="text-end">{{ $row->present_days > 0 ? $row->late_lt_15_days : '-' }}</td>
                    <td class="text-end">{{ $row->present_days > 0 ? $row->late_gte_15_days : '-' }}</td>
                    <td class="text-end">{{ $row->present_days }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-md-12">
    <div class="panel-card">
      <div class="panel-header d-flex justify-content-between">
        <span>{{ $txt('panel.pending_payroll_report') }}</span>
        <span class="text-muted small">{{ $txt('common.top5') }}</span>
      </div>
      <div class="panel-body">
        @if ($payrollReportPendingList->isEmpty())
          <div class="small text-muted">{{ $txt('common.no_pending_payroll_report') }}</div>
        @else
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead>
                <tr>
                  <th>{{ $txt('table.period') }}</th>
                  <th>{{ $txt('table.requester') }}</th>
                  <th>{{ $txt('table.step') }}</th>
                  <th class="text-end">{{ $txt('table.action') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($payrollReportPendingList as $row)
                  @php
                    $p = $payrollReportPeriodMap[$row->period_id] ?? null;
                    $req = $payrollReportRequesterMap[$row->requester_user_id] ?? null;
                  @endphp
                  <tr>
                    <td>{{ $p ? ($p->month . '/' . $p->year) : $row->period_id }}</td>
                    <td>{{ $req ? ($req->name . ' (' . $req->email . ')') : ($row->requester_user_id ?? '-') }}</td>
                    <td>{{ $row->step_no ? ($txt('common.step_prefix') . ' ' . $row->step_no) : '-' }}</td>
                    <td class="text-end">
                      <form method="post" action="{{ route('payroll.report_approval', ['period_id' => $row->period_id]) }}" class="d-inline">
                        @csrf
                        <input type="hidden" name="id" value="{{ $row->id }}">
                        <button class="btn btn-success btn-sm" name="action" value="approve_step" type="submit">{{ $txt('button.approve') }}</button>
                      </form>
                      <form method="post" action="{{ route('payroll.report_approval', ['period_id' => $row->period_id]) }}" class="d-inline" onsubmit="return confirm('{{ $txt('confirm.reject_payroll_report') }}');">
                        @csrf
                        <input type="hidden" name="id" value="{{ $row->id }}">
                        <input type="hidden" name="note" value="">
                        <button class="btn btn-outline-danger btn-sm" name="action" value="reject" type="submit">{{ $txt('button.reject') }}</button>
                      </form>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-md-12">
    <div class="panel-card">
      <div class="panel-header d-flex justify-content-between">
        <span>{{ $txt('panel.pending_payroll_pph21') }}</span>
        <span class="text-muted small">{{ $txt('common.top5') }}</span>
      </div>
      <div class="panel-body">
        @if ($payrollPph21PendingList->isEmpty())
          <div class="small text-muted">{{ $txt('common.no_pending_payroll_pph21') }}</div>
        @else
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead>
                <tr>
                  <th>{{ $txt('table.period') }}</th>
                  <th>{{ $txt('table.requester') }}</th>
                  <th>{{ $txt('table.step') }}</th>
                  <th class="text-end">{{ $txt('table.action') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($payrollPph21PendingList as $row)
                  @php
                    $p = $payrollPph21PeriodMap[$row->period_id] ?? null;
                    $req = $payrollPph21RequesterMap[$row->requester_user_id] ?? null;
                  @endphp
                  <tr>
                    <td>{{ $p ? ($p->month . '/' . $p->year) : $row->period_id }}</td>
                    <td>{{ $req ? ($req->name . ' (' . $req->email . ')') : ($row->requester_user_id ?? '-') }}</td>
                    <td>{{ $row->step_no ? ($txt('common.step_prefix') . ' ' . $row->step_no) : '-' }}</td>
                    <td class="text-end">
                      <form method="post" action="{{ route('payroll.pph21_approval', ['period_id' => $row->period_id]) }}" class="d-inline">
                        @csrf
                        <input type="hidden" name="id" value="{{ $row->id }}">
                        <button class="btn btn-success btn-sm" name="action" value="approve_step" type="submit">{{ $txt('button.approve') }}</button>
                      </form>
                      <form method="post" action="{{ route('payroll.pph21_approval', ['period_id' => $row->period_id]) }}" class="d-inline" onsubmit="return confirm('{{ $txt('confirm.reject_payroll_pph21') }}');">
                        @csrf
                        <input type="hidden" name="id" value="{{ $row->id }}">
                        <input type="hidden" name="note" value="">
                        <button class="btn btn-outline-danger btn-sm" name="action" value="reject" type="submit">{{ $txt('button.reject') }}</button>
                      </form>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-md-6">
    <div class="panel-card">
      <div class="panel-header d-flex justify-content-between">
        <span>{{ $txt('panel.top5_department_ot') }}</span>
        <span class="text-muted small">{{ format_date_id($rangeStartStr) }} - {{ format_date_id($rangeEndStr) }}</span>
      </div>
      <div class="panel-body">
        @if (count($deptRows) === 0)
          <div class="small text-muted">{{ $txt('common.no_overtime_data') }}</div>
        @else
          <table class="table table-sm mb-0">
            <thead>
              <tr>
                <th>{{ $txt('table.department') }}</th>
                <th class="text-end">{{ $txt('table.overtime_hours') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($deptRows as $r)
                <tr>
                  <td>{{ $r->dept ?: '-' }}</td>
                  <td class="text-end">{{ number_format((float) $r->ot, 2) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @endif
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="panel-card">
      <div class="panel-header d-flex justify-content-between">
        <span>{{ $txt('panel.payroll_cost') }}</span>
        <span class="text-muted small">{{ $closedPeriod ? $txt('common.latest_finalized_period') : $txt('common.no_finalized_period') }}</span>
      </div>
      <div class="panel-body">
        @if (!$closedPeriod || count($payrollByCompany) === 0)
          <div class="small text-muted">{{ $txt('common.no_payroll_data') }}</div>
        @else
          @php
            $max = 0;
            foreach ($payrollByCompany as $r) {
                $max = max($max, (float) $r->total);
            }
          @endphp
          @foreach ($payrollByCompany as $r)
            @php $pct = $max > 0 ? ((float) $r->total / $max) * 100 : 0; @endphp
            <div class="bar-row">
              <div class="bar-label">{{ $r->company_code }}</div>
              <div class="bar-track"><div class="bar-fill" style="width: {{ $pct }}%"></div></div>
              <div class="bar-value">{{ format_currency($r->total) }}</div>
            </div>
          @endforeach
        @endif
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
  .dash-number-link,
  .dash-inline-link {
    color: inherit;
    text-decoration: none;
    border-bottom: 1px dashed rgba(255, 255, 255, 0.35);
  }
  .dash-number-link:hover,
  .dash-inline-link:hover {
    text-decoration: underline;
  }
  .metric-neo.light .dash-number-link {
    border-bottom-color: rgba(15, 23, 42, 0.35);
  }
</style>
<script>
  (function () {
    var trendCtx = document.getElementById('attendanceTrend');
    if (trendCtx) {
      new Chart(trendCtx, {
        type: 'line',
        data: {
          labels: {!! json_encode($labels) !!},
          datasets: [
            {
              label: @json($txt('chart.present')),
              data: {!! json_encode($presentSeries) !!},
              backgroundColor: 'rgba(32, 163, 158, 0.2)',
              borderColor: 'rgba(32, 163, 158, 1)',
              borderWidth: 2,
              tension: 0.3,
              fill: true
            },
            {
              label: @json($txt('chart.absent')),
              data: {!! json_encode($absentSeries) !!},
              backgroundColor: 'rgba(239, 68, 68, 0.15)',
              borderColor: 'rgba(239, 68, 68, 1)',
              borderWidth: 2,
              tension: 0.3,
              fill: true
            }
          ]
        },
        options: {
          responsive: true,
          scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
      });
    }

    var donutCtx = document.getElementById('attendanceDonut');
    if (donutCtx) {
      new Chart(donutCtx, {
        type: 'doughnut',
        data: {
          labels: [@json($txt('chart.present')), @json($txt('chart.absent'))],
          datasets: [{
            data: [{{ (int) $presentTotal }}, {{ (int) $absentTotal }}],
            backgroundColor: ['rgba(34,197,94,0.75)', 'rgba(239,68,68,0.6)'],
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { position: 'bottom' }
          }
        }
      });
    }
  })();
</script>
@endsection
