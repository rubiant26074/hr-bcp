@extends('layouts.app')

@section('content')
<h4 class="mb-3">Modul Pensiun</h4>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-2">
      <div class="fw-semibold">Daftar Karyawan Tetap</div>
      <a class="btn btn-outline-secondary btn-sm"
         href="{{ route('pension.pdf', array_filter(['company_id' => ($user['role'] ?? '') === 'Super Admin' ? $companyId : null, 'age_min' => $ageMin, 'age_max' => $ageMax, 'retire_year' => $retireYear, 'retirement_method' => $retirementMethod ?? null, 'custom_retire_age' => $customRetireAge], fn($v) => $v !== null && $v !== '')) }}">
        Export PDF
      </a>
    </div>
    <form method="get" class="row g-2 align-items-end mb-3" id="pensionFilterForm">
      @if (($user['role'] ?? '') === 'Super Admin')
      <div class="col-md-4">
        <label class="form-label">Company</label>
        <select class="form-select" name="company_id">
          @foreach ($companies as $c)
            <option value="{{ $c->id }}" {{ (int) $companyId === (int) $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
          @endforeach
        </select>
      </div>
      @endif
      <div class="col-md-4">
        <label class="form-label">Metode Usia Pensiun</label>
        <select class="form-select" name="retirement_method" id="retirement_method">
          @foreach (($retirementMethodOptions ?? []) as $key => $label)
            <option value="{{ $key }}" {{ ($retirementMethod ?? 'government') === $key ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2 {{ ($retirementMethod ?? 'government') === 'company_policy' ? '' : 'd-none' }}" id="custom_retire_age_wrap">
        <label class="form-label">Usia Pensiun</label>
        <input type="number" class="form-control" name="custom_retire_age" id="custom_retire_age" value="{{ $customRetireAge ?? 55 }}" min="1" max="100" placeholder="Contoh: 55">
      </div>
      <div class="col-md-2">
        <label class="form-label">Usia Min</label>
        <input type="number" class="form-control" name="age_min" value="{{ $ageMin ?? '' }}" min="0" placeholder="Contoh: 50">
      </div>
      <div class="col-md-2">
        <label class="form-label">Usia Max</label>
        <input type="number" class="form-control" name="age_max" value="{{ $ageMax ?? '' }}" min="0" placeholder="Contoh: 60">
      </div>
      <div class="col-md-2">
        <label class="form-label">Tahun Pensiun</label>
        <input type="number" class="form-control" name="retire_year" value="{{ $retireYear ?? '' }}" min="1900" max="2100" placeholder="Contoh: 2028">
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-primary w-100" type="submit">Filter</button>
        <a class="btn btn-outline-secondary w-100" href="{{ route('pension.index') }}">Reset</a>
      </div>
    </form>
    <div class="table-responsive">
      <table class="table table-striped table-sm align-middle">
        <thead>
          <tr>
            <th>NIK</th>
            <th>Nama</th>
            <th>Tgl Lahir</th>
            <th>Usia</th>
            <th>Usia Pensiun</th>
            <th>Tahun Pensiun</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($rows as $row)
            @php $e = $row['employee']; @endphp
            <tr>
              <td>{{ $e->nik }}</td>
              <td>{{ $e->name }}</td>
              <td>{{ format_date_id($e->date_of_birth) }}</td>
              <td>{{ $row['age'] !== null ? $row['age'] . ' th' : '-' }}</td>
              <td>{{ $row['retire_age'] !== null ? $row['retire_age'] . ' th' : '-' }}</td>
              <td>{{ $row['retire_year'] ?? '-' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-muted">Belum ada data karyawan tetap.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="small text-muted mt-2">
      @if (($retirementMethod ?? 'government') === 'company_policy')
        Usia pensiun mengikuti kebijakan perusahaan dengan usia pensiun {{ $customRetireAge ?? 55 }} tahun.
      @else
        Usia pensiun mengikuti jadwal kenaikan bertahap sesuai ketentuan PP 45/2015 (JP BPJS).
        Silakan cek kembali kebijakan internal bila berbeda.
      @endif
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-2">
      <div class="fw-semibold">Perhitungan Pensiun (Fleksibel)</div>
      <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#pensionLegalModal">
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
        <label class="form-label">Faktor Pesangon</label>
        <input type="number" step="0.01" class="form-control" name="severance_factor" value="{{ old('severance_factor', $calc['severance_factor'] ?? '') }}" placeholder="Contoh: 1.75">
      </div>
      <div class="col-md-4">
        <label class="form-label">Faktor UPMK</label>
        <input type="number" step="0.01" class="form-control" name="upmk_factor" value="{{ old('upmk_factor', $calc['upmk_factor'] ?? '') }}" placeholder="Contoh: 1.00">
      </div>
      <div class="col-md-4">
        <label class="form-label">UPH % (dari Pesangon+UPMK)</label>
        <input type="number" step="0.01" class="form-control" name="uph_percent" value="{{ old('uph_percent', $calc['uph_percent'] ?? '') }}" placeholder="Contoh: 15">
      </div>
      <div class="col-md-4">
        <label class="form-label">Pesangon Manual (Rp)</label>
        <input type="number" step="0.01" class="form-control" name="manual_severance" value="{{ old('manual_severance', $calc['manual_severance'] ?? '') }}">
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
          Isi faktor untuk estimasi otomatis, lalu silakan koreksi dengan angka manual agar fleksibel mengikuti UU/PP & kebijakan perusahaan.
        </div>
      </div>
    </form>

    @if ($calc)
      <div class="row g-2 mt-3">
        <div class="col-md-4">
          <div class="border rounded p-2">
            <div class="small text-muted">Estimasi Pesangon (Auto)</div>
            <div class="fw-semibold">{{ format_currency_id($calc['auto_severance'] ?? 0, 2, true) }}</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded p-2">
            <div class="small text-muted">Estimasi UPMK (Auto)</div>
            <div class="fw-semibold">{{ format_currency_id($calc['auto_upmk'] ?? 0, 2, true) }}</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded p-2">
            <div class="small text-muted">Estimasi UPH (Auto)</div>
            <div class="fw-semibold">{{ format_currency_id($calc['auto_uph'] ?? 0, 2, true) }}</div>
          </div>
        </div>
        <div class="col-md-12">
          <div class="border rounded p-2 bg-light">
            <div class="small text-muted">Total Manual (Pesangon + UPMK + UPH)</div>
            <div class="fs-5 fw-semibold">{{ format_currency_id($calc['total'] ?? 0, 2, true) }}</div>
          </div>
        </div>
      </div>
    @endif
  </div>
</div>
<script>
  (function () {
    var methodSelect = document.getElementById('retirement_method');
    var customWrap = document.getElementById('custom_retire_age_wrap');
    var customInput = document.getElementById('custom_retire_age');
    if (!methodSelect || !customWrap || !customInput) return;

    function syncRetirementMethod() {
      var isCompanyPolicy = methodSelect.value === 'company_policy';
      customWrap.classList.toggle('d-none', !isCompanyPolicy);
      customInput.required = isCompanyPolicy;
      if (!isCompanyPolicy) {
        customInput.value = '';
      } else if (!customInput.value) {
        customInput.value = '55';
      }
    }

    methodSelect.addEventListener('change', syncRetirementMethod);
    syncRetirementMethod();
  })();
</script>
@endsection

@section('modals')
<div class="modal fade" id="pensionLegalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Dasar Hukum & Panduan Pengisian</h5>
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
          <li>
            <a href="https://peraturan.bpk.go.id/Home/Details/5613/pp-no-45-tahun-2015" target="_blank" rel="noopener">
              PP No. 45 Tahun 2015
            </a>
            (Program Jaminan Pensiun, usia pensiun).
          </li>
        </ul>
        <div class="fw-semibold mb-2">Rincian PP (Ringkas)</div>
        <ul class="mb-3">
          <li>PP 35/2021: Mengatur PKWT, alih daya, waktu kerja/istirahat, tata cara PHK, serta komponen hak pekerja seperti Uang Pesangon, UPMK, dan UPH.</li>
          <li>PP 45/2015: Usia pensiun program Jaminan Pensiun ditetapkan 56 (2015), 57 (2019), 58 (2022), 59 (2025), lalu naik 1 tahun setiap 3 tahun hingga 65.</li>
        </ul>
                <div class="fw-semibold mb-2">Ringkasan Pasal Utama</div>
        <div class="mb-2">
          <div class="fw-semibold">1) PP 35/2021 - Pensiun & Kompensasi</div>
          <div class="small text-muted">Pasal 56 (PHK karena pensiun + hak kompensasi)</div>
          <ul class="mb-2">
            <li>Pesangon: 1,75x ketentuan pesangon normal.</li>
            <li>UPMK: 1x ketentuan UPMK.</li>
            <li>UPH: sesuai ketentuan peraturan.</li>
          </ul>
          <div class="small text-muted">Pasal 40 ayat (2) - Tabel Pesangon (dasar perhitungan)</div>
          <div class="table-responsive mb-2">
            <table class="table table-sm table-bordered mb-0">
              <thead>
                <tr>
                  <th>Masa Kerja</th>
                  <th>Pesangon</th>
                </tr>
              </thead>
              <tbody>
                <tr><td>&lt; 1 tahun</td><td>1 bulan upah</td></tr>
                <tr><td>1-2 tahun</td><td>2 bulan upah</td></tr>
                <tr><td>2-3 tahun</td><td>3 bulan upah</td></tr>
                <tr><td>3-4 tahun</td><td>4 bulan upah</td></tr>
                <tr><td>4-5 tahun</td><td>5 bulan upah</td></tr>
                <tr><td>5-6 tahun</td><td>6 bulan upah</td></tr>
                <tr><td>6-7 tahun</td><td>7 bulan upah</td></tr>
                <tr><td>7-8 tahun</td><td>8 bulan upah</td></tr>
                <tr><td>&ge; 8 tahun</td><td>9 bulan upah</td></tr>
              </tbody>
            </table>
          </div>
          <div class="small text-muted mb-2">Untuk pensiun, angka pesangon di atas dikalikan 1,75.</div>

          <div class="small text-muted">Pasal 40 ayat (3) - Tabel UPMK</div>
          <div class="table-responsive mb-2">
            <table class="table table-sm table-bordered mb-0">
              <thead>
                <tr>
                  <th>Masa Kerja</th>
                  <th>UPMK</th>
                </tr>
              </thead>
              <tbody>
                <tr><td>3-6 tahun</td><td>2 bulan upah</td></tr>
                <tr><td>6-9 tahun</td><td>3 bulan upah</td></tr>
                <tr><td>9-12 tahun</td><td>4 bulan upah</td></tr>
                <tr><td>12-15 tahun</td><td>5 bulan upah</td></tr>
                <tr><td>15-18 tahun</td><td>6 bulan upah</td></tr>
                <tr><td>18-21 tahun</td><td>7 bulan upah</td></tr>
                <tr><td>21-24 tahun</td><td>8 bulan upah</td></tr>
                <tr><td>&ge; 24 tahun</td><td>10 bulan upah</td></tr>
              </tbody>
            </table>
          </div>

          <div class="small text-muted">Pasal 40 ayat (4) - Uang Penggantian Hak (UPH)</div>
          <ul class="mb-2">
            <li>Sisa cuti tahunan yang belum diambil dan belum gugur.</li>
            <li>Biaya pulang untuk pekerja dan keluarganya ke tempat di mana pekerja diterima bekerja.</li>
            <li>Hak lain sesuai perjanjian kerja/PP/PKB.</li>
          </ul>

          <div class="small text-muted">Pasal 58 - Jika perusahaan memiliki program dana pensiun</div>
          <ul class="mb-3">
            <li>Iuran perusahaan dapat diperhitungkan sebagai bagian pesangon.</li>
            <li>Jika manfaat dana pensiun lebih kecil dari pesangon, perusahaan wajib membayar selisihnya.</li>
          </ul>
        </div>

        <div class="mb-2">
          <div class="fw-semibold">2) PP 45/2015 - Usia Pensiun Program Jaminan Pensiun</div>
          <div class="small text-muted">Pasal 15 - usia pensiun bertahap</div>
          <ul class="mb-2">
            <li>Usia pensiun pertama 56 tahun.</li>
            <li>Mulai 1 Januari 2019 menjadi 57 tahun.</li>
            <li>Selanjutnya naik 1 tahun setiap 3 tahun sampai maksimum 65 tahun.</li>
          </ul>
          <div class="table-responsive mb-2">
            <table class="table table-sm table-bordered mb-0">
              <thead>
                <tr>
                  <th>Tahun</th>
                  <th>Usia Pensiun</th>
                </tr>
              </thead>
              <tbody>
                <tr><td>2015</td><td>56 tahun</td></tr>
                <tr><td>2019</td><td>57 tahun</td></tr>
                <tr><td>2022</td><td>58 tahun</td></tr>
                <tr><td>2025</td><td>59 tahun</td></tr>
                <tr><td>2028</td><td>60 tahun</td></tr>
                <tr><td>2031</td><td>61 tahun</td></tr>
                <tr><td>2034</td><td>62 tahun</td></tr>
                <tr><td>2037</td><td>63 tahun</td></tr>
                <tr><td>2040</td><td>64 tahun</td></tr>
                <tr><td>2043</td><td>65 tahun</td></tr>
              </tbody>
            </table>
          </div>
          <div class="small text-muted mb-2">Makna: usia pensiun JP naik otomatis menyesuaikan harapan hidup dan keberlanjutan dana.</div>
          <div class="small text-muted">Catatan: usia pensiun BPJS tidak harus sama dengan usia pensiun perusahaan (bisa 55/56/58/59 sesuai kebijakan internal).</div>
        </div>

<div class="fw-semibold mb-2">Cara Mengisi</div>
        <ul class="mb-0">
          <li>Isi faktor untuk estimasi otomatis sesuai ketentuan regulasi yang berlaku.</li>
          <li>Gunakan kolom manual jika ada kebijakan perusahaan atau kesepakatan PKB/PP yang berbeda.</li>
          <li>Pastikan data upah dasar dan masa kerja sudah sesuai sebelum menetapkan faktor.</li>
        </ul>
        <div class="small text-muted mt-3">
          Catatan: Jika terjadi perubahan regulasi, sesuaikan faktor/angka manual agar perhitungan tetap akurat.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
@endsection
