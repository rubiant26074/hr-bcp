@extends('layouts.app')

@section('content')
<h4 class="mb-3">Run Payroll</h4>
@foreach ($messages as $m)
  <div class="alert alert-info">{{ $m }}</div>
@endforeach

@if (current_user_has_global_scope($user))
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

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post">
      @csrf
      <div class="mb-3">
        <label class="form-label">Period</label>
        <select class="form-select" name="period_id" required>
          @foreach ($periods as $p)
            <option value="{{ $p->id }}">{{ $p->month }}/{{ $p->year }} - {{ $p->status }}</option>
          @endforeach
        </select>
      </div>
      <button class="btn btn-primary" type="submit">Run Payroll</button>
    </form>
  </div>
</div>
@endsection
