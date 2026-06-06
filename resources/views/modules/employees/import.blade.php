@extends('layouts.app')

@section('content')
<h4 class="mb-3">Import Data Karyawan (CSV / Excel)</h4>
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
    <div class="mb-3">
      <a class="btn btn-outline-success btn-sm" href="{{ route('employees.template') }}">Download Template Excel</a>
    </div>
    <form method="post" enctype="multipart/form-data">
      @csrf
      <div class="mb-3">
        <label class="form-label">Upload CSV / Excel</label>
        <input type="file" class="form-control" name="file" accept=".csv,.xlsx,.xls" required>
        <div class="form-text">Header wajib: NIK, Nama Karyawan, Status Karyawan, Staff / Non Staff, Jabatan, Golongan, Tanggal Join, Habis Kontrak. Opsional: Status Aktif, NIK KTP, Alamat KTP, Alamat Domisili, Tempat Lahir, Tanggal Lahir, Nomor HP, Nomor Tlp Saudara/Famili, NPWP, Nama Bank, Nomor Rekening, PTKP Status, Departement</div>
      </div>
      <button class="btn btn-primary" type="submit">Import</button>
    </form>
  </div>
</div>
@endsection
