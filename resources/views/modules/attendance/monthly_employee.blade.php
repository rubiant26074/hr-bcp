@extends('layouts.app')

@section('content')
<h4 class="mb-3">Rekap Absensi Bulanan Per Employee</h4>
<style>
  .checkin-on-time { color: #14532d !important; font-weight: 700; }
  .checkin-late-mid { color: #fd7e14 !important; font-weight: 700; }
  .checkin-late-high { color: #dc3545 !important; font-weight: 700; }
  .manual-overtime-input { width: 5.5rem; min-width: 5.5rem; }
</style>

@foreach ($messages as $m)
  <div class="alert alert-info">{{ $m }}</div>
@endforeach

@if (!empty($company))
  <div class="card shadow-sm mb-3">
    <div class="card-body py-2">
      <div class="row g-2 small">
        <div class="col-md-3"><strong>Jam Kerja:</strong> {{ $company->work_time_start ? format_time_id($company->work_time_start) : '-' }} - {{ $company->work_time_end ? format_time_id($company->work_time_end) : '-' }}</div>
        <div class="col-md-3"><strong>Durasi:</strong> {{ $company->work_duration_hours ?? '-' }} jam</div>
        <div class="col-md-3"><strong>Hari / Minggu:</strong> {{ $company->work_days_per_week ?? '-' }}</div>
        <div class="col-md-3"><strong>Periode Cut-Off:</strong> {{ $range['label'] ?? '-' }}</div>
      </div>
    </div>
  </div>
@endif

<form class="mb-3" method="get">
  <div class="row g-2 align-items-end">
    @if (current_user_has_global_scope($user))
    <div class="col-md-4">
      <label class="form-label">Company</label>
      <select class="form-select" name="set_company" onchange="this.form.submit()">
        @foreach ($companies as $c)
          <option value="{{ $c->id }}" {{ $companyId == $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
        @endforeach
      </select>
    </div>
    @endif
    <div class="col-md-2">
      <label class="form-label">Month (Closing)</label>
      <input type="number" class="form-control" name="month" min="1" max="12" value="{{ $month }}">
    </div>
    <div class="col-md-2">
      <label class="form-label">Year (Closing)</label>
      <input type="number" class="form-control" name="year" min="2020" max="2035" value="{{ $year }}">
    </div>
    <div class="col-md-4">
      <label class="form-label">Cari NIK / Nama</label>
      <input type="text" class="form-control" name="q" value="{{ $filterEmployee ?? '' }}" placeholder="Filter daftar employee" {{ !empty($isEmployeeRole) ? 'readonly' : '' }}>
    </div>
    <div class="col-md-5">
      <label class="form-label">Employee</label>
      <select class="form-select" name="employee_id" {{ !empty($isEmployeeRole) ? 'disabled' : '' }}>
        @if (empty($isEmployeeRole))
        <option value="">-- Pilih Employee --</option>
        @endif
        @foreach ($employees as $e)
          <option value="{{ $e->id }}" {{ (int) $employeeId === (int) $e->id ? 'selected' : '' }}>{{ $e->nik }} - {{ $e->name }}</option>
        @endforeach
      </select>
      @if (!empty($isEmployeeRole))
        <input type="hidden" name="employee_id" value="{{ (int) $employeeId }}">
        <small class="text-muted">Mode employee: hanya bisa melihat rekap milik sendiri.</small>
      @endif
    </div>
    <div class="col-md-4">
      <button class="btn btn-primary" type="submit">Tampilkan</button>
    </div>
  </div>
</form>

@if ($selectedEmployee)
  <div class="card shadow-sm mb-3">
    <div class="card-body py-2">
      <div class="row g-2 small">
        <div class="col-md-3"><strong>NIK:</strong> {{ $selectedEmployee->nik }}</div>
        <div class="col-md-5"><strong>Nama:</strong> {{ $selectedEmployee->name }}</div>
        <div class="col-md-4"><strong>Periode:</strong> {{ $range['label'] }}</div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <div class="row g-2 small">
        <div class="col-md-2"><strong>Hadir:</strong> {{ $summary['hadir'] }}</div>
        <div class="col-md-2"><strong>Tidak Hadir:</strong> {{ $summary['tidak_hadir'] }}</div>
        <div class="col-md-2"><strong>Cuti:</strong> {{ $summary['cuti'] }}</div>
        <div class="col-md-2"><strong>Izin:</strong> {{ $summary['izin'] }}</div>
        <div class="col-md-2"><strong>Cuti Bersama:</strong> {{ $summary['cuti_bersama'] }}</div>
        <div class="col-md-2"><strong>Libur Mingguan:</strong> {{ $summary['libur_mingguan'] }}</div>
        <div class="col-md-2"><strong>Libur Nasional:</strong> {{ $summary['libur_nasional'] }}</div>
        <div class="col-md-2"><strong>Total Jam Kerja:</strong> {{ number_format((float) ($summary['work_hours'] ?? 0), 2, ',', '.') }}</div>
        <div class="col-md-2"><strong>Total Lembur:</strong> {{ number_format((float) ($summary['overtime_hours'] ?? 0), 2, ',', '.') }}</div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post">
        @csrf
        <input type="hidden" name="action" value="save_monthly_verification">
        <input type="hidden" name="month" value="{{ $month }}">
        <input type="hidden" name="year" value="{{ $year }}">
        <input type="hidden" name="employee_id" value="{{ (int) ($selectedEmployee->id ?? 0) }}">
        <input type="hidden" name="q" value="{{ $filterEmployee ?? '' }}">
        @if (empty($isEmployeeRole))
        <div class="mb-2 text-end">
          <button type="submit" class="btn btn-success btn-sm">Simpan Verifikasi</button>
        </div>
        @endif
      <div class="small text-muted mb-2">
        TA-IL : Absensi Lembur Tetapi tidak ada izin Lembur<br>
        SK-SD : Sakit dengan Izin dokter
        @if (!empty($canEditManualOvertime) && empty($hasManualOvertimeColumns))
          <br>Input manual lembur membutuhkan migration terbaru.
        @endif
      </div>
      <div class="small mb-2">
        @php
          $positionName = strtoupper(trim((string) ($selectedEmployee->position ?? '')));
          $isSecurityShift =
            !str_contains($positionName, 'KEPALA SECURITY')
            && !str_contains($positionName, 'KEPALA SCURITY')
            && (str_contains($positionName, 'SECURITY') || str_contains($positionName, 'SCURITY') || str_contains($positionName, 'SATPAM'));
        @endphp
        @if ($isSecurityShift)
          <span class="badge checkin-on-time">Security (2P-2S-2M-2OFF, efektif 28/04/2026): tidak terlambat (<= +5 menit dari jam shift)</span>
          <span class="badge checkin-late-mid">Security: terlambat +5 s/d +15 menit</span>
          <span class="badge checkin-late-high">Security: terlambat > +15 menit</span>
        @else
          <span class="badge checkin-on-time">Tidak terlambat (<= 08:05)</span>
          <span class="badge checkin-late-mid">Terlambat 08:05 - 08:15</span>
          <span class="badge checkin-late-high">Terlambat > 08:15</span>
        @endif
      </div>
      <div class="table-responsive">
        <table class="table table-striped table-sm align-middle">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Hari</th>
              <th>Check In</th>
              <th>Check Out</th>
              <th>Status</th>
              <th>Jam Kerja</th>
              <th>Lembur</th>
              <th>
                <div class="d-flex align-items-center gap-1">
                  @if (empty($isEmployeeRole))
                    <input class="form-check-input mt-0" type="checkbox" id="ta-il-check-all" title="Pilih semua TA-IL">
                  @endif
                  <span>TA-IL</span>
                </div>
              </th>
              <th>Cuti</th>
              <th>SK-SD</th>
              <th>Izin Khusus</th>
              <th>Keterangan</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($rows as $r)
              @php
                $checkInClass = '';
                $shiftCode = (string) ($r->security_shift_code ?? '');
                if (!empty($r->check_in)) {
                    $checkInTime = date('H:i:s', strtotime((string) $r->check_in));
                    if (!empty($isSecurityShift)) {
                        $shiftStart = trim((string) ($r->security_shift_start ?? ''));
                        if ($shiftCode !== 'OFF' && $shiftStart !== '') {
                            $checkInSec = ((int) date('H', strtotime((string) $r->check_in)) * 3600)
                                + ((int) date('i', strtotime((string) $r->check_in)) * 60)
                                + (int) date('s', strtotime((string) $r->check_in));
                            $shiftTs = strtotime('1970-01-01 ' . $shiftStart);
                            $shiftStartSec = ((int) date('H', $shiftTs) * 3600)
                                + ((int) date('i', $shiftTs) * 60)
                                + (int) date('s', $shiftTs);
                            $deltaSec = $checkInSec - $shiftStartSec;
                            if ($deltaSec < -43200) {
                                $deltaSec += 86400;
                            } elseif ($deltaSec > 43200) {
                                $deltaSec -= 86400;
                            }
                            $lateSec = max(0, $deltaSec);

                            if ($lateSec <= 5 * 60) {
                                $checkInClass = 'checkin-on-time';
                            } elseif ($lateSec <= 15 * 60) {
                                $checkInClass = 'checkin-late-mid';
                            } else {
                                $checkInClass = 'checkin-late-high';
                            }
                        }
                    } elseif ($checkInTime <= '08:05:00') {
                        $checkInClass = 'checkin-on-time';
                    } elseif ($checkInTime <= '08:15:00') {
                        $checkInClass = 'checkin-late-mid';
                    } else {
                        $checkInClass = 'checkin-late-high';
                    }
                }
              @endphp
              <tr>
                <td>
                  <input type="hidden" name="row_dates[]" value="{{ $r->date }}">
                  @if (!empty($r->display_date) && str_contains((string) $r->display_date, ' - '))
                    @php [$d1, $d2] = explode(' - ', (string) $r->display_date, 2); @endphp
                    {{ format_date_id($d1) }} - {{ format_date_id($d2) }}
                  @else
                    {{ format_date_id($r->date) }}
                  @endif
                </td>
                <td>
                  {{ $r->day_name }}
                </td>
                <td class="{{ $checkInClass }}">{{ $r->check_in ? format_datetime_id($r->check_in) : '-' }}</td>
                <td>{{ $r->check_out ? format_datetime_id($r->check_out) : '-' }}</td>
                <td>
                  @if (($r->status ?? '') === 'OFF')
                    <span class="badge text-bg-warning">OFF</span>
                  @elseif (($r->status ?? '') === 'Perlu Konfirmasi')
                    <span class="badge text-bg-danger">Perlu Konfirmasi</span>
                  @elseif ((int) ($r->is_sick_doctor_excused ?? 0) === 1)
                    <span class="badge text-bg-success">SK-SD</span>
                  @elseif ((int) ($r->is_special_leave_excused ?? 0) === 1)
                    <span class="badge text-bg-success">Izin Khusus</span>
                  @elseif ($r->status === 'Hadir')
                    <span class="badge text-bg-success">Hadir</span>
                  @elseif ($r->status === 'Cuti Bersama')
                    <span class="badge text-bg-primary">Cuti Bersama</span>
                  @elseif ($r->status === 'Cuti')
                    <span class="badge text-bg-primary">Cuti</span>
                  @elseif ($r->status === 'Izin Cuti')
                    <span class="badge text-bg-primary">Izin Cuti</span>
                  @elseif ($r->status === 'Izin')
                    <span class="badge text-bg-primary">Izin</span>
                  @elseif ($r->status === 'Libur Nasional')
                    <span class="badge text-bg-info">Libur Nasional</span>
                  @elseif ($r->status === 'Libur Mingguan')
                    <span class="badge text-bg-warning">Libur</span>
                  @else
                    <span class="badge text-bg-secondary">Tidak Hadir</span>
                  @endif
                </td>
                <td>{{ number_format((float) ($r->work_hours ?? 0), 2, ',', '.') }}</td>
                <td>
                  @if (!empty($canEditManualOvertime) && !empty($hasManualOvertimeColumns) && !empty($r->has_attendance))
                    <input
                      type="number"
                      class="form-control form-control-sm manual-overtime-input"
                      name="manual_overtime_hours[{{ $r->date }}]"
                      min="0"
                      step="0.01"
                      value="{{ number_format((float) ($r->overtime_hours ?? 0), 2, '.', '') }}"
                      aria-label="Jam lembur {{ format_date_id($r->date) }}"
                    >
                    @if (!empty($r->overtime_hours_is_manual))
                      <span class="badge text-bg-info mt-1">Manual</span>
                    @endif
                  @else
                    {{ number_format((float) ($r->overtime_hours ?? 0), 2, ',', '.') }}
                  @endif
                </td>
                <td>
                  @if (!empty($r->has_attendance) && empty($isEmployeeRole))
                    <input class="form-check-input js-ta-il" type="checkbox" name="no_overtime_permit_dates[]" value="{{ $r->date }}" {{ (int) ($r->no_overtime_permit ?? 0) === 1 ? 'checked' : '' }}>
                  @elseif (!empty($r->has_attendance))
                    {{ (int) ($r->no_overtime_permit ?? 0) === 1 ? 'Ya' : '-' }}
                  @else
                    -
                  @endif
                </td>
                <td class="text-center">
                  @if (empty($r->has_attendance) && empty($isEmployeeRole))
                    <input class="form-check-input js-excuse-leave" type="checkbox" data-date="{{ $r->date }}" name="is_leave_excused_dates[]" value="{{ $r->date }}" {{ (int) ($r->is_leave_excused ?? 0) === 1 ? 'checked' : '' }}>
                  @elseif (empty($r->has_attendance))
                    {{ (int) ($r->is_leave_excused ?? 0) === 1 ? 'Ya' : '-' }}
                  @else
                    -
                  @endif
                </td>
                <td class="text-center">
                  @if (empty($r->has_attendance) && empty($isEmployeeRole))
                    <input class="form-check-input js-excuse-sick" type="checkbox" data-date="{{ $r->date }}" name="is_sick_doctor_excused_dates[]" value="{{ $r->date }}" {{ (int) ($r->is_sick_doctor_excused ?? 0) === 1 ? 'checked' : '' }}>
                  @elseif (empty($r->has_attendance))
                    {{ (int) ($r->is_sick_doctor_excused ?? 0) === 1 ? 'Ya' : '-' }}
                  @else
                    -
                  @endif
                </td>
                <td class="text-center">
                  @if (empty($r->has_attendance) && empty($isEmployeeRole))
                    <input class="form-check-input js-excuse-special" type="checkbox" data-date="{{ $r->date }}" name="is_special_leave_excused_dates[]" value="{{ $r->date }}" {{ (int) ($r->is_special_leave_excused ?? 0) === 1 ? 'checked' : '' }} {{ (!empty($r->is_special_leave_excused) && empty($canUnlockSpecialLeave)) ? 'disabled' : '' }}>
                    @if (!empty($r->is_special_leave_excused) && empty($canUnlockSpecialLeave))
                      <input type="hidden" name="is_special_leave_excused_dates[]" value="{{ $r->date }}">
                    @endif
                  @elseif (empty($r->has_attendance))
                    {{ (int) ($r->is_special_leave_excused ?? 0) === 1 ? 'Ya' : '-' }}
                  @else
                    -
                  @endif
                </td>
                <td>{{ $r->holiday_name ?? '-' }}</td>
              </tr>
            @empty
              <tr><td colspan="12" class="text-center text-muted">Tidak ada data untuk periode ini.</td></tr>
            @endforelse
          </tbody>
          @if (!empty($rows) && count($rows) > 0)
          <tfoot>
            <tr class="table-light fw-semibold">
              <td colspan="5" class="text-end">Total</td>
              <td>{{ number_format((float) ($summary['work_hours'] ?? 0), 2, ',', '.') }}</td>
              <td>{{ number_format((float) ($summary['overtime_hours'] ?? 0), 2, ',', '.') }}</td>
              <td colspan="5"></td>
            </tr>
          </tfoot>
          @endif
        </table>
      </div>
      </form>
    </div>
  </div>
@else
  <div class="alert alert-warning">Pilih employee untuk melihat rekap absensi periode cut-off 21 sampai 20.</div>
@endif
<script>
  (function () {
    if ({{ !empty($isEmployeeRole) ? 'true' : 'false' }}) return;
    var taIlAll = document.getElementById('ta-il-check-all');
    var taIlItems = Array.prototype.slice.call(document.querySelectorAll('.js-ta-il'));
    var leave = document.querySelectorAll('.js-excuse-leave');
    var sick = document.querySelectorAll('.js-excuse-sick');
    var special = document.querySelectorAll('.js-excuse-special');

    function syncTaIlHeader() {
      if (!taIlAll || !taIlItems.length) return;
      var checkedCount = taIlItems.filter(function (el) { return el.checked; }).length;
      taIlAll.checked = checkedCount > 0 && checkedCount === taIlItems.length;
      taIlAll.indeterminate = checkedCount > 0 && checkedCount < taIlItems.length;
    }

    if (taIlAll && taIlItems.length) {
      taIlAll.addEventListener('change', function () {
        taIlItems.forEach(function (el) {
          el.checked = taIlAll.checked;
        });
        syncTaIlHeader();
      });
      taIlItems.forEach(function (el) {
        el.addEventListener('change', syncTaIlHeader);
      });
      syncTaIlHeader();
    }

    if (!leave.length || !sick.length || !special.length) return;

    function toggleByDate(source, targetList) {
      source.addEventListener('change', function () {
        if (!source.checked) return;
        var d = source.getAttribute('data-date');
        targetList.forEach(function (other) {
          if (other.getAttribute('data-date') === d) {
            if (other.disabled) return;
            other.checked = false;
          }
        });
      });
    }

    leave.forEach(function (el) { toggleByDate(el, sick); });
    sick.forEach(function (el) { toggleByDate(el, leave); });
    leave.forEach(function (el) { toggleByDate(el, special); });
    sick.forEach(function (el) { toggleByDate(el, special); });
    special.forEach(function (el) { toggleByDate(el, leave); });
    special.forEach(function (el) { toggleByDate(el, sick); });
  })();
</script>
@endsection
