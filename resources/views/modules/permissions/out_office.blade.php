@extends('layouts.app')

@section('content')
<h4 class="mb-3">Perizinan - Izin Keluar Kantor</h4>

@if (current_user_has_global_scope($user))
<form class="mb-3" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label">Company</label>
      <select class="form-select" name="set_company" onchange="this.form.submit()">
        @foreach (\App\Models\Company::orderBy('id')->get() as $c)
          <option value="{{ $c->id }}" {{ $companyId == $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
        @endforeach
      </select>
    </div>
  </div>
</form>
@endif

<div class="d-flex gap-2 flex-wrap mb-3">
  <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#outOfficeForm" aria-expanded="false" aria-controls="outOfficeForm">
    Ajukan Izin Keluar
  </button>
</div>
<div class="collapse mb-3 show" id="outOfficeForm">
  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post">
        @csrf
        <input type="hidden" name="action" value="create">
        <div class="row g-2 align-items-end">
          @if (($user['role'] ?? '') !== 'Employee')
          <div class="col-md-4">
            <label class="form-label">Employee</label>
            <select class="form-select" name="employee_id" required>
              <option value="">Pilih Employee</option>
              @foreach ($employees as $e)
                <option value="{{ $e->id }}">{{ $e->name }} ({{ $e->nik }})</option>
              @endforeach
            </select>
          </div>
          @endif
          <div class="col-md-2">
            <label class="form-label">Tanggal</label>
            <input type="date" class="form-control" name="date" required>
          </div>
          <div class="col-md-2">
            <label class="form-label">Jam Mulai</label>
            <div class="input-group">
              <input id="outOfficeStart" type="text" class="form-control js-time-24" name="time_start" required placeholder="HH:MM" inputmode="numeric" pattern="^([01]\d|2[0-3]):[0-5]\d$" title="Format 24 jam (HH:MM)">
              <button class="btn btn-outline-secondary js-time-picker-btn" type="button" data-target="#outOfficeStart" aria-label="Pilih jam">
                <span class="icon-clock" aria-hidden="true"></span>
              </button>
            </div>
          </div>
          <div class="col-md-2">
            <label class="form-label">Jam Selesai</label>
            <div class="input-group">
              <input id="outOfficeEnd" type="text" class="form-control js-time-24" name="time_end" required placeholder="HH:MM" inputmode="numeric" pattern="^([01]\d|2[0-3]):[0-5]\d$" title="Format 24 jam (HH:MM)">
              <button class="btn btn-outline-secondary js-time-picker-btn" type="button" data-target="#outOfficeEnd" aria-label="Pilih jam">
                <span class="icon-clock" aria-hidden="true"></span>
              </button>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Tujuan</label>
            <input type="text" class="form-control" name="destination" placeholder="Tujuan singkat">
          </div>
          <div class="col-md-6">
            <label class="form-label">Alasan</label>
            <input type="text" class="form-control" name="reason" placeholder="Alasan singkat">
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-success w-100">Ajukan</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <form class="mb-3" method="get" id="outOfficeFilterForm">
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Employee</label>
          <select class="form-select auto-filter" name="employee_id">
            <option value="">Semua</option>
            @foreach ($employees as $e)
              <option value="{{ $e->id }}" {{ (int) $filterEmployeeId === (int) $e->id ? 'selected' : '' }}>
                {{ $e->name }} ({{ $e->nik }})
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select class="form-select auto-filter" name="status">
            @php
              $statusOptions = ['','Pending Atasan','Pending Approval 1','Pending Approval 2','Pending Approval 3','Pending Approval 4','Pending Approval 5','Pending HRD','Approved','Rejected'];
            @endphp
            @foreach ($statusOptions as $opt)
              <option value="{{ $opt }}" {{ $filterStatus === $opt ? 'selected' : '' }}>
                {{ $opt === '' ? 'Semua' : $opt }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Periode Mulai</label>
          <input type="date" class="form-control auto-filter" name="from" value="{{ $filterFrom }}">
        </div>
        <div class="col-md-2">
          <label class="form-label">Periode Selesai</label>
          <input type="date" class="form-control auto-filter" name="to" value="{{ $filterTo }}">
        </div>
        <div class="col-md-1">
          <a class="btn btn-outline-secondary w-100" href="{{ route('permissions.out_office') }}">Reset</a>
        </div>
      </div>
    </form>
    <table class="table table-striped table-sm">
      <thead>
        <tr>
          <th>Employee</th>
          <th>Tanggal</th>
          <th>Jam</th>
          <th>Status</th>
          <th>Ttd Appv 1</th>
          <th>Ttd Appv Final</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($items as $item)
          <tr>
            @php $emp = $employeesById[$item->employee_id] ?? null; @endphp
            <td>{{ $emp ? ($emp->name . ' (' . $emp->nik . ')') : $item->employee_id }}</td>
            <td>{{ format_date_id($item->date) }}</td>
            <td>{{ format_time_id($item->time_start) }} - {{ format_time_id($item->time_end) }}</td>
            <td>
              {{ $item->status }}
              @if ($item->status === 'Rejected')
                <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-danger reject-detail"
                        data-bs-toggle="modal"
                        data-bs-target="#rejectNoteModal"
                        data-employee="{{ $emp ? ($emp->name . ' (' . $emp->nik . ')') : $item->employee_id }}"
                        data-note="{{ $item->rejected_note ?? '-' }}">
                  Lihat Alasan
                </button>
              @endif
            </td>
            <td>
              @php
                $firstStatus = $firstStepStatus[$item->id] ?? null;
              @endphp
              @if ($firstStatus === 'Approved')
                <span class="text-success fw-semibold">Approved</span>
              @elseif ($firstStatus === 'Rejected')
                <span class="text-danger fw-semibold">Reject</span>
              @elseif ($item->atasan_signature || $item->atasan_approved_by)
                <span class="text-success fw-semibold">Approved</span>
              @elseif ($item->status === 'Approved')
                <span class="text-success fw-semibold">Approved</span>
              @elseif ($item->status === 'Rejected')
                <span class="text-danger fw-semibold">Reject</span>
              @else
                -
              @endif
            </td>
            <td>
              @php
                $lastStatus = $lastStepStatus[$item->id] ?? null;
              @endphp
              @if ($lastStatus === 'Approved')
                <span class="text-success fw-semibold">Approved</span>
              @elseif ($lastStatus === 'Rejected')
                <span class="text-danger fw-semibold">Reject</span>
              @elseif ($item->hrd_signature || $item->hrd_approved_by)
                <span class="text-success fw-semibold">Approved</span>
              @elseif ($item->status === 'Approved')
                <span class="text-success fw-semibold">Approved</span>
              @elseif ($item->status === 'Rejected')
                <span class="text-danger fw-semibold">Reject</span>
              @else
                -
              @endif
            </td>
            <td class="text-end">
              <a class="icon-btn icon-detail" title="Preview Surat" href="{{ route('permissions.out_office_preview', ['id' => $item->id]) }}" target="_blank">
                <span class="icon i-eye" aria-hidden="true"></span>
              </a>
              @if ($item->status === 'Approved')
                <a class="icon-btn icon-download" title="Download PDF" href="{{ route('permissions.out_office_pdf', ['id' => $item->id]) }}">
                  <span class="icon i-download" aria-hidden="true"></span>
                </a>
              @endif
              @php
                $resolvedRequesterId = (int) ($item->requester_user_id ?? 0);
                if ($resolvedRequesterId <= 0) {
                  $resolvedRequesterId = (int) ($userIdByEmployeeId[$item->employee_id] ?? 0);
                }
                $approval = $approvalMap[$resolvedRequesterId] ?? null;
                $canApprove1 = $approval
                  ? (int) ($approval->approver1_user_id ?? 0) === (int) ($user['id'] ?? 0)
                  : (($user['role'] ?? '') !== 'Employee');
                $canApprove2 = $approval
                  ? (int) ($approval->approver2_user_id ?? 0) === (int) ($user['id'] ?? 0)
                  : in_array(($user['role'] ?? ''), ['HR','HR1','HR2','Super Admin'], true);
                $pendingStep1 = in_array($item->status, ['Pending Approval 1', 'Pending Atasan'], true);
                $pendingStep2 = in_array($item->status, ['Pending Approval 2', 'Pending HRD'], true);
              @endphp
              @php
                $currentStepNo = $pendingStepNo[$item->id] ?? null;
                $currentApproverId = (int) ($pendingApproverId[$item->id] ?? 0);
                $canApproveCurrent = false;
                if ($currentStepNo) {
                  if ($currentApproverId > 0) {
                    $canApproveCurrent = (int) ($user['id'] ?? 0) === $currentApproverId;
                  } else {
                    $canApproveCurrent = $currentStepNo === 1
                      ? (($user['role'] ?? '') !== 'Employee')
                      : in_array(($user['role'] ?? ''), ['HR','HR1','HR2','Super Admin'], true);
                  }
                }
              @endphp
              @if ($currentStepNo && $canApproveCurrent)
                <form method="post" class="d-inline">
                  @csrf
                  <input type="hidden" name="action" value="approve_step">
                  <input type="hidden" name="id" value="{{ $item->id }}">
                  <button class="btn btn-sm btn-success">Approve</button>
                </form>
              @endif
              @if ($currentStepNo && $canApproveCurrent)
                <button type="button" class="btn btn-sm btn-outline-danger reject-action"
                        data-bs-toggle="modal"
                        data-bs-target="#rejectInputModal"
                        data-id="{{ $item->id }}"
                        data-employee="{{ $emp ? ($emp->name . ' (' . $emp->nik . ')') : $item->employee_id }}">
                  Reject
                </button>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="text-muted">Belum ada pengajuan.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<script>
  (function () {
    var form = document.getElementById('outOfficeFilterForm');
    if (!form) return;
    var fields = form.querySelectorAll('.auto-filter');
    fields.forEach(function (el) {
      el.addEventListener('change', function () {
        form.submit();
      });
    });
  })();

  window.addEventListener('DOMContentLoaded', function () {
    var noteModal = document.getElementById('rejectNoteModal');
    if (noteModal) {
      noteModal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        if (!btn) return;
        var note = btn.getAttribute('data-note') || '-';
        var emp = btn.getAttribute('data-employee') || '-';
        var noteEl = noteModal.querySelector('[data-role="reject-note"]');
        var empEl = noteModal.querySelector('[data-role="reject-employee"]');
        if (noteEl) noteEl.textContent = note;
        if (empEl) empEl.textContent = emp;
      });
    }

    var inputModal = document.getElementById('rejectInputModal');
    if (inputModal) {
      inputModal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        if (!btn) return;
        var id = btn.getAttribute('data-id') || '';
        var emp = btn.getAttribute('data-employee') || '-';
        var idInput = inputModal.querySelector('[data-role="reject-id"]');
        var empEl = inputModal.querySelector('[data-role="reject-employee"]');
        if (idInput) idInput.value = id;
        if (empEl) empEl.textContent = emp;
      });
    }
  });
</script>
@endsection

@section('modals')
<div class="modal fade" id="rejectNoteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Alasan Reject</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="small text-muted mb-1">Employee</div>
        <div class="fw-semibold mb-3" data-role="reject-employee">-</div>
        <div class="small text-muted mb-1">Alasan</div>
        <div data-role="reject-note">-</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="rejectInputModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        @csrf
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="id" data-role="reject-id">
        <div class="modal-header">
          <h5 class="modal-title">Reject Perizinan</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="small text-muted mb-1">Employee</div>
          <div class="fw-semibold mb-3" data-role="reject-employee">-</div>
          <label class="form-label">Alasan Reject</label>
          <textarea class="form-control" name="note" rows="3" placeholder="Tulis alasan reject..." required></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger">Reject</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
