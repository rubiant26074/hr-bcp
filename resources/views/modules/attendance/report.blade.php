@extends('layouts.app')

@section('content')
<h4 class="mb-3">Attendance Report</h4>
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
    <div class="col-md-2">
      <label class="form-label">Mode Periode</label>
      <select class="form-select" name="period_mode" id="periodMode" onchange="togglePeriodMode()">
        <option value="cutoff" {{ ($periodMode ?? 'cutoff') === 'cutoff' ? 'selected' : '' }}>Cut Off 21-20</option>
        <option value="date_range" {{ ($periodMode ?? 'cutoff') === 'date_range' ? 'selected' : '' }}>Per Tanggal</option>
      </select>
    </div>
    <div class="col-md-2 js-cutoff">
      <label class="form-label">Month (Closing)</label>
      <input type="number" class="form-control" name="month" min="1" max="12" value="{{ $month }}">
    </div>
    <div class="col-md-2 js-cutoff">
      <label class="form-label">Year (Closing)</label>
      <input type="number" class="form-control" name="year" min="2020" max="2035" value="{{ $year }}">
    </div>
    <div class="col-md-2 js-range">
      <label class="form-label">Tanggal Dari</label>
      <input type="date" class="form-control" name="date_from" value="{{ $dateFrom ?? '' }}">
    </div>
    <div class="col-md-2 js-range">
      <label class="form-label">Tanggal Sampai</label>
      <input type="date" class="form-control" name="date_to" value="{{ $dateTo ?? '' }}">
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary" type="submit">Filter</button>
    </div>
  </div>
</form>
<div class="small text-muted mb-2">Periode aktif: {{ $periodLabel ?? '-' }}</div>

<div class="card shadow-sm">
  <div class="card-body">
    <table class="table table-striped table-sm">
      <thead>
        <tr>
          <th>NIK</th>
          <th>Nama</th>
          <th>Hadir (hari)</th>
          <th>Overtime (jam)</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($rows as $r)
          <tr>
            <td>{{ $r->nik }}</td>
            <td>{{ $r->name }}</td>
            <td>{{ $r->hadir }}</td>
            <td>{{ number_format((float) ($r->overtime ?? 0), 2, ',', '.') }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
<script>
  function togglePeriodMode() {
    var mode = document.getElementById('periodMode');
    var isCutoff = !mode || mode.value === 'cutoff';
    document.querySelectorAll('.js-cutoff').forEach(function (el) {
      el.style.display = isCutoff ? '' : 'none';
    });
    document.querySelectorAll('.js-range').forEach(function (el) {
      el.style.display = isCutoff ? 'none' : '';
    });
  }
  togglePeriodMode();
</script>
@endsection
