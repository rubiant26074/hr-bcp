@extends('layouts.app')

@section('content')
@php
  $vm = $viewMode ?? 'active';
  $isAdmin = (($user['role'] ?? '') === 'Super Admin');
  $currentCompany = collect($companies ?? [])->firstWhere('id', (int) ($companyId ?? 0));
  $isResourceMitraBersama = mb_strtolower(trim((string) ($currentCompany->company_name ?? ''))) === 'pt. resource mitra bersama';
@endphp
<h4 class="mb-3">
  @if ($vm === 'archive')
    Arsip Master Employees
  @elseif ($vm === 'mutasi')
    Arsip Mutasi
  @elseif ($vm === 'active_all')
    Data Karyawan Aktif (All)
  @elseif ($vm === 'active_tetap')
    Data Karyawan Tetap
  @elseif ($vm === 'active_kontrak')
    Data Karyawan Kontrak
  @elseif ($vm === 'active_percobaan')
    Data Karyawan Percobaan & Magang
  @elseif ($vm === 'active_harian')
    Data Karyawan Harian
  @elseif ($vm === 'active_freelance')
    Data Karyawan Freelance
  @elseif ($vm === 'active_komisaris')
    Data Komisaris
  @else
    Master Employees
  @endif
</h4>
<style>
  .emp-actions { width: 48px; min-width: 48px; }
  .emp-actions .action-stack { display: flex; flex-direction: column; align-items: center; gap: 6px; }
  .emp-actions .icon-btn { display: inline-flex; }
  .emp-actions form { margin: 0; }
  .emp-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
  .emp-table { width: 100%; table-layout: fixed; }
  .emp-table th, .emp-table td { white-space: normal; word-break: normal; overflow-wrap: break-word; }
  .emp-table th, .emp-table td { border-right: 1px solid #dee2e6; }
  .emp-table th:last-child, .emp-table td:last-child { border-right: 0; }
  .emp-table th { font-size: 0.85rem; text-align: center; vertical-align: middle; }
  .emp-table td { font-size: 0.85rem; vertical-align: top; }
  .emp-col-photo { width: 86px; }
  .emp-col-aksi { width: 56px; }
  .emp-col-nik { width: 120px; }
  .emp-col-nik-ktp { width: 160px; }
  .emp-photo-thumb {
    width: 54px;
    height: 54px;
    object-fit: cover;
    object-position: center;
  }
  .emp-nowrap {
    white-space: nowrap !important;
    word-break: normal !important;
  }
  @if (($viewMode ?? 'active') === 'mutasi')
    .emp-table th { line-height: 1.15; font-size: 0.82rem; }
  @endif
  @media (max-width: 992px) {
    .emp-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .emp-table { width: max-content; min-width: 1480px; table-layout: fixed; }
    .emp-table th, .emp-table td { white-space: normal; word-break: normal; overflow-wrap: break-word; }
    .emp-table th { font-size: 0.8rem; }
    .emp-table td { font-size: 0.8rem; }
  }
</style>

@if (current_user_has_global_scope($user))
<form class="mb-3" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label">Company</label>
      <input type="hidden" name="view" value="{{ $viewMode ?? 'active' }}">
      <select class="form-select" name="set_company" onchange="this.form.submit()">
        @foreach ($companies as $c)
          <option value="{{ $c->id }}" {{ $companyId == $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
        @endforeach
      </select>
    </div>
  </div>
</form>
@endif

<div class="row g-3">
  <div class="col-md-12">
    <div class="card shadow-sm mb-3">
      <div class="card-body d-flex gap-2 flex-wrap">
        <a class="btn btn-primary" href="{{ route('employees.form') }}">Tambah Karyawan</a>
        <form method="get" class="d-inline-flex align-items-center gap-2">
          <label for="employee_view_mode" class="small text-muted mb-0">View</label>
          <select
            id="employee_view_mode"
            name="view"
            class="form-select form-select-sm"
            style="min-width: 230px;"
            onchange="this.form.submit()"
          >
            <option value="active_all" {{ $vm === 'active_all' ? 'selected' : '' }}>Data Karyawan Aktif (All)</option>
            <option value="active_tetap" {{ $vm === 'active_tetap' ? 'selected' : '' }}>Data Karyawan Tetap</option>
            <option value="active_kontrak" {{ $vm === 'active_kontrak' ? 'selected' : '' }}>Data Karyawan Kontrak</option>
            <option value="active_harian" {{ $vm === 'active_harian' ? 'selected' : '' }}>Data Karyawan Harian</option>
            <option value="active_freelance" {{ $vm === 'active_freelance' ? 'selected' : '' }}>Data Karyawan Freelance</option>
            <option value="active_komisaris" {{ $vm === 'active_komisaris' ? 'selected' : '' }}>Data Komisaris</option>
            <option value="active_percobaan" {{ $vm === 'active_percobaan' ? 'selected' : '' }}>Data Karyawan Percobaan & Magang</option>
            <option value="archive" {{ $vm === 'archive' ? 'selected' : '' }}>Arsip Resign/PHK/Habis Kontrak</option>
            <option value="mutasi" {{ $vm === 'mutasi' ? 'selected' : '' }} {{ !($mutasiTableReady ?? false) ? 'disabled' : '' }}>Arsip Mutasi</option>
          </select>
        </form>
        <div class="btn-group">
          <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            Setting
          </button>
          <ul class="dropdown-menu">
            @php
              $activeStatusHref = \Illuminate\Support\Facades\Route::has('employees.active_status')
                ? route('employees.active_status')
                : url('/employees/active-status');
            @endphp
            <li><a class="dropdown-item" href="{{ $activeStatusHref }}">Status Aktif</a></li>
            <li><a class="dropdown-item" href="{{ route('employees.status') }}">Status Karyawan</a></li>
            <li><a class="dropdown-item" href="{{ route('employees.type') }}">Staf & Non Staf</a></li>
            <li><a class="dropdown-item" href="{{ route('employees.department') }}">Master Departement</a></li>
            <li><a class="dropdown-item" href="{{ route('employees.position') }}">Jabatan</a></li>
            <li><a class="dropdown-item" href="{{ route('employees.grade') }}">Golongan</a></li>
            <li><a class="dropdown-item" href="{{ route('employees.allin_overtime') }}">Setting Lembur All-In</a></li>
          </ul>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('employees.import') }}">Import CSV/Excel</a>
        <a class="btn btn-outline-success" href="{{ route('employees.export', request()->query()) }}">Export CSV</a>
        <a class="btn btn-outline-success" href="{{ route('employees.export_excel', request()->query()) }}">Export Excel</a>
        <a class="btn btn-outline-success" href="{{ route('employees.export_pdf', request()->query()) }}">Export PDF</a>
        @if ($vm !== 'mutasi')
          <button
            type="button"
            class="btn btn-outline-danger"
            id="btnBulkDelete"
            disabled
          >Hapus Terpilih</button>
        @endif
      </div>
    </div>
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form method="get" class="row g-2 align-items-end" id="empFilterForm">
          <input type="hidden" name="view" value="{{ $vm }}">
          <div class="col-md-4">
            <label class="form-label">Search</label>
            <input type="text" class="form-control" name="q" value="{{ $q }}" placeholder="Nama / NIK / Jabatan" data-auto-filter="input">
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <input type="text" class="form-control" name="status" value="{{ $filterStatus }}" placeholder="Kontrak/Tetap" data-auto-filter="input">
          </div>
          <div class="col-md-4">
            <label class="form-label">Type</label>
            <input type="text" class="form-control" name="type" value="{{ $filterType }}" placeholder="Staff/Non Staff" data-auto-filter="input">
          </div>
          <div class="col-12">
            <a class="btn btn-outline-secondary" href="{{ route('employees.index', ['view' => $vm]) }}">Reset</a>
          </div>
        </form>
      </div>
    </div>
    @if ($vm === 'mutasi' && !($mutasiTableReady ?? false))
      <div class="alert alert-warning">Table <span class="fw-semibold">employee_mutations</span> belum ada. Jalankan migration supaya Arsip Mutasi bisa dipakai.</div>
    @endif
    <div class="card shadow-sm">
      <div class="card-body">
        @if ($vm !== 'mutasi')
          <form id="bulkDeleteForm" method="post" action="{{ route('employees.index', request()->query()) }}" class="d-none">
            @csrf
            <input type="hidden" name="action" value="delete_bulk">
            <div id="bulkDeleteIds"></div>
          </form>
        @endif
        <div class="emp-table-wrap">
        <table class="table table-striped table-sm emp-table">
          <thead>
            <tr>
              @if ($vm !== 'mutasi')
                <th style="width:44px;">
                  <input type="checkbox" id="checkAllRows" title="Pilih semua">
                </th>
              @endif
              <th class="emp-col-photo">Foto</th>
              <th class="emp-col-nik emp-nowrap">NIK</th>
              <th class="emp-col-nik-ktp emp-nowrap">NIK Penduduk</th>
              <th>Nama</th>
              <th>Status<br>Aktif</th>
              @if ($vm === 'mutasi')
                <th>Mutasi<br>Ke</th>
                <th>Tgl<br>Mutasi</th>
              @endif
              <th>Nomor<br>HP</th>
              <th>NPWP</th>
              <th>Status</th>
              <th>Tipe</th>
              <th>Dept</th>
              @if ($isResourceMitraBersama && $vm !== 'mutasi')
                <th>Pnpt</th>
              @endif
              <th>Jabatan</th>
              <th>Golongan</th>
              <th>Tanggal<br>Join</th>
              <th>Habis<br>Kontrak</th>
              <th class="text-center emp-actions emp-col-aksi">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($employees as $e)
              @php $mut = ($vm === 'mutasi') ? ($mutationsByEmployee->get($e->id) ?? null) : null; @endphp
              <tr>
                @if ($vm !== 'mutasi')
                  <td class="text-center align-middle">
                    <input type="checkbox" class="row-check" value="{{ $e->id }}" title="Pilih baris">
                  </td>
                @endif
                <td>
                  @if (!empty($e->photo_path))
                    <img src="{{ asset_url($e->photo_path) }}" alt="photo" class="rounded emp-photo-thumb">
                  @endif
                </td>
                <td class="emp-col-nik emp-nowrap">{{ $e->nik }}</td>
                <td class="emp-col-nik-ktp emp-nowrap">{{ $e->nik_ktp }}</td>
                <td>{{ $e->name }}</td>
                <td>{{ $vm === 'mutasi' ? 'Mutasi' : $e->active_status }}</td>
                @if ($vm === 'mutasi')
                  <td>{{ $mut ? (optional($mut->toCompany)->company_name ?: '-') : '-' }}</td>
                  <td>{{ $mut ? format_date_id(optional($mut->mutated_at)->format('Y-m-d')) : '-' }}</td>
                @endif
                <td>{{ $e->phone }}</td>
                <td>{{ $e->npwp }}</td>
                <td>{{ $e->employment_status }}</td>
                <td>{{ $e->employee_type }}</td>
                <td>{{ $e->department }}</td>
                @if ($isResourceMitraBersama && $vm !== 'mutasi')
                  <td>{{ optional($e->placementCompany)->company_name ?: '-' }}</td>
                @endif
                <td>{{ $e->position }}</td>
                <td>{{ $e->grade }}</td>
                <td>{{ format_date_id($e->join_date) }}</td>
                <td>{{ format_date_id($e->contract_end) }}</td>
                <td class="text-center emp-actions align-middle">
                  <div class="action-stack">
                    @if ($vm !== 'mutasi')
                      <a class="icon-btn icon-edit" title="Edit" href="{{ route('employees.form', ['edit' => $e->id, 'view' => $vm]) }}">
                        <span class="icon i-edit" aria-hidden="true"></span>
                      </a>
                    @endif
                    <a class="icon-btn icon-detail" title="Detail" href="{{ route('employees.detail', ['id' => $e->id]) }}">
                      <span class="icon i-eye" aria-hidden="true"></span>
                    </a>
                    @if ($vm !== 'mutasi')
                      <form method="post" onsubmit="return confirm('Hapus karyawan ini?');">
                        @csrf
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="{{ $e->id }}">
                        <button class="icon-btn icon-delete" title="Delete" type="submit">
                          <span class="icon i-trash" aria-hidden="true"></span>
                        </button>
                      </form>
                    @elseif ($isAdmin && $mut)
                      <form method="post" onsubmit="return confirm('Hapus arsip mutasi ini?');">
                        @csrf
                        <input type="hidden" name="action" value="delete_mutation">
                        <input type="hidden" name="mutation_id" value="{{ $mut->id }}">
                        <button class="icon-btn icon-delete" title="Delete Arsip Mutasi" type="submit">
                          <span class="icon i-trash" aria-hidden="true"></span>
                        </button>
                      </form>
                    @endif
                  </div>
                </td>
              </tr>
            @endforeach
            @if ($employees->isEmpty())
              <tr>
                <td colspan="{{ $vm === 'mutasi' ? 17 : ($isResourceMitraBersama ? 17 : 16) }}" class="text-center text-muted py-4">
                  @if ($vm === 'archive')
                    Belum ada data arsip karyawan.
                  @elseif ($vm === 'mutasi')
                    Belum ada data arsip mutasi.
                  @else
                    Belum ada data karyawan aktif.
                  @endif
                </td>
              </tr>
            @endif
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
  (function () {
    var form = document.getElementById('empFilterForm');
    if (!form) return;
    var timer = null;

    function submitNow() {
      if (form) form.submit();
    }

    form.querySelectorAll('[data-auto-filter="input"]').forEach(function (el) {
      el.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(submitNow, 400);
      });
      el.addEventListener('change', submitNow);
    });
  })();

  (function () {
    var checkAll = document.getElementById('checkAllRows');
    var rowChecks = Array.prototype.slice.call(document.querySelectorAll('.row-check'));
    var bulkBtn = document.getElementById('btnBulkDelete');
    var bulkForm = document.getElementById('bulkDeleteForm');
    var bulkIdsWrap = document.getElementById('bulkDeleteIds');

    if (!bulkBtn || !bulkForm || !bulkIdsWrap || rowChecks.length === 0) return;

    function selectedIds() {
      return rowChecks.filter(function (el) { return el.checked; }).map(function (el) { return el.value; });
    }

    function syncState() {
      var ids = selectedIds();
      bulkBtn.disabled = ids.length === 0;
      if (checkAll) {
        checkAll.checked = ids.length > 0 && ids.length === rowChecks.length;
      }
    }

    rowChecks.forEach(function (cb) {
      cb.addEventListener('change', syncState);
    });

    if (checkAll) {
      checkAll.addEventListener('change', function () {
        rowChecks.forEach(function (cb) { cb.checked = checkAll.checked; });
        syncState();
      });
    }

    bulkBtn.addEventListener('click', function () {
      var ids = selectedIds();
      if (ids.length === 0) return;
      if (!confirm('Hapus ' + ids.length + ' karyawan terpilih?')) return;

      bulkIdsWrap.innerHTML = '';
      ids.forEach(function (id) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        bulkIdsWrap.appendChild(input);
      });
      bulkForm.submit();
    });
  })();
</script>
@endsection
