@extends('layouts.app')

@section('content')
<h4 class="mb-3">Rekap Absensi Bulanan</h4>
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
    <div class="col-md-2">
      <label class="form-label">Month</label>
      <input type="number" class="form-control" name="month" min="1" max="12" value="{{ $month }}">
    </div>
    <div class="col-md-2">
      <label class="form-label">Year</label>
      <input type="number" class="form-control" name="year" min="2020" max="2030" value="{{ $year }}">
    </div>
    <div class="col-md-3">
      <label class="form-label">NIK / Nama</label>
      <input type="text" class="form-control" name="q" value="{{ $filterEmployee ?? '' }}" placeholder="Cari NIK atau nama">
    </div>
    <div class="col-md-3">
      <label class="form-label">Filter</label>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="only_present" value="1" id="onlyPresent" {{ $onlyPresent ? 'checked' : '' }}>
        <label class="form-check-label" for="onlyPresent">Tampilkan hanya yang hadir</label>
      </div>
    </div>
    <div class="col-md-3">
      <button class="btn btn-primary" type="submit">Filter</button>
      <a class="btn btn-outline-secondary" href="{{ route('attendance.monthly', ['month' => $month, 'year' => $year, 'rebuild_month' => 1]) }}">Rebuild 1 Bulan</a>
    </div>
  </div>
</form>

<div class="card shadow-sm">
  <div class="card-body">
    <table class="table table-striped table-sm">
      <thead>
        <tr>
          <th>NIK</th>
          <th>Nama</th>
          <th>Hadir (hari)</th>
          <th>Total Jam</th>
          <th>Overtime (jam)</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($rows as $r)
          <tr>
            <td>{{ $r->nik }}</td>
            <td>{{ $r->name }}</td>
            <td>{{ $r->hadir }}</td>
            <td>{{ $r->total_hours }}</td>
            <td>{{ $r->overtime }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection
