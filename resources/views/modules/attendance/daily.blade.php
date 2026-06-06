@extends('layouts.app')

@section('content')
<h4 class="mb-3">Rekap Absensi Harian</h4>

@foreach ($messages as $m)
  <div class="alert alert-info">{{ $m }}</div>
@endforeach

@if (!empty($company))
  @php
    $workDays = [];
    if (!empty($company->work_days_json)) {
      $decoded = json_decode($company->work_days_json, true);
      if (is_array($decoded)) {
        $workDays = $decoded;
      }
    }
    $dayLabels = [
      'Mon' => 'Senin',
      'Tue' => 'Selasa',
      'Wed' => 'Rabu',
      'Thu' => 'Kamis',
      'Fri' => 'Jumat',
      'Sat' => 'Sabtu',
      'Sun' => 'Minggu',
    ];
    $workDaysText = [];
    foreach ($workDays as $d) {
      $workDaysText[] = $dayLabels[$d] ?? $d;
    }
  @endphp
  <div class="card shadow-sm mb-3">
    <div class="card-body py-2">
      <div class="row g-2 small">
        <div class="col-md-3"><strong>Jam Kerja:</strong> {{ $company->work_time_start ? format_time_id($company->work_time_start) : '-' }} - {{ $company->work_time_end ? format_time_id($company->work_time_end) : '-' }}</div>
        <div class="col-md-3"><strong>Durasi:</strong> {{ $company->work_duration_hours ?? '-' }} jam</div>
        <div class="col-md-3"><strong>Hari / Minggu:</strong> {{ $company->work_days_per_week ?? '-' }}</div>
        <div class="col-md-3"><strong>Hari Kerja:</strong> {{ count($workDaysText) ? implode(', ', $workDaysText) : '-' }}</div>
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
    <div class="col-md-3">
      <label class="form-label">Tanggal</label>
      <div class="input-group">
        @php
          $prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
          $nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
          $baseParams = ['date' => $date];
          if ($onlyPresent) {
            $baseParams['only_present'] = 1;
          }
          if (!empty($filterEmployee)) {
            $baseParams['q'] = $filterEmployee;
          }
        @endphp
        <a class="btn btn-outline-secondary" href="{{ route('attendance.daily', array_merge($baseParams, ['date' => $prevDate])) }}" title="Hari sebelumnya">&larr;</a>
        <input type="date" class="form-control" name="date" value="{{ $date }}">
        <a class="btn btn-outline-secondary" href="{{ route('attendance.daily', array_merge($baseParams, ['date' => $nextDate])) }}" title="Hari berikutnya">&rarr;</a>
      </div>
    </div>
    <div class="col-md-3">
      <label class="form-label">NIK / Nama</label>
      <input type="text" class="form-control" name="q" value="{{ $filterEmployee ?? '' }}" placeholder="Cari NIK atau nama">
    </div>
    <div class="col-md-3">
      <label class="form-label">Filter</label>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="only_present" value="1" id="onlyPresentDaily" {{ $onlyPresent ? 'checked' : '' }}>
        <label class="form-check-label" for="onlyPresentDaily">Tampilkan hanya yang hadir</label>
      </div>
    </div>
    <div class="col-md-3">
      <button class="btn btn-primary" type="submit">Filter</button>
      <a class="btn btn-outline-secondary" href="{{ route('attendance.daily', ['date' => $date, 'rebuild' => 1]) }}">Rebuild</a>
    </div>
  </div>
</form>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post">
      @csrf
      <input type="hidden" name="action" value="save_daily_verification">
      <input type="hidden" name="date" value="{{ $date }}">
      <input type="hidden" name="only_present" value="{{ $onlyPresent ? '1' : '0' }}">
      <div class="mb-2 text-end">
        <button type="submit" class="btn btn-success btn-sm">Simpan Verifikasi Lembur</button>
      </div>
      <div class="small text-muted mb-2">
        TA-IL : Absensi Lembur Tetapi tidak ada izin Lembur<br>
        SK-SD : Sakit dengan Izin dokter
      </div>
      <table class="table table-striped table-sm">
      <thead>
        <tr>
          <th>NIK</th>
          <th>Nama</th>
          <th>Check In</th>
          <th>Check Out</th>
          <th>Status</th>
          <th>Work Hours</th>
          <th>Overtime</th>
          <th>TA-IL</th>
          @if ($showExcuseCols)
          <th>Cuti</th>
          <th>SK-SD</th>
          <th>Izin Khusus</th>
          @endif
        </tr>
      </thead>
      <tbody>
        @foreach ($daily as $d)
          @php
            $hasAttendance = !empty($d->check_in) || !empty($d->check_out) || (float) ($d->work_hours ?? 0) > 0;
          @endphp
          <tr>
            <td>{{ $d->nik }}</td>
            <td>{{ $d->name }}</td>
            <td>{{ $d->check_in }}</td>
            <td>{{ $d->check_out }}</td>
	            <td>
	              @if ($hasAttendance)
	                <span class="badge text-bg-success">Hadir</span>
              @elseif ((int) ($d->is_sick_doctor_excused ?? 0) === 1)
                <span class="badge text-bg-success">SK-SD</span>
              @elseif ((int) ($d->is_special_leave_excused ?? 0) === 1)
                <span class="badge text-bg-success">Izin Khusus</span>
              @elseif (!empty($isNationalHoliday))
                <span class="badge text-bg-info">Libur Nasional</span>
	              @elseif (!empty($isOffDay))
	                <span class="badge text-bg-warning">Libur</span>
	              @elseif (!empty($absenceStatusMap[$d->employee_id]['is_cuti_bersama']))
	                <span class="badge text-bg-primary">Cuti Bersama</span>
	              @elseif (!empty($absenceStatusMap[$d->employee_id]['request_type']) && $absenceStatusMap[$d->employee_id]['request_type'] === 'Cuti')
	                <span class="badge text-bg-primary">Cuti</span>
	              @elseif (!empty($absenceStatusMap[$d->employee_id]['request_type']) && $absenceStatusMap[$d->employee_id]['request_type'] === 'Izin')
	                <span class="badge text-bg-primary">Izin</span>
	              @else
	                <span class="badge text-bg-secondary">Tidak Hadir</span>
	              @endif
	            </td>
            <td>{{ $d->work_hours }}</td>
            <td>{{ $d->overtime_hours }}</td>
            <td>
              <input type="hidden" name="employee_ids[]" value="{{ $d->employee_id }}">
              @if ($hasAttendance)
                <input class="form-check-input" type="checkbox" name="no_overtime_permit[]" value="{{ $d->employee_id }}" {{ (int) ($d->no_overtime_permit ?? 0) === 1 ? 'checked' : '' }}>
              @else
                -
              @endif
            </td>
            @if ($showExcuseCols)
            <td class="text-center">
              @if (!$hasAttendance)
                <input class="form-check-input js-excuse-leave" type="checkbox" name="is_leave_excused[]" value="{{ $d->employee_id }}" {{ (int) ($d->is_leave_excused ?? 0) === 1 ? 'checked' : '' }}>
              @else
                -
              @endif
            </td>
            <td class="text-center">
              @if (!$hasAttendance)
                <input class="form-check-input js-excuse-sick" type="checkbox" name="is_sick_doctor_excused[]" value="{{ $d->employee_id }}" {{ (int) ($d->is_sick_doctor_excused ?? 0) === 1 ? 'checked' : '' }}>
              @else
                -
              @endif
            </td>
            <td class="text-center">
              @if (!$hasAttendance)
                <input class="form-check-input js-excuse-special" type="checkbox" name="is_special_leave_excused[]" value="{{ $d->employee_id }}" {{ (int) ($d->is_special_leave_excused ?? 0) === 1 ? 'checked' : '' }}>
              @else
                -
              @endif
            </td>
            @endif
          </tr>
        @endforeach
      </tbody>
      </table>
    </form>
  </div>
</div>
<script>
  (function () {
    var leave = document.querySelectorAll('.js-excuse-leave');
    var sick = document.querySelectorAll('.js-excuse-sick');
    var special = document.querySelectorAll('.js-excuse-special');
    if (!leave.length || !sick.length || !special.length) return;

    function bindExclusive(primaryList, secondaryList) {
      primaryList.forEach(function (el) {
        el.addEventListener('change', function () {
          if (!el.checked) return;
          secondaryList.forEach(function (other) {
            if (other.value === el.value) {
              other.checked = false;
            }
          });
        });
      });
    }

    bindExclusive(leave, sick);
    bindExclusive(sick, leave);
    bindExclusive(leave, special);
    bindExclusive(sick, special);
    bindExclusive(special, leave);
    bindExclusive(special, sick);
  })();
</script>
@endsection
