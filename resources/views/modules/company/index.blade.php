@extends('layouts.app')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">Master Company</h4>
  <a class="btn btn-primary" href="{{ route('company.form') }}">Add Company</a>
</div>

@if (!empty($errors))
  @foreach ($errors as $m)
    <div class="alert alert-danger">{{ $m }}</div>
  @endforeach
@endif
@if (!empty($messages))
  @foreach ($messages as $m)
    <div class="alert alert-success">{{ $m }}</div>
  @endforeach
@endif

<div class="row g-3">
  <div class="col-12">
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form method="get" class="d-flex gap-2">
          <input type="text" class="form-control" name="q" placeholder="Search company..." value="{{ $q }}">
          <button class="btn btn-outline-primary" type="submit">Search</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-body">
        <table class="table table-striped">
          <thead>
            <tr>
              <th>Logo</th>
              <th>Code</th>
              <th>Name</th>
              <th>NPWP</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach ($companies as $c)
              <tr>
                <td>
                  @if (!empty($c->logo_path))
                    <img src="{{ asset_url($c->logo_path) }}" alt="logo" style="height:24px">
                  @endif
                </td>
                <td>{{ $c->company_code }}</td>
                <td>{{ $c->company_name }}</td>
                <td>{{ $c->npwp }}</td>
                <td class="text-end">
                  <a class="icon-btn icon-detail" title="Detail" href="{{ route('company.detail', ['id' => $c->id]) }}">
                    <span class="icon i-eye" aria-hidden="true"></span>
                  </a>
                  <a class="icon-btn icon-edit" title="Edit" href="{{ route('company.form', ['id' => $c->id]) }}">
                    <span class="icon i-edit" aria-hidden="true"></span>
                  </a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Hapus company ini?');">
                    @csrf
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="{{ $c->id }}">
                    <button class="icon-btn icon-delete" title="Delete" type="submit">
                      <span class="icon i-trash" aria-hidden="true"></span>
                    </button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
