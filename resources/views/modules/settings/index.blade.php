@extends('layouts.app')

@section('content')
<h4 class="mb-3">Settings</h4>
<div class="row g-3">
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-2">Seting Theme</h6>
        <div class="small text-muted mb-2">Atur preferensi tampilan (light/dark).</div>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('settings.theme') }}">Buka</a>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-2">Backup Database</h6>
        <div class="small text-muted mb-2">Unduh backup database (SQL).</div>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('settings.backup') }}">Buka</a>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-2">Kontrol Hak Akses (RBAC)</h6>
        <div class="small text-muted mb-2">Kelola hak akses per role.</div>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('rbac.index') }}">Buka</a>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-2">User Management</h6>
        <div class="small text-muted mb-2">Kelola akun user.</div>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('users.index') }}">Buka</a>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card shadow-sm border-danger">
      <div class="card-body">
        <h6 class="mb-2 text-danger">Reset Database</h6>
        <div class="small text-muted mb-2">Hapus data operasional, kecuali data karyawan.</div>
        <a class="btn btn-outline-danger btn-sm" href="{{ route('settings.reset') }}">Buka</a>
      </div>
    </div>
  </div>
</div>
@endsection
