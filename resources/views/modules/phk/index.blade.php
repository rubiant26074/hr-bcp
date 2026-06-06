@extends('layouts.app')

@section('content')
<h4 class="mb-3">Modul PHK</h4>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-2">
      <div class="fw-semibold">Perhitungan Pesangon PHK (Fleksibel)</div>
      <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#phkLegalModal">
        Dasar Hukum
      </button>
    </div>
    <form method="post" class="row g-2 align-items-end">
      @csrf
      <div class="col-md-4">
        <label class="form-label">Upah Dasar (Rp)</label>
        <input type="number" step="0.01" class="form-control" name="basis_salary" value="{{ old('basis_salary', $calc['basis_salary'] ?? '') }}" placeholder="Contoh: 5000000">
      </div>
      <div class="col-md-4">
        <label class="form-label">Masa Kerja (tahun)</label>
        <input type="number" step="0.01" class="form-control" name="service_years" value="{{ old('service_years', $calc['service_years'] ?? '') }}" placeholder="Contoh: 5.5">
      </div>
      <div class="col-md-4">
        <label class="form-label">Faktor Pesangon</label>
        <input type="number" step="0.01" class="form-control" name="pesangon_multiplier" value="{{ old('pesangon_multiplier', $calc['pesangon_multiplier'] ?? 1) }}" placeholder="Contoh: 1">
      </div>
      <div class="col-md-4">
        <label class="form-label">Faktor UPMK</label>
        <input type="number" step="0.01" class="form-control" name="upmk_multiplier" value="{{ old('upmk_multiplier', $calc['upmk_multiplier'] ?? 1) }}" placeholder="Contoh: 1">
      </div>
      <div class="col-md-4">
        <label class="form-label">UPH % (dari Pesangon+UPMK)</label>
        <input type="number" step="0.01" class="form-control" name="uph_percent" value="{{ old('uph_percent', $calc['uph_percent'] ?? '') }}" placeholder="Contoh: 15">
      </div>
      <div class="col-md-4">
        <label class="form-label">Pesangon Manual (Rp)</label>
        <input type="number" step="0.01" class="form-control" name="manual_pesangon" value="{{ old('manual_pesangon', $calc['manual_pesangon'] ?? '') }}">
      </div>
      <div class="col-md-4">
        <label class="form-label">UPMK Manual (Rp)</label>
        <input type="number" step="0.01" class="form-control" name="manual_upmk" value="{{ old('manual_upmk', $calc['manual_upmk'] ?? '') }}">
      </div>
      <div class="col-md-4">
        <label class="form-label">UPH Manual (Rp)</label>
        <input type="number" step="0.01" class="form-control" name="manual_uph" value="{{ old('manual_uph', $calc['manual_uph'] ?? '') }}">
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100" type="submit">Hitung</button>
      </div>
      <div class="col-12">
        <div class="small text-muted">
          Faktor disesuaikan dengan alasan PHK sesuai UU/PP yang berlaku. Angka manual dapat dipakai jika ada kebijakan perusahaan.
        </div>
      </div>
    </form>

    @if ($calc)
      <div class="row g-2 mt-3">
        <div class="col-md-4">
          <div class="border rounded p-2">
            <div class="small text-muted">Pesangon (Auto)</div>
            <div class="fw-semibold">{{ format_currency_id($calc['auto_pesangon'] ?? 0, 2, true) }}</div>
            <div class="small text-muted">Dasar: {{ $calc['base_pesangon_months'] ?? 0 }} bulan</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded p-2">
            <div class="small text-muted">UPMK (Auto)</div>
            <div class="fw-semibold">{{ format_currency_id($calc['auto_upmk'] ?? 0, 2, true) }}</div>
            <div class="small text-muted">Dasar: {{ $calc['base_upmk_months'] ?? 0 }} bulan</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded p-2">
            <div class="small text-muted">UPH (Auto)</div>
            <div class="fw-semibold">{{ format_currency_id($calc['auto_uph'] ?? 0, 2, true) }}</div>
          </div>
        </div>
        <div class="col-md-12">
          <div class="border rounded p-2 bg-light">
            <div class="small text-muted">Total Auto</div>
            <div class="fs-5 fw-semibold">{{ format_currency_id($calc['auto_total'] ?? 0, 2, true) }}</div>
          </div>
        </div>
        <div class="col-md-12">
          <div class="border rounded p-2">
            <div class="small text-muted">Total Manual (Pesangon + UPMK + UPH)</div>
            <div class="fs-5 fw-semibold">{{ format_currency_id($calc['manual_total'] ?? 0, 2, true) }}</div>
          </div>
        </div>
      </div>
    @endif
  </div>
</div>
@endsection

@section('modals')
<div class="modal fade" id="phkLegalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Dasar Hukum (Ringkas)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="fw-semibold mb-2">Acuan Regulasi Utama</div>
        <ul class="mb-3">
          <li>UU Ketenagakerjaan beserta perubahannya (termasuk UU Cipta Kerja).</li>
          <li>
            <a href="https://peraturan.bpk.go.id/Home/Details/161904/pp-no-35-tahun-2021" target="_blank" rel="noopener">
              PP No. 35 Tahun 2021
            </a>
            (PKWT, PHK, pesangon, UPMK, UPH).
          </li>
        </ul>
        <div class="fw-semibold mb-2">Catatan Pengisian</div>
        <ul class="mb-0">
          <li>Masukkan masa kerja dan upah dasar sesuai data terbaru.</li>
          <li>Gunakan faktor sesuai alasan PHK; jika ada kebijakan internal, pakai angka manual.</li>
          <li>Tabel pesangon & UPMK mengikuti PP 35/2021 Pasal 40.</li>
        </ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
@endsection
