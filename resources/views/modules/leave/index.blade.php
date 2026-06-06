@extends('layouts.app')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">Management Cuti</h4>
</div>

@if (!empty($messages))
  @foreach ($messages as $m)
    <div class="alert alert-success">{{ $m }}</div>
  @endforeach
@endif

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <h6 class="mb-3">Generate Cuti Lebaran</h6>
    <form method="post" class="row g-2 align-items-end">
      @csrf
      @if ($hasGlobalScope ?? false)
        <div class="col-md-3">
          <label class="form-label">Entitas Perusahaan</label>
          <select class="form-select" name="target_company_id" required>
            <option value="0" {{ (int) ($selectedCompanyId ?? 0) === 0 ? 'selected' : '' }}>All Entitas Perusahaan</option>
            @foreach (($companies ?? []) as $c)
              <option value="{{ $c->id }}" {{ (int) ($selectedCompanyId ?? 0) === (int) $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
            @endforeach
          </select>
        </div>
      @else
        <input type="hidden" name="target_company_id" value="{{ (int) ($selectedCompanyId ?? current_company_id()) }}">
      @endif
      <div class="col-md-3">
        <label class="form-label">Tanggal Mulai</label>
        <input type="date" class="form-control" name="date_start" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Tanggal Selesai</label>
        <input type="date" class="form-control" name="date_end" required>
      </div>
      <div class="col-md-2">
        <label class="form-label">Alasan</label>
        <input type="text" class="form-control" name="reason" placeholder="Cuti Lebaran">
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" name="action" value="generate_lebaran" class="btn btn-primary w-100">Generate</button>
        @if (($isAdmin ?? false))
        <button
          type="submit"
          name="action"
          value="reset_lebaran"
          class="btn btn-outline-danger w-100"
          onclick="return confirm('Reset generated cuti bersama untuk range tanggal ini?')"
        >
          Reset
        </button>
        @endif
      </div>
    </form>
    <div class="small text-muted mt-2">Generate akan menetapkan cuti bersama dan membentuk request cuti massal. Jika pilih <b>All Entitas Perusahaan</b>, proses generate dijalankan ke semua company sekaligus. Tombol Reset (admin only) menghapus seluruh data generated cuti bersama/lebaran pada entitas ini agar total cuti kembali 0.</div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="fw-semibold">Rekap Cuti Tahunan</div>
      <div class="small text-muted">Jatah: {{ $quota }} hari / tahun (reset 1 Januari)</div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead>
          <tr>
            <th>Employee</th>
            <th>Join Date</th>
            <th>Eligible</th>
            <th class="text-end">Cuti Terpakai</th>
            <th class="text-end">Sisa Cuti</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($rows as $row)
            @php $e = $row['employee']; @endphp
            <tr>
              <td>{{ $e->name }} ({{ $e->nik }})</td>
              <td>{{ $e->join_date ? format_date_id($e->join_date) : '-' }}</td>
              <td>
                @if ($row['eligible'])
                  <span class="text-success fw-semibold">Eligible</span>
                @else
                  <span class="text-danger fw-semibold">Belum 1 tahun</span>
                @endif
              </td>
              <td class="text-end">{{ $row['used'] }}</td>
              <td class="text-end">{{ $row['remaining'] }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
