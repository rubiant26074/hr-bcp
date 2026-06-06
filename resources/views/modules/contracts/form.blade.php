@extends('layouts.app')

@section('content')
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
  <h4 class="mb-0 text-center text-md-start">{{ $edit ? 'Edit Contract' : 'Add Contract' }}</h4>
  <a class="btn btn-outline-secondary align-self-center align-self-md-auto" href="{{ route('contracts.index') }}">Back to List</a>
</div>

<div class="row justify-content-center g-3">
  <div class="col-12 col-md-10 col-lg-8">
    @foreach ($messages as $m)
      <div class="alert alert-info">{{ $m }}</div>
    @endforeach

    @if (current_user_has_global_scope($user))
      <form class="card shadow-sm mb-3" method="get">
        @if ($edit)
          <input type="hidden" name="id" value="{{ $edit->id }}">
        @endif
        <div class="card-body">
          <div class="row g-2 align-items-end">
            <div class="col-md-8">
              <label class="form-label">Company</label>
              <select class="form-select" name="set_company" onchange="this.form.submit()">
                @foreach ($companies as $c)
                  <option value="{{ $c->id }}" {{ $companyId == $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
                @endforeach
              </select>
            </div>
          </div>
        </div>
      </form>
    @endif

    @if (!$edit)
      <div class="card shadow-sm mb-3">
        <div class="card-body">
          <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-2">
            <h6 class="mb-0">Import Contracts (Excel / CSV)</h6>
            <a class="btn btn-outline-success btn-sm" href="{{ route('contracts.template') }}">Download Template</a>
          </div>
          <div class="small text-muted mb-3">Header minimal: <strong>NIK</strong>, <strong>Contract Type</strong>, <strong>Start Date</strong>. Format tanggal: <strong>dd/mm/yyyy</strong> atau <strong>yyyy-mm-dd</strong>.</div>
          <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
            @csrf
            <input type="hidden" name="action" value="import_contract">
            <div class="col-md-8">
              <label class="form-label">File Import</label>
              <input type="file" class="form-control" name="import_file" accept=".csv,.xlsx,.xls" required>
            </div>
            <div class="col-md-4">
              <button class="btn btn-outline-primary w-100" type="submit">Import File</button>
            </div>
          </form>
        </div>
      </div>
    @endif

    <div class="border rounded-3 bg-light p-3 p-md-4">
      <div>
        <form method="post">
          @csrf
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="{{ $edit->id ?? '' }}">

          <div class="row g-3 g-md-4">
            <div class="col-12">
              <label class="form-label">Employee</label>
              <select class="form-select" name="employee_id" required>
                <option value="">Pilih Employee</option>
                @foreach ($employees as $e)
                  @php $selectedValue = old('employee_id', $edit->employee_id ?? ''); @endphp
                  @php $selected = (string)($e->id) === (string)$selectedValue ? 'selected' : ''; @endphp
                  <option value="{{ $e->id }}" data-join-date="{{ date_input_value($e->join_date ?? '') }}" {{ $selected }}>{{ $e->name }} ({{ $e->nik }})</option>
                @endforeach
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Contract Type</label>
              @php $selectedContractType = old('contract_type', $edit->contract_type ?? ''); @endphp
              <select class="form-select" name="contract_type" required>
                <option value="">Pilih Contract Type</option>
                <option value="PKWT" {{ $selectedContractType === 'PKWT' ? 'selected' : '' }}>PKWT</option>
                <option value="Perpanjangan PKWT" {{ $selectedContractType === 'Perpanjangan PKWT' ? 'selected' : '' }}>Perpanjangan PKWT</option>
                <option value="Percobaan" {{ $selectedContractType === 'Percobaan' ? 'selected' : '' }}>Percobaan</option>
                <option value="Re-hire" {{ $selectedContractType === 'Re-hire' ? 'selected' : '' }}>Re-hire</option>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Start Date</label>
              <input type="date" class="form-control" name="start_date" value="{{ date_input_value(old('start_date', $edit->start_date ?? '')) }}" required data-auto="0">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">End Date</label>
              <input type="date" class="form-control" name="end_date" value="{{ date_input_value(old('end_date', $edit->end_date ?? '')) }}">
            </div>
            <div class="col-12">
              <label class="form-label mb-2">Masa Kontrak</label>
              <div class="row g-2 g-md-3">
                <div class="col-12 col-md-4">
                  <label class="form-label small text-muted mb-1">Kontrak Terahir</label>
                  <input type="date" class="form-control" name="masa_kontrak_terahir" value="{{ date_input_value(old('masa_kontrak_terahir', $editNotes['masa_kontrak']['kontrak_terahir'])) }}">
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label small text-muted mb-1">Kontrak I</label>
                  <input type="date" class="form-control" name="masa_kontrak_1" value="{{ date_input_value(old('masa_kontrak_1', $editNotes['masa_kontrak']['kontrak_1'])) }}">
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label small text-muted mb-1">Kontrak II</label>
                  <input type="date" class="form-control" name="masa_kotrak_2" value="{{ date_input_value(old('masa_kotrak_2', $editNotes['masa_kontrak']['kotrak_2'])) }}">
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label small text-muted mb-1">Rehat</label>
                  <input type="date" class="form-control" name="masa_rehat" value="{{ date_input_value(old('masa_rehat', $editNotes['masa_kontrak']['rehat'])) }}">
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label small text-muted mb-1">Kontrak I</label>
                  <input type="date" class="form-control" name="masa_kontrak_1_lanjutan" value="{{ date_input_value(old('masa_kontrak_1_lanjutan', $editNotes['masa_kontrak']['kontrak_1_lanjutan'])) }}">
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label small text-muted mb-1">Kontrak II</label>
                  <input type="date" class="form-control" name="masa_kotrak_2_lanjutan" value="{{ date_input_value(old('masa_kotrak_2_lanjutan', $editNotes['masa_kontrak']['kotrak_2_lanjutan'])) }}">
                </div>
              </div>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Notes</label>
              <input type="text" class="form-control" name="notes" value="{{ old('notes', $editNotes['notes_text']) }}">
            </div>
          </div>

          <div class="mt-4 d-grid d-sm-flex gap-2 justify-content-sm-end">
            <button class="btn btn-primary" type="submit">{{ $edit ? 'Update' : 'Save' }}</button>
            <a class="btn btn-outline-secondary" href="{{ route('contracts.index') }}">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    const employeeSelect = document.querySelector('select[name="employee_id"]');
    const startDateInput = document.querySelector('input[name="start_date"]');
    if (!employeeSelect || !startDateInput) {
      return;
    }

    const applyJoinDate = function () {
      const selected = employeeSelect.options[employeeSelect.selectedIndex];
      if (!selected) {
        return;
      }
      const joinDate = selected.getAttribute('data-join-date') || '';
      if (!joinDate) {
        return;
      }
      const shouldAutoFill = startDateInput.value === '' || startDateInput.getAttribute('data-auto') === '1';
      if (shouldAutoFill) {
        startDateInput.value = joinDate;
        startDateInput.setAttribute('data-auto', '1');
      }
    };

    employeeSelect.addEventListener('change', applyJoinDate);
    applyJoinDate();
  })();
</script>
@endsection
