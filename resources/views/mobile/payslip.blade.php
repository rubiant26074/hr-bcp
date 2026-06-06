@extends('mobile.layout')

@php($showNav = true)
@php($activeTab = 'payslip')

@section('content')
<h5 class="fw-bold mb-3">Slip Gaji</h5>

<div class="card card-clean mb-3">
  <div class="card-body">
    <form method="get" class="d-flex gap-2">
      <select class="form-select" name="period_id">
        @foreach ($periods as $p)
          <option value="{{ $p->id }}" {{ (int)$periodId === (int)$p->id ? 'selected' : '' }}>
            {{ $p->month }}/{{ $p->year }}
          </option>
        @endforeach
      </select>
      <button class="btn btn-dark" type="submit">Lihat</button>
    </form>
  </div>
</div>

<div class="card card-clean">
  <div class="card-body">
    @if (!$item)
      <div class="text-muted small">Slip gaji belum tersedia untuk periode ini.</div>
    @else
      <div class="fw-semibold">{{ $item->name }} ({{ $item->nik }})</div>
      <div class="small text-muted mb-3">{{ $item->company_name }} | {{ $item->position }}</div>
      <div class="border rounded-3 p-2 mb-2">
        <div class="d-flex justify-content-between py-1">
          <span class="small">Lembur</span>
          <span>
            <strong>{{ format_currency($item->a2_overtime ?? 0) }}</strong>
            @if (!empty($taIlFlag))
              <span class="badge text-bg-danger ms-1">TA-IL</span>
            @endif
          </span>
        </div>
        <div class="small mt-1">
          @if (!empty($taIlFlag))
            <span class="badge text-bg-danger me-1">TA-IL</span>: {{ number_format((float)($taIlHours ?? 0), 2, ',', '.') }} Jam
            <span class="mx-1">|</span>
          @endif
          Lembur: {{ number_format((float)($validOvertimeHours ?? 0), 2, ',', '.') }} Jam
        </div>
        <div class="d-flex justify-content-between py-1"><span class="small">Total Penerimaan</span><strong>{{ format_currency($item->total_penerimaan) }}</strong></div>
        <div class="d-flex justify-content-between py-1"><span class="small">Total Potongan</span><strong>{{ format_currency($item->total_potongan) }}</strong></div>
        <div class="d-flex justify-content-between py-2 border-top mt-2">
          <span class="small fw-semibold">Gaji Bersih</span>
          <strong>{{ format_currency($item->gaji_bersih) }}</strong>
        </div>
        @if (!empty($taIlFlag))
          <div class="small text-secondary mt-2">TA-IL : Tidak Ada Izin Lembur</div>
        @endif
      </div>

      <button class="btn btn-outline-dark w-100 mt-2" type="button" data-bs-toggle="collapse" data-bs-target="#slipDetail" aria-expanded="false" aria-controls="slipDetail">
        Lihat Detail
      </button>

      <div class="collapse mt-3" id="slipDetail">
        <div class="border rounded-3 p-2 mb-3">
          <div class="fw-semibold small mb-2">Penerimaan</div>
          <div class="d-flex justify-content-between py-1 small"><span>Gaji Pokok</span><span>{{ format_currency($item->basic_salary) }}</span></div>
          <div class="d-flex justify-content-between py-1 small"><span>Lembur</span><span>{{ format_currency($item->a2_overtime) }}</span></div>
          <div class="d-flex justify-content-between py-1 small"><span>Tunjangan Makan</span><span>{{ format_currency($item->a3_meal) }}</span></div>
          <div class="d-flex justify-content-between py-1 small"><span>Tunjangan Transport</span><span>{{ format_currency($item->a4_transport) }}</span></div>
          <div class="d-flex justify-content-between py-1 small"><span>Tunjangan Kinerja</span><span>{{ format_currency($item->a5_performance) }}</span></div>
          <div class="d-flex justify-content-between py-1 small"><span>Tunjangan Jabatan</span><span>{{ format_currency($item->a6_position) }}</span></div>
          <div class="d-flex justify-content-between py-1 small"><span>Tunjangan Anak & Istri</span><span>{{ format_currency($item->a7_family) }}</span></div>
          <div class="d-flex justify-content-between py-1 small"><span>Tunjangan Komunikasi</span><span>{{ format_currency($item->a8_communication) }}</span></div>
          <div class="d-flex justify-content-between py-1 small"><span>Tunjangan Lain</span><span>{{ format_currency($item->a9_other) }}</span></div>
          <div class="d-flex justify-content-between py-1 small"><span>THR</span><span>{{ format_currency($item->a10_thr) }}</span></div>
          <div class="d-flex justify-content-between py-1 small"><span>Bonus</span><span>{{ format_currency($item->a11_bonus) }}</span></div>
          <div class="d-flex justify-content-between py-1 small"><span>Rapel Gaji</span><span>{{ format_currency($item->a12_rapel_gaji ?? 0) }}</span></div>
          <div class="d-flex justify-content-between py-1 small"><span>Tunjangan Pajak</span><span>{{ format_currency($item->a12_tax_allowance) }}</span></div>
          <div class="d-flex justify-content-between py-1 small"><span>Tunjangan BPJS</span><span>{{ format_currency($item->a13_bpjs_allowance) }}</span></div>
        </div>

        <div class="border rounded-3 p-2 mb-1">
          <div class="fw-semibold small mb-2">Potongan</div>
          <div class="d-flex justify-content-between py-1 small"><span>Pinjaman</span><span>{{ format_currency($item->b1_loan) }}</span></div>
          <div class="d-flex justify-content-between py-1 small"><span>Absensi</span><span>{{ format_currency($item->b2_absence) }}</span></div>
          <div class="d-flex justify-content-between py-1 small"><span>Subsidi</span><span>{{ format_currency($item->b3_subsidy) }}</span></div>
          <div class="d-flex justify-content-between py-1 small"><span>BPJS Kesehatan</span><span>{{ format_currency($item->b4_bpjs_health) }}</span></div>
          <div class="d-flex justify-content-between py-1 small"><span>JHT</span><span>{{ format_currency($item->b5_jht) }}</span></div>
          <div class="d-flex justify-content-between py-1 small"><span>JP</span><span>{{ format_currency($item->b6_jp) }}</span></div>
          <div class="d-flex justify-content-between py-1 small"><span>PPH 21</span><span>{{ format_currency($item->b7_pph21) }}</span></div>
          <div class="d-flex justify-content-between py-1 small"><span>Lain-lain</span><span>{{ format_currency($item->b8_other) }}</span></div>
        </div>
      </div>
    @endif
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@endsection
