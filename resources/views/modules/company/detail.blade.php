@extends('layouts.app')

@section('content')
<h4 class="mb-3">Detail Company</h4>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-3">
        @if (!empty($company->logo_path))
          <img src="{{ asset_url($company->logo_path) }}" alt="logo" class="img-fluid rounded">
        @else
          <div class="text-muted">No Logo</div>
        @endif
      </div>
      <div class="col-md-9">
        <div><strong>Nama:</strong> {{ $company->company_name }}</div>
        <div><strong>Kode:</strong> {{ $company->company_code }}</div>
        <div><strong>Alamat:</strong> {{ $company->address }}</div>
        <div><strong>NPWP:</strong> {{ $company->npwp }}</div>
        <div><strong>Nama Bank:</strong> {{ $company->bank_name ?? '-' }}</div>
        <div><strong>Nomor Rekening Debet:</strong> {{ $company->bank_debit_account_no ?? '-' }}</div>
        <hr>
        <div><strong>BPJS Kesehatan:</strong> {{ $company->bpjs_health_pct }}%</div>
        <div><strong>JHT:</strong> {{ $company->bpjs_jht_pct }}%</div>
        <div><strong>JP:</strong> {{ $company->bpjs_jp_pct }}%</div>
        <div><strong>Payroll Day:</strong> {{ $company->payroll_day }}</div>
        <hr>
        @php
          $workDays = [];
          if (!empty($company->work_days_json)) {
            $decoded = json_decode($company->work_days_json, true);
            if (is_array($decoded)) {
              $workDays = $decoded;
            }
          }
          $dayLabels = [
            'Mon' => 'Senin',
            'Tue' => 'Selasa',
            'Wed' => 'Rabu',
            'Thu' => 'Kamis',
            'Fri' => 'Jumat',
            'Sat' => 'Sabtu',
            'Sun' => 'Minggu',
          ];
          $workDaysText = [];
          foreach ($workDays as $d) {
            $workDaysText[] = $dayLabels[$d] ?? $d;
          }
        @endphp
        <div><strong>Jumlah Hari / Minggu:</strong> {{ $company->work_days_per_week ?? '-' }}</div>
        <div><strong>Jam Kerja:</strong> {{ $company->work_time_start ? format_time_id($company->work_time_start) : '-' }} - {{ $company->work_time_end ? format_time_id($company->work_time_end) : '-' }}</div>
        <div><strong>Durasi Kerja:</strong> {{ $company->work_duration_hours ?? '-' }} jam</div>
        <div><strong>Hari Kerja:</strong> {{ count($workDaysText) ? implode(', ', $workDaysText) : '-' }}</div>
      </div>
    </div>
  </div>
</div>
@endsection
