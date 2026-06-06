@extends('layouts.app')

@section('content')
@if (request()->query('approval_submitted'))
  <div class="alert alert-success">Pengajuan approval payroll PPh21 berhasil dikirim.</div>
@endif
@if ($errors->has('approval'))
  <div class="alert alert-danger">{{ $errors->first('approval') }}</div>
@endif

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between gap-3">
      <div>
        <div class="fw-semibold">Approval Payroll PPh21</div>
        <div class="text-muted small">Status: <strong>{{ $approvalStatusLabel }}</strong></div>
        @if ($approvalRequesterLabel)
          <div class="text-muted small">Diajukan oleh: {{ $approvalRequesterLabel }}</div>
        @endif
        @if ($approvalApprovedLabel)
          <div class="text-muted small">Disetujui oleh: {{ $approvalApprovedLabel }}</div>
        @endif
        @if ($approvalRejectedLabel)
          <div class="text-muted small">Ditolak oleh: {{ $approvalRejectedLabel }}</div>
        @endif
        @if ($pendingStepNo)
          <div class="text-muted small">Menunggu Step {{ $pendingStepNo }}{{ $pendingApproverLabel ? ' - ' . $pendingApproverLabel : '' }}</div>
        @endif
        @if (!empty($approvalRequest?->rejected_note))
          <div class="text-muted small">Catatan: {{ $approvalRequest->rejected_note }}</div>
        @endif
      </div>
      <div class="text-end">
        <a class="btn btn-outline-primary btn-sm" href="{{ route('payroll.pph21_approval', ['period_id' => $periodId]) }}">Approval List</a>
      </div>
    </div>

    <div class="mt-3">
      @if (!$approvalRequest || ($approvalRequest->status ?? '') === 'Rejected')
        <form method="post">
          @csrf
          <input type="hidden" name="action" value="submit">
          <input type="hidden" name="period_id" value="{{ $periodId }}">
          <button class="btn btn-primary btn-sm" type="submit">Ajukan Approval</button>
        </form>
      @elseif (($approvalRequest->status ?? '') === 'Approved')
        <div class="text-success small">Approval sudah selesai.</div>
      @else
        @if ($canApprove)
          <form method="post" class="row g-2 align-items-end">
            @csrf
            <input type="hidden" name="period_id" value="{{ $periodId }}">
            <div class="col-md-6">
              <label class="form-label">Catatan (opsional)</label>
              <input type="text" class="form-control form-control-sm" name="note" maxlength="255">
            </div>
            <div class="col-md-6">
              <div class="d-flex gap-2">
                <button class="btn btn-success btn-sm" type="submit" name="action" value="approve_step">Approve</button>
                <button class="btn btn-outline-danger btn-sm" type="submit" name="action" value="reject" onclick="return confirm('Tolak approval payroll PPh21?');">Reject</button>
              </div>
            </div>
          </form>
        @else
          <div class="text-muted small">Menunggu approval dari approver terkait.</div>
        @endif
      @endif
    </div>
  </div>
</div>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h4 class="mb-1">Modul PPh21</h4>
    <div class="text-muted small">Simulasi dan rekonsiliasi PPh21 berbasis payroll per periode.</div>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      @if (current_user_has_global_scope($user))
      <div class="col-md-4">
        <label class="form-label">Company</label>
        <select class="form-select" name="set_company" onchange="this.form.submit()">
          @foreach ($companies as $company)
            <option value="{{ $company->id }}" {{ (int) $companyId === (int) $company->id ? 'selected' : '' }}>{{ $company->company_name }}</option>
          @endforeach
        </select>
      </div>
      @endif
      <div class="col-md-4">
        <label class="form-label">Periode Payroll</label>
        <select class="form-select" name="period_id">
          @foreach ($periods as $period)
            <option value="{{ $period->id }}" {{ (int) $periodId === (int) $period->id ? 'selected' : '' }}>
              {{ sprintf('%02d/%04d', (int) $period->month, (int) $period->year) }} - {{ $period->status }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Detail Karyawan</label>
        <select class="form-select" name="employee_id">
          <option value="0">Semua Karyawan</option>
          @foreach ($rows as $row)
            <option value="{{ $row['employee_id'] }}" {{ (int) $employeeId === (int) $row['employee_id'] ? 'selected' : '' }}>
              {{ $row['nik'] }} - {{ $row['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-12 d-flex justify-content-end">
        <button class="btn btn-primary" type="submit">Tampilkan</button>
      </div>
    </form>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small">Karyawan</div>
        <div class="fs-4 fw-semibold">{{ number_format($summary['employees'], 0, ',', '.') }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small">Bruto Bulanan</div>
        <div class="fs-5 fw-semibold">{{ format_currency($summary['total_bruto']) }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small">PPh21 Payroll</div>
        <div class="fs-5 fw-semibold">{{ format_currency($summary['total_pph21_actual']) }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small">Selisih</div>
        <div class="fs-5 fw-semibold {{ $summary['total_variance'] == 0.0 ? '' : 'text-danger' }}">{{ format_currency($summary['total_variance']) }}</div>
      </div>
    </div>
  </div>
</div>

@if ($selected)
<div class="card shadow-sm mb-3">
  <div class="card-header fw-semibold">Detail PPh21 Karyawan</div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-4">
        <div><strong>NIK:</strong> {{ $selected['nik'] }}</div>
        <div><strong>Nama:</strong> {{ $selected['name'] }}</div>
        <div><strong>Jabatan:</strong> {{ $selected['position'] ?: '-' }}</div>
      </div>
      <div class="col-md-4">
        <div><strong>PTKP:</strong> {{ $selected['ptkp_status'] }}</div>
        <div><strong>Kategori TER:</strong> {{ $selected['ter_category'] }}</div>
        <div><strong>Tarif TER:</strong> {{ number_format($selected['ter_rate'] * 100, 2) }}%</div>
      </div>
      <div class="col-md-4">
        <div><strong>Bruto Bulan Ini:</strong> {{ format_currency($selected['bruto_monthly']) }}</div>
        <div><strong>PPh21 Expected:</strong> {{ format_currency($selected['pph21_expected']) }}</div>
        <div><strong>PPh21 Payroll:</strong> {{ format_currency($selected['pph21_actual']) }}</div>
      </div>
      <div class="col-md-3">
        <div class="text-muted small">Bruto YTD</div>
        <div class="fw-semibold">{{ format_currency($selected['bruto_ytd']) }}</div>
      </div>
      <div class="col-md-3">
        <div class="text-muted small">JHT YTD</div>
        <div class="fw-semibold">{{ format_currency($selected['jht_ytd']) }}</div>
      </div>
      <div class="col-md-3">
        <div class="text-muted small">JP YTD</div>
        <div class="fw-semibold">{{ format_currency($selected['jp_ytd']) }}</div>
      </div>
      <div class="col-md-3">
        <div class="text-muted small">PKP</div>
        <div class="fw-semibold">{{ format_currency($selected['pkp']) }}</div>
      </div>
      <div class="col-md-4">
        <div class="text-muted small">PPh21 TER Bulan Ini</div>
        <div class="fw-semibold">{{ format_currency($selected['pph21_ter']) }}</div>
      </div>
      <div class="col-md-4">
        <div class="text-muted small">PPh21 Tahunan Progresif</div>
        <div class="fw-semibold">{{ format_currency($selected['annual_pph21']) }}</div>
      </div>
      <div class="col-md-4">
        <div class="text-muted small">Rekonsiliasi Desember</div>
        <div class="fw-semibold">{{ format_currency($selected['december_adjustment']) }}</div>
      </div>
    </div>
  </div>
</div>
@endif

<div class="card shadow-sm">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>NIK</th>
            <th>Nama</th>
            <th>PTKP</th>
            <th>Kategori TER</th>
            <th class="text-end">Bruto</th>
            <th class="text-end">Tarif TER</th>
            <th class="text-end">Expected</th>
            <th class="text-end">Payroll</th>
            <th class="text-end">Selisih</th>
            <th class="text-end">Bruto YTD</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($rows as $row)
          <tr>
            <td>{{ $row['nik'] }}</td>
            <td>{{ $row['name'] }}</td>
            <td>{{ $row['ptkp_status'] }}</td>
            <td>{{ $row['ter_category'] }}</td>
            <td class="text-end">{{ format_currency_id($row['bruto_monthly'], 2, false) }}</td>
            <td class="text-end">{{ number_format($row['ter_rate'] * 100, 2) }}%</td>
            <td class="text-end">{{ format_currency_id($row['pph21_expected'], 2, false) }}</td>
            <td class="text-end">{{ format_currency_id($row['pph21_actual'], 2, false) }}</td>
            <td class="text-end {{ $row['pph21_variance'] == 0.0 ? '' : 'text-danger fw-semibold' }}">{{ format_currency_id($row['pph21_variance'], 2, false) }}</td>
            <td class="text-end">{{ format_currency_id($row['bruto_ytd'], 2, false) }}</td>
            <td class="text-end">
              <a class="btn btn-outline-secondary btn-sm" href="{{ route('payroll.pph21', ['period_id' => $periodId, 'employee_id' => $row['employee_id']]) }}">Detail</a>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="11" class="text-center text-muted py-4">Belum ada data payroll untuk periode ini.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
