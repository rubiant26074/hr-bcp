@extends('layouts.app')

@section('content')
<h4 class="mb-3">Review Payroll</h4>
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
    <div class="col-md-3">
      <label class="form-label">Period</label>
      <select class="form-select" name="period_id" onchange="this.form.submit()">
        @foreach ($periods as $p)
          <option value="{{ $p->id }}" {{ (int)$periodId === (int)$p->id ? 'selected' : '' }}>{{ $p->month }}/{{ $p->year }}</option>
        @endforeach
      </select>
    </div>
  </div>
</form>

<div class="card shadow-sm">
  <div class="card-body">
    <table class="table table-striped table-sm">
      <thead>
        <tr>
          <th>NIK</th>
          <th>Nama</th>
          <th>Total Penerimaan</th>
          <th>Total Potongan</th>
          <th>Gaji Bersih</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($items as $i)
          <tr>
            <td>{{ $i->nik }}</td>
            <td>{{ $i->name }}</td>
            <td>{{ format_currency($i->total_penerimaan) }}</td>
            <td>{{ format_currency($i->total_potongan) }}</td>
            <td>{{ format_currency($i->gaji_bersih) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection
