@extends('layouts.app')

@section('content')
<h4 class="mb-3">Import Absensi (CSV)</h4>
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
  </div>
</form>
@endif

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post" enctype="multipart/form-data">
      @csrf
      @if (current_user_has_global_scope($user))
      <div class="mb-3">
        <label class="form-label">Company (Untuk Import)</label>
        <select class="form-select" name="company_id" required>
          <option value="0" {{ (int) ($importCompanyId ?? $companyId ?? 0) === 0 ? 'selected' : '' }}>Semua Entitas Perusahaan</option>
          @foreach ($companies as $c)
            <option value="{{ $c->id }}" {{ (int) ($importCompanyId ?? $companyId ?? 0) === (int) $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
          @endforeach
        </select>
        <div class="form-text">Jika pilih "Semua Entitas Perusahaan", sistem akan mendistribusikan log ke company masing-masing karyawan secara otomatis.</div>
      </div>
      @endif
      <div class="mb-3">
        <label class="form-label">Upload CSV Fingerprint</label>
        <input type="file" class="form-control" name="file" accept=".csv,.xls,.xlsx" required>
        <div class="form-text">CSV standar: Department, Name, No, Date/Time, Location ID, ID Number, VerifyCode, CardNo</div>
        <div class="form-text">Excel (tab 2.1.*): akan dibaca langsung dari sheet detail log (mis. 2.1.8888).</div>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit" name="action" value="import_now">Import Langsung</button>
        <button class="btn btn-dark" type="submit" name="action" value="start_chunk">Import Bertahap (Chunk)</button>
        <a class="btn btn-outline-secondary" href="{{ route('attendance.template') }}">Download Template</a>
      </div>
    </form>

    @if (!empty($chunkState))
      <hr>
      <div class="alert alert-warning mb-2">
        <div><strong>Proses Chunk Aktif</strong></div>
        <div class="small">Total sementara: {{ (int) ($chunkState['inserted_count'] ?? 0) }} log | Unknown employee: {{ (int) ($chunkState['unknown_count'] ?? 0) }}</div>
      </div>
      <form method="post" class="d-flex gap-2">
        @csrf
        <input type="hidden" name="action" value="process_chunk">
        @if (current_user_has_global_scope($user))
          <input type="hidden" name="company_id" value="{{ (int) ($chunkState['import_company_id'] ?? ($importCompanyId ?? $companyId ?? 0)) }}">
        @endif
        <button class="btn btn-success" type="submit">Lanjut Proses Chunk</button>
      </form>
    @endif
  </div>
</div>
@endsection
