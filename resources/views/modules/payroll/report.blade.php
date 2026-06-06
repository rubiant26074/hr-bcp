@extends('layouts.app')

@section('content')
<h4 class="mb-3">Payroll Report</h4>
@if (request()->query('approval_submitted'))
  <div class="alert alert-success">Pengajuan approval payroll report berhasil dikirim.</div>
@endif
@if ($errors->has('approval'))
  <div class="alert alert-danger">{{ $errors->first('approval') }}</div>
@endif
@if ($errors->has('bank'))
  <div class="alert alert-danger">{{ $errors->first('bank') }}</div>
@endif

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between gap-3">
      <div>
        <div class="fw-semibold">Approval Payroll Report</div>
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
        @if ($canExport)
          <span class="badge bg-success">Export diizinkan</span>
        @else
          <span class="badge bg-secondary">Menunggu approval</span>
        @endif
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
                <button class="btn btn-outline-danger btn-sm" type="submit" name="action" value="reject" onclick="return confirm('Tolak approval payroll report?');">Reject</button>
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
      <label class="form-label">Period</label>
      <select class="form-select" name="period_id" onchange="this.form.submit()">
        @foreach ($periods as $p)
          <option value="{{ $p->id }}" {{ (int)$periodId === (int)$p->id ? 'selected' : '' }}>{{ $p->month }}/{{ $p->year }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-5">
      <label class="form-label">Export</label>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-success btn-sm" href="{{ route('payroll.report', ['period_id' => $periodId, 'format' => 'excel']) }}">Export Excel (Breakdown)</a>
        <a class="btn btn-outline-success btn-sm" href="{{ route('payroll.report', ['period_id' => $periodId, 'format' => 'pdf']) }}">Export PDF (Breakdown)</a>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('payroll.report_approval', ['period_id' => $periodId]) }}">Approval List</a>
      </div>
      <div class="text-muted small mt-1">Export Excel/PDF selalu aktif. Download file transfer bank tetap perlu approval.</div>
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
          <th>Gaji Pokok</th>
          <th>Total Penerimaan</th>
          <th>Total Potongan</th>
          <th>Gaji Bersih</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($items as $i)
          <tr>
            <td>{{ $i->nik }}</td>
            <td>{{ $i->name }}</td>
            <td>{{ format_currency($i->basic_salary) }}</td>
            <td>{{ format_currency($i->total_penerimaan) }}</td>
            <td>{{ format_currency($i->total_potongan) }}</td>
            <td>{{ format_currency($i->gaji_bersih) }}</td>
          </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr>
          <th colspan="2" class="text-start">TOTAL</th>
          <th>{{ format_currency($totalBasic) }}</th>
          <th>{{ format_currency($totalIncome) }}</th>
          <th>{{ format_currency($totalDeduct) }}</th>
          <th>{{ format_currency($totalNet) }}</th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<div class="card shadow-sm mt-3">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-2">
      <div>
        <div class="fw-semibold">Payroll Bank Transfer</div>
        <div class="text-muted small">Bank: {{ $bankCompanyName ?: ($bankType ?? '-') }}</div>
        <div class="text-muted small">Valid: {{ count($bankRows) }} • Invalid: {{ count($bankInvalids) }}</div>
        <div class="text-muted small">Total Amount: {{ format_currency($bankTotalAmount) }}</div>
      </div>
      <div class="text-end">        @if (($bankType ?? '') === 'BNI')
          <form method="post" class="d-inline">
            @csrf
            <input type="hidden" name="action" value="download_bni">
            <input type="hidden" name="period_id" value="{{ $periodId }}">
            <button class="btn btn-success btn-sm" type="submit" {{ ($approvalRequest?->status ?? '') === 'Approved' ? '' : 'disabled' }}>Download CSV BNI (Inhouse)</button>
          </form>
          <div class="text-muted small mt-1">Rek. Debet: {{ $bankDebitAccount ?: '-' }} | Remark: {{ $bankRemarkDefault }}</div>
          @if (($approvalRequest?->status ?? '') !== 'Approved')
            <div class="text-muted small mt-1">Download aktif setelah approval Payroll Report disetujui.</div>
          @endif
        @elseif (($bankType ?? '') === 'BSI')
          <form method="post" class="d-inline">
            @csrf
            <input type="hidden" name="action" value="download_bsi">
            <input type="hidden" name="period_id" value="{{ $periodId }}">
            <button class="btn btn-success btn-sm" type="submit" {{ ($approvalRequest?->status ?? '') === 'Approved' ? '' : 'disabled' }}>Download TXT BSI (Payroll Multi Service)</button>
          </form>
          <div class="text-muted small mt-1">Rek. Debet: {{ $bankDebitAccount ?: '-' }}</div>
          <div class="text-muted small mt-1">Format: Payroll Multi Service (TXT)</div>
          @if (($approvalRequest?->status ?? '') !== 'Approved')
            <div class="text-muted small mt-1">Download aktif setelah approval Payroll Report disetujui.</div>
          @endif
        @else
          <button class="btn btn-secondary btn-sm" type="button" disabled>Download bank belum tersedia untuk bank ini</button>
        @endif
      </div>
    </div>

    <div class="row g-3">
      <div class="col-md-6">
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
              @forelse ($bankRows as $r)
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
      <div class="col-md-6">
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
              @forelse ($bankInvalids as $r)
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
  </div>
</div>
@endsection
