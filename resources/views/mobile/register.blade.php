@extends('mobile.layout')

@section('content')
<div class="pt-4">
  <div class="text-center mb-4">
    <h4 class="fw-bold mb-1">Register HR-BCP Mobile</h4>
    <div class="text-muted small">Akun baru wajib aktivasi Admin sebelum bisa digunakan</div>
  </div>

  <div class="card card-clean">
    <div class="card-body p-4">
      @if (!empty($error))
        <div class="alert alert-danger py-2">{{ $error }}</div>
      @endif
      @if (!empty($success))
        <div class="alert alert-success py-2">{{ $success }}</div>
      @endif
      @if ($errors->any())
        <div class="alert alert-danger py-2">
          <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $err)
              <li>{{ $err }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form method="post" action="{{ route('mobile.register.submit') }}">
        @csrf

        <div class="mb-3">
          <label class="form-label">Pilih Entitas Perusahaan</label>
          <select name="company_id" id="companySelect" class="form-select form-select-lg" required>
            <option value="">Pilih Entitas</option>
            @foreach ($companies as $c)
              <option value="{{ $c->id }}" {{ (int) old('company_id') === (int) $c->id ? 'selected' : '' }}>
                {{ $c->company_name }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Masukan Email</label>
          <input type="email" name="email" class="form-control form-control-lg" value="{{ old('email') }}" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Pilih Connect Employee</label>
          @php
            $oldEmployeeId = (int) old('employee_id');
            $oldEmployeeText = '';
            foreach ($employees as $empItem) {
              if ((int) $empItem->id === $oldEmployeeId) {
                $oldEmployeeText = $empItem->name . ' (' . $empItem->nik . ')';
                break;
              }
            }
          @endphp
          <input type="text" id="employeePicker" class="form-control form-control-lg" list="employeeList" placeholder="Ketik nama / NIK employee..." value="{{ $oldEmployeeText }}" autocomplete="off" required>
          <datalist id="employeeList"></datalist>
          <input type="hidden" name="employee_id" id="employeeIdHidden" value="{{ $oldEmployeeId > 0 ? $oldEmployeeId : '' }}">
        </div>

        <div class="mb-3">
          <label class="form-label">Masukan Password</label>
          <input type="password" name="password" class="form-control form-control-lg" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Konfirmasi Password</label>
          <input type="password" name="password_confirmation" class="form-control form-control-lg" required>
        </div>

        <button class="btn btn-dark w-100 btn-lg" type="submit">Daftar</button>
      </form>

      <div class="text-center mt-3">
        <a href="{{ route('mobile.login') }}" class="small text-decoration-none">Kembali ke Login</a>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    var company = document.getElementById('companySelect');
    var picker = document.getElementById('employeePicker');
    var list = document.getElementById('employeeList');
    var hiddenId = document.getElementById('employeeIdHidden');
    if (!company || !picker || !list || !hiddenId) return;

    var allEmployees = [
      @foreach ($employees as $e)
        { id: {{ (int) $e->id }}, company_id: {{ (int) $e->company_id }}, label: @json($e->name . ' (' . $e->nik . ')') },
      @endforeach
    ];

    function renderEmployeeOptions() {
      var companyId = parseInt(company.value || '0', 10);
      list.innerHTML = '';
      if (!companyId) {
        picker.value = '';
        hiddenId.value = '';
        return;
      }

      var fragment = document.createDocumentFragment();
      allEmployees.forEach(function (emp) {
        if (emp.company_id !== companyId) return;
        var opt = document.createElement('option');
        opt.value = emp.label;
        opt.setAttribute('data-id', String(emp.id));
        fragment.appendChild(opt);
      });
      list.appendChild(fragment);
    }

    function syncHiddenEmployeeId() {
      var current = (picker.value || '').trim().toLowerCase();
      var companyId = parseInt(company.value || '0', 10);
      var found = allEmployees.find(function (emp) {
        return emp.company_id === companyId && emp.label.toLowerCase() === current;
      });
      hiddenId.value = found ? String(found.id) : '';
    }

    company.addEventListener('change', function () {
      renderEmployeeOptions();
      syncHiddenEmployeeId();
    });
    picker.addEventListener('input', syncHiddenEmployeeId);
    picker.addEventListener('change', syncHiddenEmployeeId);

    renderEmployeeOptions();
    syncHiddenEmployeeId();
  })();
</script>
@endsection
