@extends('layouts.app')

@section('content')
<h4 class="mb-3">Approval Settings</h4>

@if (request()->query('saved'))
  <div class="alert alert-success">Pengaturan approval tersimpan.</div>
@endif

@if ($user['role'] === 'Super Admin')
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

<div class="d-flex gap-2 flex-wrap mb-3">
  <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#approvalForm" aria-expanded="false" aria-controls="approvalForm">
    {{ $edit ? 'Edit Approval' : 'Add Approval' }}
  </button>
  <a class="btn btn-outline-secondary" href="{{ route('settings.index') }}">Back</a>
  @if ($edit)
    <a class="btn btn-outline-danger" href="{{ route('settings.approval') }}">Batal Edit</a>
  @endif
</div>
<div class="collapse mb-3 {{ $edit ? 'show' : '' }}" id="approvalForm">
  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post">
        @csrf
        <input type="hidden" name="action" value="{{ $edit ? 'update' : 'save' }}">
        <input type="hidden" name="id" value="{{ $edit->id ?? '' }}">
        <div class="row g-3">
          <div class="col-12">
            <div class="fw-semibold">Approval Flow</div>
            <div class="text-muted small">Urutan approval setelah user mengajukan.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Formulir</label>
            <select class="form-select" name="module_key" required>
              <option value="absence" {{ $moduleKey === 'absence' ? 'selected' : '' }}>Formulir Absen</option>
              <option value="out_office" {{ $moduleKey === 'out_office' ? 'selected' : '' }}>Out of Office</option>
              <option value="overtime" {{ $moduleKey === 'overtime' ? 'selected' : '' }}>Lembur</option>
              <option value="payroll_report" {{ $moduleKey === 'payroll_report' ? 'selected' : '' }}>Payroll Report</option>
              <option value="payroll_pph21" {{ $moduleKey === 'payroll_pph21' ? 'selected' : '' }}>Payroll PPh21</option>
              <option value="dinas_luar" {{ $moduleKey === 'dinas_luar' ? 'selected' : '' }}>Dinas Luar</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Step 1 (User Mengajukan)</label>
            <select class="form-select" name="requester_user_id" required>
              <option value="">Pilih User</option>
              @foreach ($users as $u)
                <option value="{{ $u->id }}" {{ (int) ($requesterUserId ?? 0) === (int) $u->id ? 'selected' : '' }}>
                  {{ $u->name }} ({{ $u->email }})
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="autoFromOrg" name="auto_from_org" value="1" {{ old('auto_from_org') ? 'checked' : '' }}>
              <label class="form-check-label" for="autoFromOrg">Otomatis dari Struktur Organisasi (parent unit)</label>
            </div>
            <div class="text-muted small">Jika aktif, sistem akan menentukan approver: Atasan langsung (parent unit) → HR (role HR).</div>
          </div>
          <div class="col-12">
            <div class="d-flex align-items-center justify-content-between">
              <label class="form-label mb-0">Approval Steps (maks 5)</label>
              <button type="button" class="btn btn-outline-secondary btn-sm" id="addApprovalStep">Add Step</button>
            </div>
          </div>
          <div class="col-12" id="approvalSteps">
            @php
              $stepRows = [];
              foreach ($steps ?? [] as $s) {
                $stepRows[] = (int) ($s->approver_user_id ?? 0);
              }
              if (empty($stepRows) && !empty($setting?->approver1_user_id)) {
                $stepRows[] = (int) $setting->approver1_user_id;
              }
              if (!empty($setting?->approver2_user_id)) {
                $stepRows[] = (int) $setting->approver2_user_id;
              }
            @endphp
            @foreach ($stepRows as $idx => $value)
              <div class="row g-2 align-items-end mb-2 approval-step">
                <div class="col-md-6">
                  <label class="form-label">Step {{ $idx + 2 }} (Approval)</label>
                  <select class="form-select js-approver-input" name="approvers[]" required>
                    <option value="">Pilih Approver</option>
                    @foreach ($users as $u)
                      <option value="{{ $u->id }}" {{ (int) $value === (int) $u->id ? 'selected' : '' }}>
                        {{ $u->name }} ({{ $u->email }})
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-2">
                  <button type="button" class="btn btn-outline-danger w-100 remove-step">Remove</button>
                </div>
              </div>
            @endforeach
            @if (empty($stepRows))
              <div class="row g-2 align-items-end mb-2 approval-step">
                <div class="col-md-6">
                  <label class="form-label">Step 2 (Approval)</label>
                  <select class="form-select js-approver-input" name="approvers[]" required>
                    <option value="">Pilih Approver</option>
                    @foreach ($users as $u)
                      <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-2">
                  <button type="button" class="btn btn-outline-danger w-100 remove-step">Remove</button>
                </div>
              </div>
            @endif
          </div>
        </div>

        <div class="mt-3 d-flex justify-content-end">
          <button class="btn btn-success" type="submit">{{ $edit ? 'Update' : 'Save' }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="card shadow-sm mt-3">
  <div class="card-body">
    <div class="fw-semibold mb-2">Daftar Approval</div>
    <form class="mb-3" method="get">
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Filter Formulir</label>
          <select class="form-select" name="filter_module" onchange="this.form.submit()">
            <option value="all" {{ ($filterModuleKey ?? 'all') === 'all' ? 'selected' : '' }}>Semua formulir</option>
            <option value="absence" {{ ($filterModuleKey ?? '') === 'absence' ? 'selected' : '' }}>Formulir Absen</option>
            <option value="out_office" {{ ($filterModuleKey ?? '') === 'out_office' ? 'selected' : '' }}>Out of Office</option>
            <option value="overtime" {{ ($filterModuleKey ?? '') === 'overtime' ? 'selected' : '' }}>Lembur</option>
            <option value="payroll_report" {{ ($filterModuleKey ?? '') === 'payroll_report' ? 'selected' : '' }}>Payroll Report</option>
            <option value="payroll_pph21" {{ ($filterModuleKey ?? '') === 'payroll_pph21' ? 'selected' : '' }}>Payroll PPh21</option>
            <option value="dinas_luar" {{ ($filterModuleKey ?? '') === 'dinas_luar' ? 'selected' : '' }}>Dinas Luar</option>
          </select>
        </div>
      </div>
    </form>
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead>
          <tr>
            <th>Formulir</th>
            <th>User Mengajukan</th>
            <th>Approval Steps</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($settingsList as $row)
            @php
              $req = $userMap[$row->requester_user_id] ?? null;
              $key = $row->module_key . '|' . $row->requester_user_id;
              $stepUserIds = $stepsListMap[$key] ?? [];
              if (empty($stepUserIds)) {
                if (!empty($row->approver1_user_id)) $stepUserIds[] = (int) $row->approver1_user_id;
                if (!empty($row->approver2_user_id)) $stepUserIds[] = (int) $row->approver2_user_id;
              }
              $stepLabels = [];
              foreach ($stepUserIds as $sid) {
                $u = $userMap[$sid] ?? null;
                $stepLabels[] = $u ? ($u->name . ' (' . $u->email . ')') : $sid;
              }
            @endphp
            <tr>
              <td>
                @if ($row->module_key === 'out_office')
                  Out of Office
                @elseif ($row->module_key === 'overtime')
                  Lembur
                @elseif ($row->module_key === 'payroll_report')
                  Payroll Report
                @elseif ($row->module_key === 'payroll_pph21')
                  Payroll PPh21
                @elseif ($row->module_key === 'dinas_luar')
                  Dinas Luar
                @else
                  Formulir Absen
                @endif
              </td>
              <td>{{ $req ? ($req->name . ' (' . $req->email . ')') : $row->requester_user_id }}</td>
              <td>
                @if (!empty($stepLabels))
                  {{ implode(', ', $stepLabels) }}
                @else
                  -
                @endif
              </td>
              <td class="text-end">
                <a class="icon-btn icon-edit" href="{{ route('settings.approval', ['edit' => $row->id]) }}" title="Edit">
                  <span class="icon i-edit" aria-hidden="true"></span>
                </a>
                <form method="post" class="d-inline" onsubmit="return confirm('Hapus setting approval ini?');">
                  @csrf
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="{{ $row->id }}">
                  <button type="submit" class="icon-btn icon-delete" title="Delete">
                    <span class="icon i-trash" aria-hidden="true"></span>
                  </button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-muted">Belum ada setting approval.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
  (function () {
    var addBtn = document.getElementById('addApprovalStep');
    var container = document.getElementById('approvalSteps');
    var autoToggle = document.getElementById('autoFromOrg');
    if (!addBtn || !container) return;
    var users = @json($users->map(fn($u) => ['id' => $u->id, 'label' => $u->name . ' (' . $u->email . ')'])->values());

    function stepCount() {
      return container.querySelectorAll('.approval-step').length;
    }

    addBtn.addEventListener('click', function () {
      if (stepCount() >= 5) return;
      var idx = stepCount() + 2;
      var wrapper = document.createElement('div');
      wrapper.className = 'row g-2 align-items-end mb-2 approval-step';
      wrapper.innerHTML =
        '<div class="col-md-6">' +
          '<label class="form-label">Step ' + idx + ' (Approval)</label>' +
          '<select class="form-select js-approver-input" name="approvers[]" required>' +
            '<option value="">Pilih Approver</option>' +
          '</select>' +
        '</div>' +
        '<div class="col-md-2">' +
          '<button type="button" class="btn btn-outline-danger w-100 remove-step">Remove</button>' +
        '</div>';

      container.appendChild(wrapper);
      var select = wrapper.querySelector('select');
      var options = '';
      users.forEach(function (u) {
        options += '<option value="' + u.id + '">' + u.label + '</option>';
      });
      select.innerHTML += options;
    });

    container.addEventListener('click', function (e) {
      if (!e.target.classList.contains('remove-step')) return;
      var row = e.target.closest('.approval-step');
      if (row) row.remove();
      container.querySelectorAll('.approval-step').forEach(function (el, i) {
        var label = el.querySelector('label.form-label');
        if (label) label.textContent = 'Step ' + (i + 2) + ' (Approval)';
      });
    });

    function toggleAutoState() {
      var isAuto = autoToggle && autoToggle.checked;
      addBtn.disabled = !!isAuto;
      container.querySelectorAll('.remove-step').forEach(function (btn) {
        btn.disabled = !!isAuto;
      });
      container.querySelectorAll('.js-approver-input').forEach(function (sel) {
        sel.disabled = !!isAuto;
      });
    }

    if (autoToggle) {
      autoToggle.addEventListener('change', toggleAutoState);
      toggleAutoState();
    }
  })();
</script>
@endsection
