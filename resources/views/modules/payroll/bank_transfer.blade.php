@extends('layouts.app')

@section('content')
<h4 class="mb-3">Payroll Bank Transfer</h4>

@if ($errors->has('bank'))
  <div class="alert alert-danger">{{ $errors->first('bank') }}</div>
@endif

<form class="mb-3" method="get">
  <div class="row g-2 align-items-end">
    @if (current_user_has_global_scope($user))
    <div class="col-md-4">
      <label class="form-label">Company</label>
      <select class="form-select" name="company_id" onchange="this.form.submit()">
        @foreach ($companies as $c)
          <option value="{{ $c->id }}" {{ (int) $companyId === (int) $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
        @endforeach
      </select>
    </div>
    @endif
    <div class="col-md-3">
      <label class="form-label">Period</label>
      <select class="form-select" name="period_id" onchange="this.form.submit()">
        @foreach ($periods as $p)
          <option value="{{ $p->id }}" {{ (int) $periodId === (int) $p->id ? 'selected' : '' }}>{{ $p->month }}/{{ $p->year }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-5">
      <label class="form-label">Bank</label>
      <div class="form-control bg-white">{{ $bankType ?? '-' }}</div>
      <div class="form-text">BNI: PT. Berkah Cipta Persada & PT. Bina Control Power. BSI: perusahaan lainnya.</div>
    </div>
  </div>
</form>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center">
      <div>
        <div class="fw-semibold">Ringkasan</div>
        <div class="text-muted small">Valid: {{ count($rows) }} | Invalid: {{ count($invalids) }}</div>
        <div class="text-muted small">Total Amount: {{ format_currency($totalAmount) }}</div>
      </div>
      <div>
        @if (($bankType ?? '') === 'BNI')
          <form method="post" class="d-inline">
            @csrf
            <input type="hidden" name="action" value="download_bni">
            <button class="btn btn-success btn-sm" type="submit">Download CSV BNI (Inhouse)</button>
          </form>
          <div class="text-muted small mt-1">Rek. Debet: {{ $debitAccount }} | Remark: {{ $remarkDefault }}</div>
        @else
          <button class="btn btn-secondary btn-sm" type="button" disabled>Download CSV (BSI belum tersedia)</button>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="fw-semibold mb-2">Data Valid</div>
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead>
          <tr>
            <th>NIK</th>
            <th>Nama</th>
            <th>Bank</th>
            <th>No. Rekening</th>
            <th class="text-end">Amount</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($rows as $r)
            <tr>
              <td>{{ $r['nik'] }}</td>
              <td>{{ $r['name'] }}</td>
              <td>{{ $r['bank_name'] }}</td>
              <td>{{ $r['bank_account_no'] }}</td>
              <td class="text-end">{{ format_currency($r['amount']) }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-muted">Belum ada data valid.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="fw-semibold mb-2">Data Invalid</div>
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead>
          <tr>
            <th>NIK</th>
            <th>Nama</th>
            <th>Bank</th>
            <th>No. Rekening</th>
            <th class="text-end">Amount</th>
            <th>Masalah</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($invalids as $r)
            <tr>
              <td>{{ $r['nik'] }}</td>
              <td>{{ $r['name'] }}</td>
              <td>{{ $r['bank_name'] }}</td>
              <td>{{ $r['bank_account_no'] }}</td>
              <td class="text-end">{{ format_currency($r['amount']) }}</td>
              <td class="text-danger">{{ $r['issues'] }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-muted">Tidak ada data invalid.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
