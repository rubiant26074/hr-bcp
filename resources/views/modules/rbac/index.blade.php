@extends('layouts.app')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">Kontrol Hak Akses (RBAC)</h4>
</div>

@if (request()->query('saved'))
  <div class="alert alert-success">Hak akses berhasil disimpan.</div>
@endif

<div class="card shadow-sm">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end mb-3">
      <div class="col-md-4">
        <label class="form-label">Role</label>
        <select class="form-select" name="role" onchange="this.form.submit()">
          @foreach ($roles as $role)
            <option value="{{ $role }}" {{ ($defaultRole ?? '') === $role ? 'selected' : '' }}>{{ $role }}</option>
          @endforeach
        </select>
      </div>
    </form>
    <style>
      .rbac-wrap { overflow: auto; }
      .rbac-table { --rbac-col1: 260px; --rbac-col2: 210px; }
      .rbac-table .sticky-col-1 {
        position: sticky;
        left: 0;
        background: #fff;
        z-index: 2;
      }
      .rbac-table .sticky-col-2 {
        position: sticky;
        left: var(--rbac-col1);
        background: #fff;
        z-index: 2;
      }
      .rbac-table thead .sticky-col-1,
      .rbac-table thead .sticky-col-2 {
        background: #f8f9fa;
        z-index: 4;
      }
      .rbac-table .section-row td {
        position: sticky;
        left: 0;
        background: #e9ecef;
        z-index: 3;
      }
      .rbac-table .section-row .section-label {
        display: inline-block;
        position: sticky;
        left: 0;
      }
    </style>
    <form method="post">
      @csrf
      <input type="hidden" name="role" value="{{ $defaultRole }}">
      <div class="table-responsive rbac-wrap">
        <table class="table table-bordered table-sm align-middle rbac-table">
          <thead class="table-light">
            <tr>
              <th class="sticky-col-1" style="min-width: 260px;">Menu / Permission</th>
              <th class="sticky-col-2" style="min-width: 210px;">Path</th>
              <th class="text-center" style="min-width: 180px;">
                <div class="d-flex flex-column align-items-center gap-1">
                  <div>{{ $defaultRole }}</div>
                  @if ($defaultRole !== 'Super Admin')
                    <div class="d-flex gap-1">
                      <button type="button" class="btn btn-outline-secondary btn-sm rbac-toggle" data-role="{{ $defaultRole }}" data-action="check">Check All</button>
                      <button type="button" class="btn btn-outline-secondary btn-sm rbac-toggle" data-role="{{ $defaultRole }}" data-action="uncheck">Uncheck All</button>
                    </div>
                  @endif
                </div>
              </th>
            </tr>
          </thead>
          <tbody>
            @php $currentSection = ''; @endphp
            @foreach ($permissions as $p)
              @if ($currentSection !== $p->section_name)
                @php $currentSection = $p->section_name; @endphp
                <tr class="table-secondary section-row">
                  <td colspan="3" class="fw-semibold text-uppercase small">
                    <span class="section-label">{{ $currentSection }}</span>
                  </td>
                </tr>
              @endif
              <tr>
                <td class="sticky-col-1">{{ $p->label }}</td>
                <td class="sticky-col-2"><code>{{ $p->path }}</code></td>
                @php $checked = $defaultRole === 'Super Admin' || !empty($matrix[$defaultRole][$p->permission_key]); @endphp
                <td class="text-center">
                  <input class="form-check-input"
                         type="checkbox"
                         name="allowed[{{ $defaultRole }}][]"
                         value="{{ $p->permission_key }}"
                         {{ $checked ? 'checked' : '' }}
                         {{ $defaultRole === 'Super Admin' ? 'disabled' : '' }}>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="mt-2 d-flex justify-content-end">
        <button class="btn btn-primary" type="submit">Simpan Matrix RBAC</button>
      </div>
    </form>
    <div class="small text-muted mt-2">Kolom Super Admin dikunci dan selalu memiliki semua akses.</div>
  </div>
</div>

<script>
document.querySelectorAll('.rbac-toggle').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var role = btn.getAttribute('data-role');
    var action = btn.getAttribute('data-action');
    if (!role || !action) return;
    var selector = 'input[type="checkbox"][name="allowed[' + role + '][]"]';
    document.querySelectorAll(selector).forEach(function (cb) {
      if (cb.disabled) return;
      cb.checked = action === 'check';
    });
  });
});
</script>
@endsection
