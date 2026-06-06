@extends('layouts.app')

@section('content')
<h4 class="mb-3">Perizinan - Lembur</h4>

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
  <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#overtimeForm" aria-expanded="false" aria-controls="overtimeForm">
    Ajukan Lembur
  </button>
</div>
<div class="collapse mb-3 show" id="overtimeForm">
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
              <input id="overtimeStart" type="text" class="form-control js-time-24" name="time_start" required placeholder="HH:MM" inputmode="numeric" pattern="^([01]\d|2[0-3]):[0-5]\d$" title="Format 24 jam (HH:MM)">
              <button class="btn btn-outline-secondary js-time-picker-btn" type="button" data-target="#overtimeStart" aria-label="Pilih jam">
                <span class="icon-clock" aria-hidden="true"></span>
              </button>
            </div>
          </div>
          <div class="col-md-2">
            <label class="form-label">Jam Selesai</label>
            <div class="input-group">
              <input id="overtimeEnd" type="text" class="form-control js-time-24" name="time_end" required placeholder="HH:MM" inputmode="numeric" pattern="^([01]\d|2[0-3]):[0-5]\d$" title="Format 24 jam (HH:MM)">
              <button class="btn btn-outline-secondary js-time-picker-btn" type="button" data-target="#overtimeEnd" aria-label="Pilih jam">
                <span class="icon-clock" aria-hidden="true"></span>
              </button>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Alasan</label>
            <input type="text" class="form-control" name="reason" placeholder="Alasan lembur">
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
    <form class="mb-3" method="get" id="overtimeFilterForm">
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
              $statusOptions = ['','Pending Approval 1','Pending Approval 2','Pending Approval 3','Pending Approval 4','Pending Approval 5','Approved','Rejected'];
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
          <a class="btn btn-outline-secondary w-100" href="{{ route('permissions.overtime') }}">Reset</a>
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
              @php $firstStatus = $firstStepStatus[$item->id] ?? null; @endphp
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
              @php $lastStatus = $lastStepStatus[$item->id] ?? null; @endphp
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
              <a class="icon-btn icon-detail" title="Preview Surat" href="{{ route('permissions.overtime_preview', ['id' => $item->id]) }}" target="_blank">
                <span class="icon i-eye" aria-hidden="true"></span>
              </a>
              @if ($item->status === 'Approved')
                <a class="icon-btn icon-download" title="Download PDF" href="{{ route('permissions.overtime_pdf', ['id' => $item->id]) }}">
                  <span class="icon i-download" aria-hidden="true"></span>
                </a>
              @endif
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
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal"
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

<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        @csrf
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="id" id="rejectId">
        <div class="modal-header">
          <h5 class="modal-title">Reject Lembur</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Employee</label>
            <div id="rejectEmployee">-</div>
          </div>
          <div class="mb-2">
            <label class="form-label">Alasan Reject</label>
            <textarea class="form-control" name="note" rows="3" placeholder="Tulis alasan reject..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger">Reject</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="rejectNoteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Alasan Reject</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Employee</label>
          <div id="rejectDetailEmployee">-</div>
        </div>
        <div class="mb-2">
          <label class="form-label">Alasan</label>
          <div id="rejectDetailNote">-</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    var form = document.getElementById('overtimeFilterForm');
    if (form) {
      form.querySelectorAll('.auto-filter').forEach(function (el) {
        el.addEventListener('change', function () {
          form.submit();
        });
      });
    }
    var rejectModal = document.getElementById('rejectModal');
    if (rejectModal) {
      rejectModal.addEventListener('show.bs.modal', function (e) {
        var btn = e.relatedTarget;
        document.getElementById('rejectId').value = btn.getAttribute('data-id');
        document.getElementById('rejectEmployee').textContent = btn.getAttribute('data-employee') || '-';
      });
    }
    var detailModal = document.getElementById('rejectNoteModal');
    if (detailModal) {
      detailModal.addEventListener('show.bs.modal', function (e) {
        var btn = e.relatedTarget;
        document.getElementById('rejectDetailEmployee').textContent = btn.getAttribute('data-employee') || '-';
        document.getElementById('rejectDetailNote').textContent = btn.getAttribute('data-note') || '-';
      });
    }
  })();
</script>
@endsection
