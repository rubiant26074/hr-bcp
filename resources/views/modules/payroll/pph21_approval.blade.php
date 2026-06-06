@extends('layouts.app')

@section('content')
<h4 class="mb-3">Approval Payroll PPh21</h4>

@foreach ($messages as $m)
  <div class="alert alert-info">{{ $m }}</div>
@endforeach

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center">
      <div>
        <div class="fw-semibold">Approval Menunggu Anda</div>
        <div class="text-muted small">Total pending: <strong>{{ $pendingForUser }}</strong></div>
      </div>
      <div>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('payroll.pph21') }}">Kembali ke Payroll PPh21</a>
      </div>
    </div>
  </div>
</div>

<form class="mb-3" method="get">
  <div class="row g-2 align-items-end">
    @if (current_user_has_global_scope($user))
    <div class="col-md-3">
      <label class="form-label">Company</label>
      <select class="form-select" name="set_company" onchange="this.form.submit()">
        @foreach ($companies as $c)
          <option value="{{ $c->id }}" {{ current_company_id() == $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
        @endforeach
      </select>
    </div>
    @endif
    <div class="col-md-3">
      <label class="form-label">Period</label>
      <select class="form-select" name="period_id" onchange="this.form.submit()">
        <option value="0">Semua Periode</option>
        @foreach ($periods as $p)
          <option value="{{ $p->id }}" {{ (int) $periodId === (int) $p->id ? 'selected' : '' }}>{{ $p->month }}/{{ $p->year }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Status</label>
      <select class="form-select" name="status" onchange="this.form.submit()">
        <option value="" {{ $statusFilter === '' ? 'selected' : '' }}>Semua Status</option>
        <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Pending (Semua)</option>
        <option value="Pending Approval 1" {{ $statusFilter === 'Pending Approval 1' ? 'selected' : '' }}>Pending Approval 1</option>
        <option value="Pending Approval 2" {{ $statusFilter === 'Pending Approval 2' ? 'selected' : '' }}>Pending Approval 2</option>
        <option value="Approved" {{ $statusFilter === 'Approved' ? 'selected' : '' }}>Approved</option>
        <option value="Rejected" {{ $statusFilter === 'Rejected' ? 'selected' : '' }}>Rejected</option>
      </select>
    </div>
  </div>
</form>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="fw-semibold mb-2">Daftar Approval</div>
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead>
          <tr>
            <th>Periode</th>
            <th>Status</th>
            <th>Requester</th>
            <th>Pending Step</th>
            <th>Approver</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($requests as $row)
            @php
              $period = $periodMap[$row->period_id] ?? null;
              $pendingNo = $pendingStepNo[$row->id] ?? null;
              $pendingApprover = $pendingApproverId[$row->id] ?? null;
              $requester = $userMap[$row->requester_user_id] ?? null;
              $approverUser = $pendingApprover ? ($userMap[$pendingApprover] ?? null) : null;
              $canApprove = $pendingApprover && (int) ($user['id'] ?? 0) === (int) $pendingApprover;
            @endphp
            <tr>
              <td>{{ $period ? ($period->month . '/' . $period->year) : $row->period_id }}</td>
              <td>{{ $row->status }}</td>
              <td>{{ $requester ? ($requester->name . ' (' . $requester->email . ')') : ($row->requester_user_id ?? '-') }}</td>
              <td>{{ $pendingNo ? 'Step ' . $pendingNo : '-' }}</td>
              <td>{{ $approverUser ? ($approverUser->name . ' (' . $approverUser->email . ')') : '-' }}</td>
              <td class="text-end">
                @if ($canApprove)
                  <form method="post" class="d-inline">
                    @csrf
                    <input type="hidden" name="id" value="{{ $row->id }}">
                    <button class="btn btn-success btn-sm" name="action" value="approve_step" type="submit">Approve</button>
                  </form>
                  <form method="post" class="d-inline" onsubmit="return confirm('Tolak approval payroll PPh21?');">
                    @csrf
                    <input type="hidden" name="id" value="{{ $row->id }}">
                    <input type="text" class="form-control form-control-sm d-inline-block ms-2" name="note" placeholder="Catatan (opsional)" style="width: 180px;">
                    <button class="btn btn-outline-danger btn-sm ms-2" name="action" value="reject" type="submit">Reject</button>
                  </form>
                @else
                  <span class="text-muted small">-</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-muted">Belum ada pengajuan approval.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="fw-semibold mb-2">Laporan Approval per Periode</div>
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead>
          <tr>
            <th>Periode</th>
            <th>Total</th>
            <th>Pending</th>
            <th>Approved</th>
            <th>Rejected</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($summaryRows as $s)
            @php $p = $periodMap[$s->period_id] ?? null; @endphp
            <tr>
              <td>{{ $p ? ($p->month . '/' . $p->year) : $s->period_id }}</td>
              <td>{{ (int) $s->total }}</td>
              <td>{{ (int) $s->pending_total }}</td>
              <td>{{ (int) $s->approved_total }}</td>
              <td>{{ (int) $s->rejected_total }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-muted">Belum ada data approval.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
