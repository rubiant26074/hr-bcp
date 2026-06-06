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
    <form method="post">
      @csrf
      <input type="hidden" name="action" value="backup">
      <button class="btn btn-primary" type="submit">Download Backup SQL</button>
      <a class="btn btn-outline-secondary" href="{{ route('settings.index') }}">Kembali</a>
    </form>
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
