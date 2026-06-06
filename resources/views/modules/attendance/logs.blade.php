@extends('layouts.app')

@section('content')
<h4 class="mb-3">Log Absensi</h4>

@foreach ($messages as $m)
  <div class="alert alert-info">{{ $m }}</div>
@endforeach

@if (current_user_has_global_scope($user))
<form class="mb-3" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label">Company</label>
      <select class="form-select" name="set_company" onchange="this.form.submit()">
        @foreach ($companies as $c)
          <option value="{{ $c->id }}" {{ $companyId == $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
        @endforeach
      </select>
    </div>
    @if ($filters['q'] !== '' || $filters['date_from'] !== '' || $filters['date_to'] !== '' || $filters['verify_type'] !== '' || $filters['device_id'] !== '')
      <input type="hidden" name="q" value="{{ $filters['q'] }}">
      <input type="hidden" name="date_from" value="{{ $filters['date_from'] }}">
      <input type="hidden" name="date_to" value="{{ $filters['date_to'] }}">
      <input type="hidden" name="verify_type" value="{{ $filters['verify_type'] }}">
      <input type="hidden" name="device_id" value="{{ $filters['device_id'] }}">
    @endif
  </div>
</form>
@endif

<form class="mb-3" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label">Keyword</label>
      <input type="text" class="form-control" name="q" value="{{ $filters['q'] }}" placeholder="Nama / Device User ID / Device">
    </div>
    <div class="col-md-2">
      <label class="form-label">Dari Tanggal</label>
      <input type="date" class="form-control" name="date_from" value="{{ $filters['date_from'] }}">
    </div>
    <div class="col-md-2">
      <label class="form-label">Sampai Tanggal</label>
      <input type="date" class="form-control" name="date_to" value="{{ $filters['date_to'] }}">
    </div>
    <div class="col-md-2">
      <label class="form-label">Verify Type</label>
      <input type="text" class="form-control" name="verify_type" value="{{ $filters['verify_type'] }}" placeholder="contoh: 1">
    </div>
    <div class="col-md-2">
      <label class="form-label">Device ID</label>
      <input type="text" class="form-control" name="device_id" value="{{ $filters['device_id'] }}">
    </div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-2">
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('attendance.logs') }}">Reset</a>
    <button class="btn btn-primary btn-sm" type="submit">Filter</button>
  </div>
</form>

<div class="row g-2 mb-2">
  <div class="col-md-6">
    <form method="post" action="{{ route('attendance.logs') }}{{ $queryString !== '' ? ('?' . $queryString) : '' }}" class="border rounded p-2" onsubmit="return confirm('Hapus log sesuai range tanggal ini?');">
      @csrf
      <input type="hidden" name="action" value="delete_range">
      <div class="small fw-semibold mb-2">Delete By Date Range</div>
      <div class="row g-2 align-items-end">
        <div class="col-5">
          <label class="form-label form-label-sm">Dari</label>
          <input type="date" class="form-control form-control-sm" name="delete_date_from" value="{{ $filters['date_from'] }}">
        </div>
        <div class="col-5">
          <label class="form-label form-label-sm">Sampai</label>
          <input type="date" class="form-control form-control-sm" name="delete_date_to" value="{{ $filters['date_to'] }}">
        </div>
        <div class="col-2 d-grid">
          <button class="btn btn-warning btn-sm" type="submit">Delete</button>
        </div>
      </div>
    </form>
  </div>
  <div class="col-md-6">
    <form method="post" action="{{ route('attendance.logs') }}{{ $queryString !== '' ? ('?' . $queryString) : '' }}" class="border rounded p-2 h-100 d-flex flex-column justify-content-between" onsubmit="return confirm('Yakin hapus SEMUA log absensi di company aktif?');">
      @csrf
      <input type="hidden" name="action" value="delete_all">
      <div class="small fw-semibold mb-2">Delete All Logs</div>
      <div class="small text-muted mb-2">Menghapus seluruh log absensi pada company yang sedang dipilih.</div>
      <div class="d-grid">
        <button class="btn btn-danger btn-sm" type="submit">Delete All</button>
      </div>
    </form>
  </div>
</div>

<form id="bulk-delete-logs-form" method="post" action="{{ route('attendance.logs') }}{{ $queryString !== '' ? ('?' . $queryString) : '' }}" class="mb-2 d-flex justify-content-end" onsubmit="return confirm('Hapus semua log terpilih?');">
  @csrf
  <input type="hidden" name="action" value="bulk_delete">
  <button class="btn btn-danger btn-sm" type="submit">Delete Selected</button>
</form>

<form method="post" action="{{ route('attendance.logs') }}{{ $queryString !== '' ? ('?' . $queryString) : '' }}" class="mb-3 d-flex justify-content-between align-items-center border rounded p-2" onsubmit="return confirm('Jalankan Auto Mapping Nama untuk log yang belum terdeteksi?');">
  @csrf
  <input type="hidden" name="action" value="auto_map_unknown">
  <div class="small text-muted">
    Log belum terdeteksi nama: <strong>{{ (int) ($unknownCount ?? 0) }}</strong>
  </div>
  <button class="btn btn-outline-primary btn-sm" type="submit">Auto Mapping Nama</button>
</form>

<div class="card shadow-sm">
  <div class="card-body">
    <table class="table table-striped table-sm">
      <thead>
        <tr>
          <th style="width:32px;">
            <input type="checkbox" id="check-all-logs">
          </th>
          <th>Nama</th>
          <th>Device User ID</th>
          <th>Scan Time</th>
          <th>Verify</th>
          <th>Device</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($logs as $l)
          <tr>
            <td>
              <input type="checkbox" class="log-check" form="bulk-delete-logs-form" name="delete_ids[]" value="{{ $l->id }}">
            </td>
            <td>{{ $l->name ?? '-' }}</td>
            <td>{{ $l->device_user_id }}</td>
            <td>{{ $l->scan_time }}</td>
            <td>{{ $l->verify_type }}</td>
            <td>{{ $l->device_id }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
    <div class="d-flex justify-content-between align-items-center mt-3">
      <div class="small text-muted">{{ $total }} items</div>
      <div class="btn-group" role="group" aria-label="Pagination">
        <a class="btn btn-outline-secondary btn-sm {{ $page <= 1 ? 'disabled' : '' }}" href="{{ route('attendance.logs') }}?{{ $queryPrefix }}page=1">&laquo;</a>
        <a class="btn btn-outline-secondary btn-sm {{ $page <= 1 ? 'disabled' : '' }}" href="{{ route('attendance.logs') }}?{{ $queryPrefix }}page={{ $page - 1 }}">&lsaquo;</a>
        <span class="btn btn-outline-primary btn-sm disabled">{{ $page }} of {{ $totalPages }}</span>
        <a class="btn btn-outline-secondary btn-sm {{ $page >= $totalPages ? 'disabled' : '' }}" href="{{ route('attendance.logs') }}?{{ $queryPrefix }}page={{ $page + 1 }}">&rsaquo;</a>
        <a class="btn btn-outline-secondary btn-sm {{ $page >= $totalPages ? 'disabled' : '' }}" href="{{ route('attendance.logs') }}?{{ $queryPrefix }}page={{ $totalPages }}">&raquo;</a>
      </div>
    </div>
  </div>
</div>
<script>
const checkAllLogs = document.getElementById('check-all-logs');
if (checkAllLogs) {
  checkAllLogs.addEventListener('change', function () {
    document.querySelectorAll('.log-check').forEach(function (cb) {
      cb.checked = checkAllLogs.checked;
    });
  });
}
</script>
@endsection
