@extends('layouts.app')

@section('content')
<h4 class="mb-3">Perizinan - Tidak Masuk Kerja (Izin/Cuti/Sakit)</h4>

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
  <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#absenceForm" aria-expanded="false" aria-controls="absenceForm">
    Ajukan Perizinan
  </button>
</div>
<div class="collapse mb-3 show" id="absenceForm">
  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="force_izin" id="forceIzinFlag" value="0">
        @if (($user['role'] ?? '') === 'Employee')
          <input type="hidden" id="currentEmployeeMeta"
                 data-cuti="{{ $selfCutiBalance !== null ? $selfCutiBalance : ($cutiBalances[$selfEmployeeIdResolved ?? 0] ?? $cutiQuota) }}"
                 data-eligible="{{ $selfCutiEligible !== null ? ($selfCutiEligible ? '1' : '0') : (!empty($cutiEligibility[$selfEmployeeIdResolved ?? 0]) ? '1' : '0') }}">
          <input type="hidden" id="currentEmployeeEligible" value="{{ $selfCutiEligible !== null ? ($selfCutiEligible ? '1' : '0') : '0' }}">
          <input type="hidden" id="currentEmployeeEligibleReason" value="{{ $selfCutiReason ?? '' }}">
        @endif
        <div class="row g-2 align-items-end">
          @if (($user['role'] ?? '') !== 'Employee')
          <div class="col-md-4">
            <label class="form-label">Employee</label>
            <select class="form-select" name="employee_id" required>
              <option value="">Pilih Employee</option>
              @foreach ($employees as $e)
                <option value="{{ $e->id }}"
                        data-cuti="{{ $cutiBalances[$e->id] ?? $cutiQuota }}"
                        data-eligible="{{ !empty($cutiEligibility[$e->id]) ? '1' : '0' }}">
                  {{ $e->name }} ({{ $e->nik }})
                </option>
              @endforeach
            </select>
          </div>
          @endif
          <div class="col-md-3">
            <label class="form-label">Jenis</label>
            <select class="form-select" name="request_type" required>
              @foreach (['Izin','Cuti','Cuti Khusus','Sakit'] as $t)
                <option value="{{ $t }}">{{ $t }}</option>
              @endforeach
            </select>
            <div class="form-text" id="cutiInfo">Sisa cuti: -</div>
          </div>
          <div class="col-md-2">
            <label class="form-label">Tanggal Mulai</label>
            <input type="date" class="form-control" name="date_start" required>
          </div>
          <div class="col-md-2">
            <label class="form-label">Tanggal Selesai</label>
            <input type="date" class="form-control" name="date_end" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Alasan</label>
            <input type="text" class="form-control" name="reason" placeholder="Alasan singkat">
          </div>
          <div class="col-md-4" id="doctorNoteWrap" style="display:none;">
            <label class="form-label">Surat Dokter</label>
            <input type="file" class="form-control" name="doctor_note_file" accept=".jpg,.jpeg,.png,.pdf">
            <div class="form-text">Wajib untuk izin sakit.</div>
          </div>
          <div class="col-md-4" id="specialAttachmentWrap" style="display:none;">
            <label class="form-label">Lampiran Dokumen</label>
            <input type="file" class="form-control" name="special_attachment_file" accept=".jpg,.jpeg,.png,.pdf">
            <div class="form-text">Wajib untuk cuti khusus.</div>
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
      <form class="mb-3" method="get" id="absenceFilterForm">
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
            <a class="btn btn-outline-secondary w-100" href="{{ route('permissions.absence') }}">Reset</a>
          </div>
        </div>
      </form>
      <table class="table table-striped table-sm">
      <thead>
        <tr>
          <th>Employee</th>
          <th>Jenis</th>
          <th>Periode</th>
          <th>Status</th>
          <th>Ttd Appv 1</th>
          <th>Ttd Appv Final</th>
          <th>Lampiran</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($items as $item)
          <tr>
            @php $emp = $employeesById[$item->employee_id] ?? null; @endphp
            <td>{{ $emp ? ($emp->name . ' (' . $emp->nik . ')') : $item->employee_id }}</td>
            <td>{{ $item->request_type }}</td>
            <td>{{ format_date_id($item->date_start) }} - {{ format_date_id($item->date_end) }}</td>
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
            <td>
              @if (!empty($item->doctor_note_path))
                <a href="{{ asset_url($item->doctor_note_path) }}" target="_blank">Surat Dokter</a>
              @elseif (!empty($item->attachment_path))
                <a href="{{ asset_url($item->attachment_path) }}" target="_blank">Lampiran</a>
              @else
                -
              @endif
            </td>
            <td class="text-end">
              <a class="icon-btn icon-detail" title="Preview Surat" href="{{ route('permissions.absence_preview', ['id' => $item->id]) }}" target="_blank">
                <span class="icon i-eye" aria-hidden="true"></span>
              </a>
              @if ($item->status === 'Approved')
                <a class="icon-btn icon-download" title="Download PDF" href="{{ route('permissions.absence_pdf', ['id' => $item->id]) }}">
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
            <td colspan="8" class="text-muted">Belum ada pengajuan.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<script>
  (function () {
    var typeSelect = document.querySelector('select[name="request_type"]');
    var doctorWrap = document.getElementById('doctorNoteWrap');
    var specialWrap = document.getElementById('specialAttachmentWrap');
    if (!typeSelect || !doctorWrap || !specialWrap) return;
    var doctorInput = doctorWrap.querySelector('input[type="file"]');
    var specialInput = specialWrap.querySelector('input[type="file"]');

    function toggleDoctor() {
      var val = String(typeSelect.value || '').toUpperCase().trim();
      doctorWrap.style.display = (val === 'SAKIT') ? '' : 'none';
      specialWrap.style.display = (val === 'CUTI KHUSUS') ? '' : 'none';
      if (doctorInput) doctorInput.required = (val === 'SAKIT');
      if (specialInput) specialInput.required = (val === 'CUTI KHUSUS');
    }

    typeSelect.addEventListener('change', toggleDoctor);
    toggleDoctor();
  })();

  (function () {
    var form = document.getElementById('absenceFilterForm');
    if (!form) return;
    var fields = form.querySelectorAll('.auto-filter');
    fields.forEach(function (el) {
      el.addEventListener('change', function () {
        form.submit();
      });
    });
  })();

  window.addEventListener('DOMContentLoaded', function () {
    var typeSelect = document.querySelector('select[name="request_type"]');
    var employeeSelect = null;
    var currentMeta = document.getElementById('currentEmployeeMeta');
    var selfEligibleEl = document.getElementById('currentEmployeeEligible');
    var selfEligibleReasonEl = document.getElementById('currentEmployeeEligibleReason');
    var cutiInfo = document.getElementById('cutiInfo');
    var forceIzin = document.getElementById('forceIzinFlag');
    var form = typeSelect ? typeSelect.closest('form') : null;
    if (form) {
      employeeSelect = form.querySelector('select[name="employee_id"]');
    }
    var cutiToIzinBtn = document.getElementById('cutiToIzinBtn');

    function currentEmployeeOption() {
      if (!employeeSelect) return null;
      return employeeSelect.options[employeeSelect.selectedIndex] || null;
    }

    var holidaySet = {};
    @if (!empty($holidayDates))
      @foreach ($holidayDates as $hd)
        holidaySet['{{ $hd }}'] = true;
      @endforeach
    @endif

    function getRemaining() {
      var opt = currentEmployeeOption();
      if (!opt) {
        if (currentMeta) return parseInt(currentMeta.getAttribute('data-cuti') || '0', 10);
        return {{ ($user['role'] ?? '') === 'Employee' ? (int) ($cutiBalances[$user['employee_id'] ?? 0] ?? $cutiQuota) : $cutiQuota }};
      }
      return parseInt(opt.getAttribute('data-cuti') || '0', 10);
    }

    function isEligible() {
      var opt = currentEmployeeOption();
      if (!opt) {
        if (selfEligibleEl) return selfEligibleEl.value === '1';
        if (currentMeta) return currentMeta.getAttribute('data-eligible') === '1';
        return true;
      }
      return opt.getAttribute('data-eligible') === '1';
    }

    function updateInfo() {
      if (!cutiInfo || !typeSelect) return;
      if (String(typeSelect.value).toUpperCase() === 'CUTI') {
        cutiInfo.textContent = 'Sisa cuti: ' + getRemaining() + ' hari';
      } else {
        cutiInfo.textContent = 'Sisa cuti: -';
      }
    }

    if (employeeSelect) {
      employeeSelect.addEventListener('change', updateInfo);
    }
    if (typeSelect) {
      typeSelect.addEventListener('change', updateInfo);
    }
    updateInfo();

    if (form && typeSelect) {
      form.addEventListener('submit', function (e) {
        if (String(typeSelect.value).toUpperCase() !== 'CUTI') return;
        // Skip frontend check for Employee; rely on server-side validation.
        if (!employeeSelect) return;
        var remaining = getRemaining();
        var startInput = form.querySelector('input[name="date_start"]');
        var endInput = form.querySelector('input[name="date_end"]');
        if (!startInput || !endInput || !startInput.value || !endInput.value) return;
        var start = new Date(startInput.value);
        var end = new Date(endInput.value);
        if (end < start) return;
        var days = 0;
        var cursor = new Date(start.getTime());
        while (cursor <= end) {
          var yyyy = cursor.getFullYear();
          var mm = String(cursor.getMonth() + 1).padStart(2, '0');
          var dd = String(cursor.getDate()).padStart(2, '0');
          var key = yyyy + '-' + mm + '-' + dd;
          if (!holidaySet[key]) {
            days++;
          }
          cursor.setDate(cursor.getDate() + 1);
        }
        if (remaining < days) {
          e.preventDefault();
          if (forceIzin) forceIzin.value = '0';
          var modal2 = document.getElementById('cutiExhaustedModal');
          if (modal2 && window.bootstrap) {
            new bootstrap.Modal(modal2).show();
          }
        }
      });
    }

    if (cutiToIzinBtn) {
      cutiToIzinBtn.addEventListener('click', function () {
        if (forceIzin) forceIzin.value = '1';
        if (typeSelect) typeSelect.value = 'Izin';
        if (form) form.submit();
      });
    }

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
<div class="modal fade" id="cutiNotEligibleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Syarat Cuti</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="cutiNotEligibleReason">
          {{ $selfCutiReason ?? 'Syarat cuti belum terpenuhi.' }}
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="cutiExhaustedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Jatah Cuti</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Jatah Cuti sudah Habis, lanjut izin
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="cutiToIzinBtn">Lanjut Izin</button>
      </div>
    </div>
  </div>
</div>
@endsection
