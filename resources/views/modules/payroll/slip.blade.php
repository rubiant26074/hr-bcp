@extends('layouts.app')

@section('content')
<style>
  .slip-wrap {
    background: #fff;
    border: 1px solid #e6ebf2;
    border-radius: 14px;
    padding: 22px 24px;
  }
  .slip-top {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 16px;
    align-items: start;
  }
  .slip-brand {
    display: flex;
    gap: 12px;
    align-items: center;
  }
  .slip-brand img {
    height: 46px;
  }
  .slip-title {
    font-size: 22px;
    font-weight: 800;
    letter-spacing: 1px;
    color: #1f3553;
  }
  .slip-subtitle {
    font-size: 12px;
    color: #6b7a8b;
  }
  .slip-period {
    margin-top: 6px;
    font-weight: 600;
    color: #1f3553;
  }
  .slip-divider {
    border-top: 1px solid #e6ebf2;
    margin: 16px 0;
  }
  .slip-section-title {
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.7px;
    color: #1f3553;
    margin-bottom: 8px;
  }
  .slip-kv {
    display: grid;
    grid-template-columns: 140px 1fr;
    gap: 6px 12px;
    font-size: 13px;
  }
  .slip-kv .label {
    color: #6b7a8b;
  }
  .slip-card {
    border: 1px solid #dfe6ef;
    border-radius: 10px;
    overflow: hidden;
  }
  .slip-card .head {
    background: linear-gradient(135deg, #2b4f77, #385f8e);
    color: #fff;
    font-weight: 700;
    padding: 8px 12px;
    font-size: 13px;
    letter-spacing: 0.4px;
  }
  .slip-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
  }
  .slip-table th, .slip-table td {
    padding: 7px 10px;
    border-bottom: 1px solid #edf1f6;
  }
  .slip-table th {
    background: #f3f6fb;
    text-align: left;
    color: #2d3a4a;
  }
  .slip-table td:last-child, .slip-table th:last-child {
    text-align: right;
  }
  .slip-total-row {
    background: #f7f9fd;
    font-weight: 700;
  }
  .slip-summary {
    border: 1px solid #dfe6ef;
    border-radius: 10px;
    overflow: hidden;
  }
  .slip-summary .head {
    background: linear-gradient(135deg, #2b4f77, #385f8e);
    color: #fff;
    font-weight: 700;
    padding: 8px 12px;
  }
  .slip-summary table td {
    padding: 8px 12px;
    border-bottom: 1px solid #edf1f6;
  }
  .slip-summary table td:last-child {
    text-align: right;
    font-weight: 700;
  }
  .slip-summary .take-home {
    background: #2b4f77;
    color: #fff;
    font-weight: 800;
  }
  .slip-note {
    font-size: 12px;
    color: #6b7a8b;
  }
  .slip-sign {
    margin-top: 14px;
    font-size: 13px;
  }
  .slip-sign .name {
    margin-top: 40px;
    font-weight: 800;
  }
  .slip-actions .btn {
    border-radius: 10px;
  }
  .ta-il-badge {
    display: inline-block;
    margin-left: 6px;
    padding: 1px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.4;
    color: #fff;
    background: #dc3545;
    vertical-align: middle;
  }
  .ta-il-note {
    margin-top: 6px;
    font-size: 12px;
    color: #6c757d;
  }
  @media print {
    .sidebar, .topbar, form, .slip-actions { display: none !important; }
    .page-content { padding: 0 !important; }
    .slip-wrap { border: none; box-shadow: none; }
  }
</style>
<h4 class="mb-3">Slip Gaji</h4>
<form class="mb-3" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label">Period</label>
      <select class="form-select" name="period_id">
        @foreach ($periods as $p)
          <option value="{{ $p->id }}" {{ (int)$periodId === (int)$p->id ? 'selected' : '' }}>{{ $p->month }}/{{ $p->year }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Employee</label>
      <select class="form-select" name="employee_id">
        @foreach ($items as $i)
          <option value="{{ $i->employee_id }}" {{ (int)$employeeId === (int)$i->employee_id ? 'selected' : '' }}>{{ $i->name }} ({{ $i->nik }})</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <button class="btn btn-primary" type="submit">View</button>
    </div>
  </div>
</form>

@if ($item)
<div class="slip-wrap shadow-sm">
  <div class="slip-top">
    <div>
      <div class="slip-brand">
        @if (!empty($item->logo_path))
          <img src="{{ asset_url($item->logo_path) }}" alt="logo">
        @endif
        <div>
          <div class="slip-title">{{ $item->company_name }}</div>
        </div>
      </div>
      @if ($currentPeriod)
        <div class="slip-period">Periode: {{ $currentPeriod->month }}/{{ $currentPeriod->year }}</div>
      @endif
    </div>
    <div class="slip-actions">
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('payroll.slip', ['period_id' => $periodId, 'employee_id' => $employeeId, 'format' => 'pdf']) }}">Download PDF</a>
      <button class="btn btn-primary btn-sm" onclick="window.print()">Print</button>
    </div>
  </div>

  <div class="slip-divider"></div>

  <div class="slip-section-title">DATA KARYAWAN</div>
  <div class="row g-3">
    <div class="col-md-6">
      <div class="slip-kv">
        <div class="label">NIK</div><div>{{ $item->nik }}</div>
        <div class="label">Nama</div><div>{{ $item->name }}</div>
        <div class="label">Golongan</div><div>{{ $item->grade }}</div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="slip-kv">
        <div class="label">Jabatan</div><div>{{ $item->position }}</div>
        <div class="label">Perusahaan</div><div>{{ $item->company_name }}</div>
      </div>
    </div>
  </div>

  <div class="slip-divider"></div>

  <div class="row g-3">
    <div class="col-md-6">
      <div class="slip-card">
        <div class="head">RINCIAN PENERIMAAN</div>
        <table class="slip-table">
          <thead>
            <tr><th style="width:48px;">No.</th><th>Komponen</th><th>Jumlah</th></tr>
          </thead>
          <tbody>
            <tr><td>A1</td><td>Gaji Pokok</td><td>{{ format_currency($item->basic_salary) }}</td></tr>
            <tr>
              <td>A2</td>
              <td>
                @php
                  $fmtHour = static function ($value) {
                      return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
                  };
                  $otDisplay = $overtimeSlipDisplay ?? [
                      'is_all_in' => false,
                      'raw_hours' => (float) ($lemburHours ?? 0),
                      'final_hours' => (float) ($lemburHours ?? 0),
                      'deducted_hours' => 0,
                  ];
                @endphp
                Lembur (
                  Lembur:
                  @if (!empty($otDisplay['is_all_in']) && (float) ($otDisplay['deducted_hours'] ?? 0) > 0)
                    {{ $fmtHour($otDisplay['raw_hours'] ?? 0) }} Jam - {{ $fmtHour($otDisplay['deducted_hours'] ?? 0) }} Jam (start 19.00)= {{ $fmtHour($otDisplay['final_hours'] ?? 0) }} Jam
                  @else
                    {{ $fmtHour($otDisplay['final_hours'] ?? $lemburHours ?? 0) }} Jam
                  @endif
                  | TA-IL: {{ $fmtHour($taIlHours ?? 0) }} Jam
                )
                @if (!empty($hasTaIl))
                  <span class="ta-il-badge">TA-IL</span>
                @endif
                @if (!empty($hasTaIl))
                  <div class="ta-il-note">TA-IL: Tidak Ada Izin Lembur</div>
                @endif
              </td>
              <td>{{ format_currency($item->a2_overtime) }}</td>
            </tr>
            <tr><td>A3</td><td>Tunjangan Makan</td><td>{{ format_currency($item->a3_meal) }}</td></tr>
            <tr><td>A4</td><td>Tunjangan Transport</td><td>{{ format_currency($item->a4_transport) }}</td></tr>
            <tr><td>A5</td><td>Tunjangan Kinerja</td><td>{{ format_currency($item->a5_performance) }}</td></tr>
            <tr><td>A6</td><td>Tunjangan Jabatan</td><td>{{ format_currency($item->a6_position) }}</td></tr>
            <tr><td>A7</td><td>Tunjangan Anak & Istri</td><td>{{ format_currency($item->a7_family) }}</td></tr>
            <tr><td>A8</td><td>Tunjangan Komunikasi</td><td>{{ format_currency($item->a8_communication) }}</td></tr>
            <tr><td>A9</td><td>Tunjangan Lain</td><td>{{ format_currency($item->a9_other) }}</td></tr>
            <tr><td>A10</td><td>THR</td><td>{{ format_currency($item->a10_thr) }}</td></tr>
            <tr><td>A11</td><td>Bonus</td><td>{{ format_currency($item->a11_bonus) }}</td></tr>
            <tr><td>A12</td><td>Rapel Gaji</td><td>{{ format_currency($item->a12_rapel_gaji ?? 0) }}</td></tr>
            <tr><td>A13</td><td>Tunjangan Pajak</td><td>{{ format_currency($item->a12_tax_allowance) }}</td></tr>
            <tr><td>A14</td><td>Tunjangan BPJS</td><td>{{ format_currency($item->a13_bpjs_allowance) }}</td></tr>
            <tr class="slip-total-row"><td colspan="2">Total Penerimaan</td><td>{{ format_currency($item->total_penerimaan) }}</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="col-md-6">
      <div class="slip-card mb-3">
        <div class="head">RINCIAN POTONGAN</div>
        <table class="slip-table">
          <thead>
            <tr><th style="width:48px;">No.</th><th>Komponen</th><th>Jumlah</th></tr>
          </thead>
          <tbody>
            <tr><td>B1</td><td>Pinjaman</td><td>{{ format_currency($item->b1_loan) }}</td></tr>
            <tr><td>B2</td><td>Absensi</td><td>{{ format_currency($item->b2_absence) }}</td></tr>
            <tr><td>B3</td><td>Subsidi</td><td>{{ format_currency($item->b3_subsidy) }}</td></tr>
            <tr><td>B4</td><td>BPJS Kesehatan (1%)</td><td>{{ format_currency($item->b4_bpjs_health) }}</td></tr>
            <tr><td>B5</td><td>JHT (2%)</td><td>{{ format_currency($item->b5_jht) }}</td></tr>
            <tr><td>B6</td><td>JP (1%)</td><td>{{ format_currency($item->b6_jp) }}</td></tr>
            <tr><td>B7</td><td>PPH 21</td><td>{{ format_currency($item->b7_pph21) }}</td></tr>
            <tr><td>B8</td><td>Lain-lain</td><td>{{ format_currency($item->b8_other) }}</td></tr>
            <tr class="slip-total-row"><td colspan="2">Total Potongan</td><td>{{ format_currency($item->total_potongan) }}</td></tr>
          </tbody>
        </table>
      </div>

      <div class="slip-summary">
        <div class="head">RINGKASAN GAJI</div>
        <table class="w-100">
          <tr><td>Total Penerimaan</td><td>{{ format_currency($item->total_penerimaan) }}</td></tr>
          <tr><td>Total Potongan</td><td>{{ format_currency($item->total_potongan) }}</td></tr>
          <tr class="take-home"><td>Gaji Bersih</td><td>{{ format_currency($item->gaji_bersih) }}</td></tr>
          <tr><td>Pembulatan</td><td>{{ format_currency($item->pembulatan) }}</td></tr>
        </table>
      </div>
    </div>
  </div>

  <div class="slip-divider"></div>
  <div class="slip-section-title">KETERANGAN</div>
  <div class="slip-note">Slip gaji ini dihasilkan secara sistem dan tidak memerlukan tanda tangan basah.</div>

  <div class="slip-divider"></div>
  <div class="slip-sign">
    <div>Mengetahui,</div>
    <div class="name">HRD Manager</div>
    <div>{{ $item->company_name }}</div>
  </div>
</div>
@endif
@endsection
