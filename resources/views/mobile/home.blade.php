@extends('mobile.layout')

@php($showNav = true)
@php($activeTab = 'home')

@section('content')
<div class="d-flex justify-content-between align-items-start mb-3">
  <div>
    <div class="small text-muted">Selamat datang</div>
    <div class="fw-bold">{{ $user['name'] ?? '-' }}</div>
    <div class="small text-muted">{{ $company->company_name ?? 'Perusahaan belum dipilih' }}</div>
  </div>
  <a href="{{ route('mobile.logout') }}" class="btn btn-outline-secondary btn-sm">Keluar</a>
</div>

<div class="card card-clean mb-3">
  <div class="card-body">
    <div class="fw-semibold mb-2">Menu Utama</div>
    <div class="d-grid gap-2">
      <a href="{{ route('mobile.attendance') }}" class="btn btn-dark">Absensi Mobile</a>
      <a href="{{ route('mobile.recap') }}" class="btn btn-outline-dark">Lihat Rekap Absensi</a>
      <a href="{{ route('mobile.payslip') }}" class="btn btn-outline-dark">Lihat Slip Gaji</a>
    </div>
  </div>
</div>

<div class="card card-clean">
  <div class="card-body">
    <div class="fw-semibold mb-2">Log Hari Ini</div>
    @if ($todayLogs->isEmpty())
      <div class="text-muted small">Belum ada log absensi hari ini.</div>
    @else
      @foreach ($todayLogs as $log)
        <div class="d-flex justify-content-between border-bottom py-2 small">
          <span>{{ date('H:i', strtotime((string) $log->scan_time)) }}</span>
          <span class="text-muted">{{ strtoupper((string) $log->verify_type) }}</span>
        </div>
      @endforeach
    @endif
  </div>
</div>
@endsection

