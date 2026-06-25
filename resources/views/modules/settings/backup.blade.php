@extends('layouts.app')

@section('content')
<h4 class="mb-3">Backup Database</h4>

@foreach ($messages as $m)
  <div class="alert alert-success">{{ $m }}</div>
@endforeach
@foreach ($errors as $e)
  <div class="alert alert-danger">{{ $e }}</div>
@endforeach

<div class="card shadow-sm">
  <div class="card-body">
    <div class="alert alert-info">
      Backup akan menghasilkan file SQL lengkap (struktur + data).
    </div>

    <div class="row g-3 align-items-end">
      <div class="col-lg-5">
        <form method="post">
          @csrf
          <input type="hidden" name="action" value="save_schedule">
          <label class="form-label">Jadwal Auto Backup</label>
          <select class="form-select" name="backup_frequency">
            <option value="manual" {{ ($autoBackup['frequency'] ?? 'manual') === 'manual' ? 'selected' : '' }}>Manual / Nonaktif</option>
            <option value="daily" {{ ($autoBackup['frequency'] ?? '') === 'daily' ? 'selected' : '' }}>Setiap Hari</option>
            <option value="weekly" {{ ($autoBackup['frequency'] ?? '') === 'weekly' ? 'selected' : '' }}>Setiap Minggu</option>
            <option value="monthly" {{ ($autoBackup['frequency'] ?? '') === 'monthly' ? 'selected' : '' }}>Setiap Bulan</option>
          </select>
          <button class="btn btn-success mt-2" type="submit">Simpan Jadwal</button>
        </form>
      </div>
      <div class="col-lg-7">
        <div class="border rounded p-3 bg-light">
          <div><strong>Status:</strong> {{ ($autoBackup['frequency'] ?? 'manual') === 'manual' ? 'Manual / Nonaktif' : 'Aktif' }}</div>
          <div><strong>Terakhir backup:</strong> {{ $autoBackup['last_run_at'] ?? '-' }}</div>
          <div><strong>Backup berikutnya:</strong> {{ $autoBackup['next_run_text'] ?? '-' }}</div>
          <div><strong>File terakhir:</strong> {{ $autoBackup['last_file'] ?? '-' }}</div>
        </div>
      </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-3">
      <form method="post">
        @csrf
        <input type="hidden" name="action" value="backup">
        <button class="btn btn-primary" type="submit">Download Backup SQL</button>
      </form>
      <form method="post">
        @csrf
        <input type="hidden" name="action" value="run_auto_backup">
        <button class="btn btn-outline-primary" type="submit">Jalankan Auto Backup Sekarang</button>
      </form>
      <a class="btn btn-outline-secondary" href="{{ route('settings.index') }}">Kembali</a>
    </div>

    <div class="alert alert-secondary mt-3 mb-0">
      Agar auto backup berjalan otomatis di cPanel, tambahkan cron job:
      <code>php {{ base_path('artisan') }} backup:auto</code>
    </div>

    @if (!empty($recentBackups))
      <div class="mt-3">
        <div class="fw-semibold mb-2">Riwayat Auto Backup</div>
        <div class="table-responsive">
          <table class="table table-sm table-bordered mb-0">
            <thead>
              <tr>
                <th>File</th>
                <th>Ukuran</th>
                <th>Dibuat</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($recentBackups as $backup)
                <tr>
                  <td>{{ $backup['name'] }}</td>
                  <td>{{ number_format(($backup['size'] ?? 0) / 1024, 2, ',', '.') }} KB</td>
                  <td>{{ $backup['modified_at'] }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endif
  </div>
</div>

<div class="card shadow-sm border-danger mt-3">
  <div class="card-body">
    <div class="alert alert-warning">
      Restore akan mengeksekusi file SQL. Pastikan file valid dan sesuai versi database.
    </div>
    <form method="post" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="action" value="restore">
      <div class="mb-3">
        <label class="form-label">Mode Restore</label>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="restore_mode" id="modeDrop" value="drop" checked>
          <label class="form-check-label" for="modeDrop">Drop + Recreate (default)</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="restore_mode" id="modeAppend" value="append">
          <label class="form-check-label" for="modeAppend">Append (tanpa DROP/CREATE)</label>
        </div>
        <div class="form-text">Append akan melewati query DROP TABLE dan CREATE TABLE.</div>
      </div>
      <div class="mb-3">
        <label class="form-label">File SQL (max 70 MB)</label>
        <input type="file" class="form-control" name="sql_file" accept=".sql">
      </div>
      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" name="confirm_restore" id="confirmRestore" value="1">
        <label class="form-check-label" for="confirmRestore">Saya mengerti dan ingin melakukan restore database.</label>
      </div>
      <div class="mb-3">
        <label class="form-label">Ketik RESTORE untuk konfirmasi</label>
        <input type="text" class="form-control" name="confirm_text" placeholder="RESTORE">
      </div>
      <button class="btn btn-danger" type="submit">Restore Database</button>
    </form>
  </div>
</div>
@endsection
