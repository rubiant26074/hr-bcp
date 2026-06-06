@extends('layouts.app')

@section('content')
<h4 class="mb-3">Periode Payroll</h4>
@if (request()->query('ok'))
  <div class="alert alert-success">Periode payroll berhasil dibuat.</div>
@endif
@if (request()->query('updated'))
  <div class="alert alert-success">Periode payroll berhasil diupdate.</div>
@endif
@foreach ($messages as $m)
  <div class="alert alert-info">{{ $m }}</div>
@endforeach

<div class="d-flex gap-2 flex-wrap mb-3">
  <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#payrollPeriodForm" aria-expanded="false" aria-controls="payrollPeriodForm">
    {{ $edit ? 'Edit Periode' : 'Buat Periode' }}
  </button>
  <a class="btn btn-outline-secondary" href="{{ route('payroll.period') }}">Reset</a>
  @if ($edit)
    <a class="btn btn-outline-danger" href="{{ route('payroll.period') }}">Batal Edit</a>
  @endif
</div>

<div class="collapse mb-3 {{ $edit ? 'show' : '' }}" id="payrollPeriodForm">
  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post">
        @csrf
        <input type="hidden" name="id" value="{{ $edit->id ?? '' }}">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label">Tipe Periode</label>
            <select class="form-select" name="period_type" id="period_type">
              <option value="month_year" {{ ($periodTypeValue ?? 'month_year') === 'month_year' ? 'selected' : '' }}>Bulan/Tahun (Auto 21-20)</option>
              <option value="date_range" {{ ($periodTypeValue ?? 'month_year') === 'date_range' ? 'selected' : '' }}>Rentang Tanggal</option>
            </select>
          </div>
          <div class="col-md-3 d-none" id="start_date_wrap">
            <label class="form-label">Tanggal Mulai</label>
            <input type="date" class="form-control" name="start_date" id="start_date" value="{{ $startDateValue ?? '' }}">
          </div>
          <div class="col-md-3 d-none" id="end_date_wrap">
            <label class="form-label">Tanggal Akhir</label>
            <input type="date" class="form-control" name="end_date" id="end_date" value="{{ $endDateValue ?? '' }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">Bulan</label>
            <input type="number" class="form-control" name="month" id="month" min="1" max="12" value="{{ $monthValue }}" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Tahun</label>
            <input type="number" class="form-control" name="year" id="year" min="2000" max="2100" value="{{ $yearValue }}" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Status</label>
            <input type="text" class="form-control bg-light" value="{{ $statusValue }}" readonly>
            <div class="form-text">Status otomatis: Draft saat dibuat, Running saat Run Payroll, Close saat approval payroll report selesai.</div>
          </div>
          <div class="col-md-3">
            <button class="btn btn-success w-100" type="submit">{{ $edit ? 'Update' : 'Save' }}</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <table class="table table-striped table-sm">
      <thead>
        <tr>
          <th>Bulan</th>
          <th>Tahun</th>
          <th>Periode Absensi (21-20)</th>
          <th>Status</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($periods as $p)
          <tr>
            <td>{{ $p->month }}</td>
            <td>{{ $p->year }}</td>
            <td>{{ $p->period_label ?? '-' }}</td>
            <td>{{ $p->status }}</td>
            <td class="text-end">
              <a class="icon-btn icon-edit" title="Edit" href="{{ route('payroll.period', ['edit' => $p->id]) }}">
                <span class="icon i-edit" aria-hidden="true"></span>
              </a>
              <form method="post" class="d-inline" onsubmit="return confirm('Hapus periode ini?');">
                @csrf
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="{{ $p->id }}">
                <button class="icon-btn icon-delete" title="Delete" type="submit">
                  <span class="icon i-trash" aria-hidden="true"></span>
                </button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="noAttendanceModal" tabindex="-1" aria-labelledby="noAttendanceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="noAttendanceModalLabel">Tidak Bisa Buat Payroll Period</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        {{ $noAttendanceMessage ?? 'Belum ada data absensi untuk periode ini.' }}
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

@if (!empty($showNoAttendanceModal))
<script>
  (function () {
    var modalEl = document.getElementById('noAttendanceModal');
    if (!modalEl || !window.bootstrap) return;
    var modal = new bootstrap.Modal(modalEl);
    modal.show();
  })();
</script>
@endif
<script>
  (function () {
    var periodType = document.getElementById('period_type');
    var month = document.getElementById('month');
    var year = document.getElementById('year');
    var startWrap = document.getElementById('start_date_wrap');
    var endWrap = document.getElementById('end_date_wrap');
    var startDate = document.getElementById('start_date');
    var endDate = document.getElementById('end_date');
    if (!periodType) return;

    function syncPeriodTypeUi() {
      var isDateRange = periodType.value === 'date_range';
      if (startWrap) startWrap.classList.toggle('d-none', !isDateRange);
      if (endWrap) endWrap.classList.toggle('d-none', !isDateRange);
      if (month) month.readOnly = isDateRange;
      if (year) year.readOnly = isDateRange;
      if (month) month.classList.toggle('bg-light', isDateRange);
      if (year) year.classList.toggle('bg-light', isDateRange);
      if (startDate) startDate.required = isDateRange;
      if (endDate) endDate.required = isDateRange;
    }

    periodType.addEventListener('change', syncPeriodTypeUi);
    syncPeriodTypeUi();
  })();
</script>
@endsection
