@extends('layouts.app')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <div>
    <h4 class="mb-1">Bantuan / Panduan</h4>
    <div class="text-muted small">Panduan interaktif, menyeluruh, dan ringkas untuk seluruh modul.</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('dashboard') }}">Ke Dashboard</a>
    <a class="btn btn-outline-primary btn-sm" href="{{ route('notifications.index') }}">Notifikasi</a>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-2 align-items-end">
      <div class="col-md-5">
        <label class="form-label">Cari Panduan</label>
        <input type="text" class="form-control" id="helpSearch" placeholder="Ketik kata kunci (contoh: payroll, approval, absensi)">
      </div>
      <div class="col-md-4">
        <label class="form-label">Filter Kategori</label>
        <select class="form-select" id="helpFilter">
          <option value="all">Semua</option>
          <option value="core">Core</option>
          <option value="master">Master Data</option>
          <option value="ops">Operations</option>
          <option value="reports">Reports</option>
          <option value="leave">Perizinan</option>
          <option value="settings">Settings</option>
          <option value="approval">Approval</option>
          <option value="bank">Payroll Bank</option>
        </select>
      </div>
      <div class="col-md-3 text-md-end">
        <label class="form-label d-none d-md-block">&nbsp;</label>
        <button class="btn btn-outline-secondary w-100" type="button" id="helpReset">Reset Filter</button>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
  <div class="card-body">
    <div class="fw-semibold mb-2">Quick Start</div>
    <div class="small text-muted mb-2">Urutan kerja paling aman untuk memulai.</div>
    <ol class="mb-0">
      <li>Isi Master Company dan setting dasar kerja.</li>
      <li>Input data karyawan lengkap (termasuk bank).</li>
      <li>Import Absensi atau gunakan Absensi Mobile.</li>
      <li>Buat Payroll Period, Run Payroll, Review Payroll.</li>
      <li>Ajukan Approval Payroll Report dan download bank.</li>
    </ol>
  </div>
</div>
  </div>
  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="fw-semibold mb-2">Checklist Data Wajib</div>
        <div class="small text-muted mb-2">Cegah error saat payroll.</div>
        <ul class="mb-0">
          <li>Company: Nama, Code, Bank, Rekening Debet.</li>
          <li>Employee: NIK, Nama, NPWP, Bank, Rekening.</li>
          <li>Absensi: Log harian tersedia.</li>
          <li>Approval Settings: Payroll Report & PPh21.</li>
        </ul>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="fw-semibold mb-2">Peran & Akses</div>
        <div class="small text-muted mb-2">Ringkasan per role umum.</div>
        <ul class="mb-0">
          <li>HR: Master Data, Absensi, Perizinan.</li>
          <li>Finance: Payroll Report, Bank Transfer.</li>
          <li>CEO/CFA: Approval & Summary.</li>
          <li>Employee: Slip Gaji, Perizinan.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<div class="accordion mt-3" id="helpAccordion">
  <div class="accordion-item help-item" data-tags="core dashboard notifikasi tv akun profil">
    <h2 class="accordion-header" id="hCore">
      <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#cCore" aria-expanded="true" aria-controls="cCore">
        Core
      </button>
    </h2>
    <div id="cCore" class="accordion-collapse collapse show" aria-labelledby="hCore" data-bs-parent="#helpAccordion">
      <div class="accordion-body">
        <div class="fw-semibold">Dashboard</div>
        <div class="text-muted small mb-2">Ringkasan KPI utama (absensi, payroll, aktivitas).</div>
        <ol class="small mb-3">
          <li>Pilih company (jika role global).</li>
          <li>Periksa ringkasan absensi dan payroll.</li>
          <li>Klik kartu metrik untuk detail jika tersedia.</li>
        </ol>
        <div class="fw-semibold">Notifikasi</div>
        <div class="text-muted small mb-2">Pengajuan approval, status payroll, dan informasi penting.</div>
        <ol class="small mb-3">
          <li>Buka Notifikasi untuk item terbaru.</li>
          <li>Klik item untuk menuju halaman terkait.</li>
        </ol>
        <div class="fw-semibold">TV Dashboard</div>
        <div class="text-muted small mb-2">Tampilan ringkas untuk monitor/TV perusahaan.</div>
        <ol class="small mb-3">
          <li>Gunakan mode layar penuh saat display.</li>
          <li>Pastikan data absensi ter-update.</li>
        </ol>
        <div class="fw-semibold">Profil Saya / Akun</div>
        <div class="text-muted small mb-2">Ubah data akun dan password.</div>
        <ol class="small mb-0">
          <li>Masuk ke Profil Saya / Akun.</li>
          <li>Perbarui data yang diperlukan.</li>
          <li>Simpan perubahan.</li>
        </ol>
      </div>
    </div>
  </div>

  <div class="accordion-item help-item" data-tags="master data company employees kontrak cuti libur">
    <h2 class="accordion-header" id="hMaster">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cMaster" aria-expanded="false" aria-controls="cMaster">
        Master Data
      </button>
    </h2>
    <div id="cMaster" class="accordion-collapse collapse" aria-labelledby="hMaster" data-bs-parent="#helpAccordion">
      <div class="accordion-body">
        <div class="fw-semibold">Company</div>
        <div class="text-muted small mb-2">Isi data perusahaan, bank, dan rekening debet. Wajib untuk payroll bank transfer.</div>
        <ol class="small mb-3">
          <li>Masuk Master Data → Company.</li>
          <li>Tambah atau Edit company.</li>
          <li>Isi Nama, Code, NPWP, Bank, Rekening Debet.</li>
          <li>Simpan lalu cek Detail Company.</li>
        </ol>
        <div class="fw-semibold">Employees</div>
        <div class="text-muted small mb-2">Pastikan bank & nomor rekening karyawan lengkap untuk transfer payroll.</div>
        <ol class="small mb-3">
          <li>Masuk Master Data → Employees.</li>
          <li>Tambah karyawan atau import template.</li>
          <li>Lengkapi NPWP, bank, rekening, dan dokumen wajib.</li>
          <li>Simpan dan pastikan status aktif.</li>
        </ol>
        <div class="fw-semibold">Contracts</div>
        <div class="text-muted small mb-2">Kelola kontrak karyawan dan masa kontrak.</div>
        <ol class="small mb-3">
          <li>Masuk Master Data → Contracts.</li>
          <li>Tambah manual atau import template.</li>
          <li>Isi Contract Type, Start/End Date.</li>
        </ol>
        <div class="fw-semibold">Management Cuti</div>
        <div class="text-muted small mb-2">Monitoring kuota & penggunaan cuti tahunan.</div>
        <ol class="small mb-3">
          <li>Masuk Master Data → Management Cuti.</li>
          <li>Cek sisa cuti per karyawan.</li>
          <li>Gunakan generate cuti lebaran jika perlu.</li>
        </ol>
        <div class="fw-semibold">Libur Nasional</div>
        <div class="text-muted small">Import libur untuk perhitungan cuti/absensi.</div>
        <ol class="small mb-0">
          <li>Masuk Master Data → Libur Nasional.</li>
          <li>Download template atau input manual.</li>
          <li>Import file dan cek daftar libur.</li>
        </ol>
      </div>
    </div>
  </div>

  <div class="accordion-item help-item" data-tags="operations absensi import log rekap payroll period run review slip">
    <h2 class="accordion-header" id="hOps">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cOps" aria-expanded="false" aria-controls="cOps">
        Operations
      </button>
    </h2>
    <div id="cOps" class="accordion-collapse collapse" aria-labelledby="hOps" data-bs-parent="#helpAccordion">
      <div class="accordion-body">
        <div class="fw-semibold">Import Absensi</div>
        <div class="text-muted small mb-2">Upload CSV fingerprint. Gunakan template agar header sesuai.</div>
        <ol class="small mb-3">
          <li>Masuk Operations → Import Absensi.</li>
          <li>Download template, isi data, lalu upload.</li>
          <li>Periksa pesan sukses dan unknown employee.</li>
        </ol>
        <div class="fw-semibold">Absensi Mobile</div>
        <div class="text-muted small mb-2">Absensi dengan face recognition + radius lokasi.</div>
        <ol class="small mb-3">
          <li>Pastikan lokasi absen sudah diatur.</li>
          <li>Enroll wajah pada menu Absensi Mobile.</li>
          <li>Check-in/out saat berada di radius lokasi.</li>
        </ol>
        <div class="fw-semibold">Log Absensi</div>
        <div class="text-muted small mb-2">Audit data mentah sebelum rekap.</div>
        <ol class="small mb-3">
          <li>Filter log berdasarkan tanggal atau karyawan.</li>
          <li>Hapus log salah bila perlu.</li>
        </ol>
        <div class="fw-semibold">Rekap Harian / Bulanan</div>
        <div class="text-muted small mb-2">Gunakan rebuild bila log sudah tersedia tetapi rekap kosong.</div>
        <ol class="small mb-3">
          <li>Pilih tanggal/bulan yang ingin dicek.</li>
          <li>Klik Rebuild jika data kosong.</li>
          <li>Verifikasi overtime/cuti bila diperlukan.</li>
        </ol>
        <div class="fw-semibold">Payroll Period</div>
        <div class="text-muted small mb-2">Periode payroll hanya bisa dibuat jika ada data absensi.</div>
        <ol class="small mb-3">
          <li>Masuk Operations → Payroll Period.</li>
          <li>Buat periode baru sesuai bulan/tahun.</li>
          <li>Jika gagal, pastikan absensi ada.</li>
        </ol>
        <div class="fw-semibold">Run Payroll & Review</div>
        <div class="text-muted small mb-2">Review sebelum approval report.</div>
        <ol class="small mb-3">
          <li>Run Payroll untuk periode aktif.</li>
          <li>Review hasil payroll per karyawan.</li>
          <li>Perbaiki data jika ada kejanggalan.</li>
        </ol>
        <div class="fw-semibold">Slip Gaji</div>
        <div class="text-muted small mb-2">Slip bisa diunduh PDF per karyawan.</div>
        <ol class="small mb-0">
          <li>Pilih periode dan karyawan.</li>
          <li>Download slip PDF.</li>
        </ol>
      </div>
    </div>
  </div>

  <div class="accordion-item help-item" data-tags="reports payroll report pph21 approval bank transfer csv txt bni bsi">
    <h2 class="accordion-header" id="hReports">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cReports" aria-expanded="false" aria-controls="cReports">
        Reports & Approval
      </button>
    </h2>
    <div id="cReports" class="accordion-collapse collapse" aria-labelledby="hReports" data-bs-parent="#helpAccordion">
      <div class="accordion-body">
        <div class="fw-semibold">Payroll Report</div>
        <div class="text-muted small mb-2">Harus approval sebelum export dan sebelum download bank.</div>
        <ol class="small mb-3">
          <li>Masuk Reports → Payroll Report.</li>
          <li>Ajukan approval.</li>
          <li>Setelah approved, export dan download bank.</li>
        </ol>
        <div class="fw-semibold">PPh21</div>
        <div class="text-muted small mb-2">Approval PPh21 mengikuti approval settings.</div>
        <ol class="small mb-3">
          <li>Masuk Reports → PPh21.</li>
          <li>Ajukan approval.</li>
          <li>Approval dilakukan sesuai setting.</li>
        </ol>
        <div class="fw-semibold">Bank Transfer</div>
        <div class="text-muted small mb-2">BNI: CSV Inhouse. BSI: TXT Payroll Multi Service.</div>
        <ol class="small mb-3">
          <li>Pastikan bank & rekening karyawan valid.</li>
          <li>Pastikan company bank & rekening debet terisi.</li>
          <li>Download file sesuai bank perusahaan.</li>
        </ol>
        <div class="fw-semibold">Approval Settings</div>
        <div class="text-muted small mb-2">Atur approver per module untuk tiap company.</div>
        <ol class="small mb-0">
          <li>Masuk Settings → Approval Settings.</li>
          <li>Pilih company & module.</li>
          <li>Tentukan approver step 1 & 2.</li>
        </ol>
      </div>
    </div>
  </div>

  <div class="accordion-item help-item" data-tags="perizinan izin cuti sakit lembur out office">
    <h2 class="accordion-header" id="hLeave">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cLeave" aria-expanded="false" aria-controls="cLeave">
        Perizinan
      </button>
    </h2>
    <div id="cLeave" class="accordion-collapse collapse" aria-labelledby="hLeave" data-bs-parent="#helpAccordion">
      <div class="accordion-body">
        <div class="fw-semibold">Tidak Masuk Kerja (Izin/Cuti/Sakit)</div>
        <div class="text-muted small mb-2">Preview selalu tersedia untuk yang sudah approve.</div>
        <ol class="small mb-3">
          <li>Buat pengajuan izin/cuti/sakit.</li>
          <li>Upload lampiran jika perlu.</li>
          <li>Approve melalui notifikasi atau list.</li>
        </ol>
        <div class="fw-semibold">Izin Keluar Kantor</div>
        <div class="text-muted small mb-2">PDF layout seragam dengan modul izin lain.</div>
        <ol class="small mb-3">
          <li>Buat pengajuan izin keluar.</li>
          <li>Approve sesuai alur.</li>
          <li>Download/preview PDF bila diperlukan.</li>
        </ol>
        <div class="fw-semibold">Lembur</div>
        <div class="text-muted small mb-2">Export dan preview memakai icon.</div>
        <ol class="small mb-0">
          <li>Buat pengajuan lembur.</li>
          <li>Approve oleh atasan/HR.</li>
          <li>Preview tetap tersedia setelah approve.</li>
        </ol>
      </div>
    </div>
  </div>

  <div class="accordion-item help-item" data-tags="settings rbac role approval lokasi backup">
    <h2 class="accordion-header" id="hSettings">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cSettings" aria-expanded="false" aria-controls="cSettings">
        Settings
      </button>
    </h2>
    <div id="cSettings" class="accordion-collapse collapse" aria-labelledby="hSettings" data-bs-parent="#helpAccordion">
      <div class="accordion-body">
        <div class="fw-semibold">Setting Lokasi Absen</div>
        <div class="text-muted small mb-2">Wajib untuk Absensi Mobile.</div>
        <ol class="small mb-3">
          <li>Masuk Settings → Setting Lokasi Absen.</li>
          <li>Input koordinat dan radius.</li>
          <li>Simpan dan uji Absensi Mobile.</li>
        </ol>
        <div class="fw-semibold">Approval Settings</div>
        <div class="text-muted small mb-2">Atur approver per module & requester.</div>
        <ol class="small mb-3">
          <li>Pilih company.</li>
          <li>Set approver untuk Payroll Report & PPh21.</li>
        </ol>
        <div class="fw-semibold">User & Role Management</div>
        <div class="text-muted small mb-2">Pastikan role memiliki hak akses yang tepat.</div>
        <ol class="small mb-3">
          <li>Kelola user dan assign role.</li>
          <li>Atur hak akses di RBAC jika dibutuhkan.</li>
        </ol>
        <div class="fw-semibold">Backup Database</div>
        <div class="text-muted small mb-0">Saran lakukan backup sebelum reset atau update besar.</div>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    var search = document.getElementById('helpSearch');
    var filter = document.getElementById('helpFilter');
    var reset = document.getElementById('helpReset');
    var items = Array.prototype.slice.call(document.querySelectorAll('.help-item'));

    function applyFilter() {
      var q = (search.value || '').toLowerCase().trim();
      var f = (filter.value || 'all').toLowerCase();
      items.forEach(function (item) {
        var tags = (item.getAttribute('data-tags') || '').toLowerCase();
        var matchesText = q === '' || tags.indexOf(q) !== -1;
        var matchesFilter = f === 'all' || tags.indexOf(f) !== -1;
        var show = matchesText && matchesFilter;
        item.style.display = show ? '' : 'none';
      });
    }

    function resetFilter() {
      search.value = '';
      filter.value = 'all';
      applyFilter();
    }

    if (search) search.addEventListener('input', applyFilter);
    if (filter) filter.addEventListener('change', applyFilter);
    if (reset) reset.addEventListener('click', resetFilter);
  })();
</script>
@endsection
