@extends('layouts.app')

@section('content')
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
  <h4 class="mb-0 text-center text-md-start">{{ $edit ? 'Edit User' : 'Add User' }}</h4>
  <a class="btn btn-outline-secondary align-self-center align-self-md-auto" href="{{ route('users.index') }}">Back to List</a>
</div>

<div class="row justify-content-center g-3">
  <div class="col-12 col-md-10 col-lg-8">
    @foreach ($messages as $m)
      <div class="alert alert-info">{{ $m }}</div>
    @endforeach
    <div class="card shadow-sm">
      <div class="card-body">
        <form method="post" enctype="multipart/form-data">
          @csrf
          <input type="hidden" name="id" value="{{ $edit->id ?? '' }}">

          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label">Nama</label>
              <input type="text" name="name" class="form-control" value="{{ old('name', $edit->name ?? '') }}" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" value="{{ old('email', $edit->email ?? '') }}" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Role</label>
              @php $roleVal = old('role', $edit->role ?? 'HR'); @endphp
              <select name="role" class="form-select" id="roleSelect" required>
                @foreach ($roles as $r)
                  <option value="{{ $r->name }}" {{ $roleVal === $r->name ? 'selected' : '' }}>{{ $r->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Status Aktivasi</label>
              @php $activeVal = (int) old('is_active', $edit->is_active ?? 1); @endphp
              <select name="is_active" class="form-select">
                <option value="1" {{ $activeVal === 1 ? 'selected' : '' }}>Aktif</option>
                <option value="0" {{ $activeVal === 0 ? 'selected' : '' }}>Menunggu Aktivasi</option>
              </select>
            </div>
            <div class="col-12 col-md-6" id="companyWrap">
              <label class="form-label">Company</label>
              @php $companyVal = old('company_id', $edit->company_id ?? ''); @endphp
              <select name="company_id" class="form-select" id="companySelect">
                <option value="">Pilih Company</option>
                @foreach ($companies as $c)
                  <option value="{{ $c->id }}" {{ (int) $companyVal === (int) $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-12 col-md-6" id="employeeWrap">
              <label class="form-label">Employee</label>
              @php $employeeVal = old('employee_id', $edit->employee_id ?? ''); @endphp
              <select name="employee_id" class="form-select" id="employeeSelect">
                <option value="">Pilih Employee</option>
                @foreach ($employees as $e)
                  <option value="{{ $e->id }}" data-company="{{ $e->company_id }}" data-dept="{{ $e->department }}" {{ (int) $employeeVal === (int) $e->id ? 'selected' : '' }}>
                    {{ $e->name }} ({{ $e->nik }})
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Departement (Auto)</label>
              <input type="text" class="form-control" id="employeeDept" value="{{ old('employee_department', ($edit?->employee_id ? ($employees->firstWhere('id', (int) $edit->employee_id)->department ?? '') : '')) }}" readonly>
            </div>
            <div class="col-12">
              <label class="form-label">{{ $edit ? 'Password Baru (kosongkan jika tidak diubah)' : 'Password' }}</label>
              <input type="password" name="password" class="form-control" minlength="8" {{ $edit ? '' : 'required' }}>
            </div>
            <div class="col-12">
              <label class="form-label">Tanda Tangan (JPG/PNG)</label>
              <input type="file" name="signature_file" class="form-control" accept=".jpg,.jpeg,.png">
              @if (!empty($edit?->signature_path))
                <div class="mt-2">
                  <img src="{{ asset($edit->signature_path) }}" alt="Tanda Tangan" style="height:60px;">
                </div>
              @endif
            </div>
          </div>

          <div class="mt-3 d-grid d-sm-flex gap-2 justify-content-sm-end">
            <button class="btn btn-primary" type="submit">{{ $edit ? 'Update' : 'Save' }}</button>
            <a class="btn btn-outline-secondary" href="{{ route('users.index') }}">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    var role = document.getElementById('roleSelect');
    var companyWrap = document.getElementById('companyWrap');
    var companySelect = document.getElementById('companySelect');
    var employeeWrap = document.getElementById('employeeWrap');
    var employeeSelect = document.getElementById('employeeSelect');
    var employeeDept = document.getElementById('employeeDept');
    if (!role || !companyWrap || !companySelect || !employeeWrap || !employeeSelect) return;

    function filterEmployeeOptions() {
      var companyId = companySelect.value;
      var selectedVal = employeeSelect.value;
      var hasSelectedVisible = false;
      Array.prototype.forEach.call(employeeSelect.options, function (opt, idx) {
        if (idx === 0) return;
        var optCompany = opt.getAttribute('data-company') || '';
        var visible = companyId === '' || optCompany === companyId;
        opt.hidden = !visible;
        if (visible && opt.value === selectedVal) {
          hasSelectedVisible = true;
        }
      });
      if (!hasSelectedVisible) {
        employeeSelect.value = '';
      }
    }

    function syncCompanyRequired() {
      var isGlobalRole = ['Super Admin', 'CEO', 'CFA', 'HR1', 'HR2'].indexOf(role.value) !== -1;
      var isEmployee = role.value === 'Employee';
      companyWrap.style.display = isGlobalRole ? 'none' : '';
      companySelect.required = !isGlobalRole;
      if (isGlobalRole) companySelect.value = '';

      employeeWrap.style.display = isEmployee ? '' : 'none';
      employeeSelect.required = isEmployee;
      if (!isEmployee) {
        employeeSelect.value = '';
      }
      filterEmployeeOptions();
    }

    companySelect.addEventListener('change', filterEmployeeOptions);
    employeeSelect.addEventListener('change', function () {
      var selected = employeeSelect.options[employeeSelect.selectedIndex];
      if (!selected) return;
      var optCompany = selected.getAttribute('data-company') || '';
      var optDept = selected.getAttribute('data-dept') || '';
      if (employeeDept) {
        employeeDept.value = optDept;
      }
      if (role.value === 'Employee' && optCompany !== '') {
        companySelect.value = optCompany;
        filterEmployeeOptions();
      }
    });

    role.addEventListener('change', syncCompanyRequired);
    syncCompanyRequired();
    if (employeeSelect) {
      var selected = employeeSelect.options[employeeSelect.selectedIndex];
      if (selected && employeeDept) {
        employeeDept.value = selected.getAttribute('data-dept') || '';
      }
    }
  })();
</script>
@endsection
