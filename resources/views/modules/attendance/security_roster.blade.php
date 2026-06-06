@extends('layouts.app')

@section('content')
<h4 class="mb-3">Jadwal Shift Security</h4>
@php
  $nextDir = function ($col) use ($sort, $dir) {
      return ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
  };
  $sortUrl = function ($col) use ($month, $year, $q, $companyId, $sort, $nextDir) {
      $params = ['month' => $month, 'year' => $year, 'q' => $q, 'sort' => $col, 'dir' => $nextDir($col)];
      if (current_user_has_global_scope(current_user())) {
          $params['set_company'] = $companyId;
      }
      return route('attendance.security_roster', $params);
  };
  $sortLabel = function ($text, $col) use ($sort, $dir) {
      $arrow = '';
      if ($sort === $col) {
          $arrow = $dir === 'asc' ? ' ↑' : ' ↓';
      }
      return $text . $arrow;
  };
@endphp

@foreach ($messages as $m)
  <div class="alert alert-info">{{ $m }}</div>
@endforeach

@if (!$hasDefs || !$hasRoster)
  <div class="alert alert-danger">Migration/security tables belum siap.</div>
@endif

<div class="card shadow-sm mb-3">
  <div class="card-body py-2">
    <div class="row g-2 small">
      <div class="col-md-3"><strong>Company:</strong> {{ $company->company_name ?? '-' }}</div>
      <div class="col-md-3"><strong>Periode:</strong> {{ format_date_id($periodStart) }} - {{ format_date_id($periodEnd) }}</div>
      <div class="col-md-6"><strong>Stat:</strong> Total {{ $stats['total'] }} | P {{ $stats['p'] }} | S {{ $stats['s'] }} | M {{ $stats['m'] }} | OFF {{ $stats['off'] }}</div>
    </div>
  </div>
</div>

<form method="get" class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-2 align-items-end">
      @if (current_user_has_global_scope($user))
      <div class="col-md-3">
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
        <input class="form-control" type="number" min="1" max="12" name="month" value="{{ $month }}">
      </div>
      <div class="col-md-2">
        <label class="form-label">Year</label>
        <input class="form-control" type="number" min="2020" max="2100" name="year" value="{{ $year }}">
      </div>
      <div class="col-md-2">
        <label class="form-label">Filter Periode</label>
        <select class="form-select" name="period_mode" id="period-mode">
          <option value="cutoff" {{ ($periodMode ?? 'cutoff') === 'cutoff' ? 'selected' : '' }}>Tanggal Cut-off</option>
          <option value="date_range" {{ ($periodMode ?? 'cutoff') === 'date_range' ? 'selected' : '' }}>Rentang Tanggal</option>
        </select>
      </div>
      <div class="col-md-2 js-date-range">
        <label class="form-label">Start Date</label>
        <input class="form-control" type="date" name="start_date" value="{{ $startDateInput ?? '' }}">
      </div>
      <div class="col-md-2 js-date-range">
        <label class="form-label">End Date</label>
        <input class="form-control" type="date" name="end_date" value="{{ $endDateInput ?? '' }}">
      </div>
      <div class="col-md-3">
        <label class="form-label">Cari Personel</label>
        <input class="form-control" type="text" name="q" value="{{ $q }}" placeholder="NIK/Nama/Shift">
      </div>
      <input type="hidden" name="sort" value="{{ $sort }}">
      <input type="hidden" name="dir" value="{{ $dir }}">
      <div class="col-md-2">
        <button class="btn btn-primary w-100" type="submit">Tampilkan</button>
      </div>
      <div class="col-md-3">
        <a
          class="btn btn-outline-success w-100"
          href="{{ route('attendance.security_roster', ['month' => $month, 'year' => $year, 'download_template' => 1]) }}"
        >Download Template Excel</a>
      </div>
      <div class="col-md-2">
        <a
          class="btn btn-outline-danger w-100"
          href="{{ route('attendance.security_roster', ['month' => $month, 'year' => $year, 'q' => $q, 'period_mode' => ($periodMode ?? 'cutoff'), 'start_date' => ($startDateInput ?? ''), 'end_date' => ($endDateInput ?? ''), 'download_pdf' => 1]) }}"
        >Generate PDF</a>
      </div>
      <div class="col-md-2">
        <a
          class="btn btn-outline-primary w-100"
          href="{{ route('attendance.security_roster', ['month' => $month, 'year' => $year, 'q' => $q, 'period_mode' => ($periodMode ?? 'cutoff'), 'start_date' => ($startDateInput ?? ''), 'end_date' => ($endDateInput ?? ''), 'download_excel' => 1]) }}"
        >Export Excel</a>
      </div>
    </div>
  </div>
</form>

@if ($hasDefs && $hasRoster)
<div class="card shadow-sm mb-3">
  <div class="card-header">Import Jadwal Security (XLS/XLSX)</div>
  <div class="card-body">
    <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
      @csrf
      <input type="hidden" name="action" value="import_roster_xls">
      <div class="col-md-8">
        <label class="form-label">File Jadwal</label>
        <input type="file" name="roster_file" class="form-control" accept=".xls,.xlsx,.csv" required>
      </div>
      <div class="col-md-4">
        <button class="btn btn-primary w-100" type="submit">Import Jadwal</button>
      </div>
      <div class="col-12">
        <small class="text-muted">Format didukung: .xls/.xlsx/.csv dengan tabel list (Tanggal/NIK/Nama/Shift) atau tabel matrix/grid (NIK/Nama + kolom tanggal berisi P/S/M/OFF).</small>
      </div>
    </form>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-header">Input Roster Manual</div>
      <div class="card-body">
        <form method="post">
          @csrf
          <input type="hidden" name="action" value="manual_save">
          <div class="mb-2">
            <label class="form-label">Tanggal</label>
            <input class="form-control" type="date" name="work_date" value="{{ $periodStart }}">
          </div>
          <div class="mb-2">
            <label class="form-label">Site Tempat Jaga</label>
            <select class="form-select" name="site_guard" id="manual-site-guard">
              <option value="">-- Pilih Site --</option>
              @foreach (($securitySites ?? collect()) as $s)
                <option value="{{ $s }}">{{ $s }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Personel</label>
            <select class="form-select" name="employee_id" id="manual-employee-id" required>
              <option value="">-- Pilih --</option>
              @foreach ($securityEmployees as $e)
                <option value="{{ $e->id }}" data-site="{{ trim((string) ($e->department ?? '')) }}">{{ $e->nik }} - {{ $e->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Shift</label>
            <select class="form-select" name="shift_code" required>
              <option value="P">P (07:00 - 15:00)</option>
              <option value="S">S (15:00 - 23:00)</option>
              <option value="M">M (23:00 - 07:00)</option>
              <option value="OFF">OFF</option>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Reason</label>
            <input class="form-control" name="reason" value="Input manual roster">
          </div>
          <div class="mb-2">
            <label class="form-label">Instruction Ref</label>
            <input class="form-control" name="instruction_ref" placeholder="WA/Ticket">
          </div>
          <button class="btn btn-success w-100" type="submit">Simpan Manual</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-header">Swap Shift</div>
      <div class="card-body">
        <form method="post">
          @csrf
          <input type="hidden" name="action" value="swap">
          <div class="mb-2">
            <label class="form-label">Tanggal</label>
            <input class="form-control" type="date" name="work_date" value="{{ $periodStart }}">
          </div>
          <div class="mb-2">
            <label class="form-label">Personel A</label>
            <select class="form-select" name="employee_a" required>
              <option value="">-- Pilih --</option>
              @foreach ($securityEmployees as $e)
                <option value="{{ $e->id }}">{{ $e->nik }} - {{ $e->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Personel B</label>
            <select class="form-select" name="employee_b" required>
              <option value="">-- Pilih --</option>
              @foreach ($securityEmployees as $e)
                <option value="{{ $e->id }}">{{ $e->nik }} - {{ $e->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Reason</label>
            <input class="form-control" name="reason" placeholder="Alasan swap" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Instruction Ref</label>
            <input class="form-control" name="instruction_ref" placeholder="WA/Ticket">
          </div>
          <button class="btn btn-warning w-100" type="submit">Swap</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-header">Replace Shift</div>
      <div class="card-body">
        <form method="post">
          @csrf
          <input type="hidden" name="action" value="replace">
          <div class="mb-2">
            <label class="form-label">Tanggal</label>
            <input class="form-control" type="date" name="work_date" value="{{ $periodStart }}">
          </div>
          <div class="mb-2">
            <label class="form-label">From Employee</label>
            <select class="form-select" name="from_employee" required>
              <option value="">-- Pilih --</option>
              @foreach ($securityEmployees as $e)
                <option value="{{ $e->id }}">{{ $e->nik }} - {{ $e->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">To Employee</label>
            <select class="form-select" name="to_employee" required>
              <option value="">-- Pilih --</option>
              @foreach ($securityEmployees as $e)
                <option value="{{ $e->id }}">{{ $e->nik }} - {{ $e->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Reason</label>
            <input class="form-control" name="reason" placeholder="Alasan replace" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Instruction Ref</label>
            <input class="form-control" name="instruction_ref" placeholder="WA/Ticket">
          </div>
          <button class="btn btn-danger w-100" type="submit">Replace</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endif

<div class="card shadow-sm">
  <div class="card-header">Daftar Roster Security</div>
  <div class="card-body">
    <form method="post" id="bulk-delete-form" onsubmit="return confirm('Hapus semua roster yang dicentang?');" class="mb-2">
      @csrf
      <input type="hidden" name="action" value="delete_roster_bulk">
      <input type="hidden" name="reason" value="Hapus roster terpilih dari UI">
      <button type="submit" class="btn btn-sm btn-danger">Hapus Terpilih</button>
    </form>
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead>
          <tr>
            <th style="width: 42px;">
              <input type="checkbox" id="check-all-roster" title="Pilih semua">
            </th>
            <th><a href="{{ $sortUrl('tanggal') }}" class="text-decoration-none text-dark">{{ $sortLabel('Tanggal', 'tanggal') }}</a></th>
            <th><a href="{{ $sortUrl('nik') }}" class="text-decoration-none text-dark">{{ $sortLabel('NIK', 'nik') }}</a></th>
            <th><a href="{{ $sortUrl('nama') }}" class="text-decoration-none text-dark">{{ $sortLabel('Nama', 'nama') }}</a></th>
            <th><a href="{{ $sortUrl('jabatan') }}" class="text-decoration-none text-dark">{{ $sortLabel('Jabatan', 'jabatan') }}</a></th>
            <th>Site</th>
            <th>Shift</th>
            <th>Start</th>
            <th>End</th>
            <th>Source</th>
            <th>Note</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($rows as $r)
            <tr>
              <td>
                <input
                  type="checkbox"
                  class="roster-check"
                  name="roster_ids[]"
                  value="{{ (int) $r->id }}"
                  form="bulk-delete-form"
                >
              </td>
              <td>{{ format_date_id($r->work_date) }}</td>
              <td>{{ $r->nik }}</td>
              <td>{{ $r->name }}</td>
              <td>{{ $r->position ?? '-' }}</td>
              <td>{{ $r->site_display ?? ($r->department ?? '-') }}</td>
              <td><span class="badge text-bg-secondary">{{ $r->shift_code }}</span></td>
              <td>{{ $r->shift_start_at ? format_datetime_id($r->shift_start_at) : '-' }}</td>
              <td>{{ $r->shift_end_at ? format_datetime_id($r->shift_end_at) : '-' }}</td>
              <td>{{ $r->source }}</td>
              <td>{{ $r->note ?? '-' }}</td>
              <td>
                <form method="post" onsubmit="return confirm('Hapus roster ini?');">
                  @csrf
                  <input type="hidden" name="action" value="delete_roster">
                  <input type="hidden" name="roster_id" value="{{ (int) $r->id }}">
                  <input type="hidden" name="reason" value="Hapus roster satu klik">
                  <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="12" class="text-center text-muted">Belum ada data roster.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var checkAll = document.getElementById('check-all-roster');
  if (!checkAll) {
    return;
  }
  checkAll.addEventListener('change', function () {
    document.querySelectorAll('.roster-check').forEach(function (el) {
      el.checked = checkAll.checked;
    });
  });

  var siteSelect = document.getElementById('manual-site-guard');
  var employeeSelect = document.getElementById('manual-employee-id');
  if (siteSelect && employeeSelect) {
    siteSelect.addEventListener('change', function () {
      var selectedSite = (siteSelect.value || '').trim().toUpperCase();
      Array.prototype.forEach.call(employeeSelect.options, function (opt, idx) {
        if (idx === 0) {
          opt.hidden = false;
          return;
        }
        var site = ((opt.getAttribute('data-site') || '') + '').trim().toUpperCase();
        opt.hidden = selectedSite !== '' && site !== selectedSite;
      });
      employeeSelect.value = '';
    });
  }

  var periodMode = document.getElementById('period-mode');
  function toggleDateRange() {
    var isRange = periodMode && periodMode.value === 'date_range';
    document.querySelectorAll('.js-date-range').forEach(function (el) {
      el.style.display = isRange ? '' : 'none';
    });
  }
  if (periodMode) {
    periodMode.addEventListener('change', toggleDateRange);
    toggleDateRange();
  }
});
</script>
@endsection
