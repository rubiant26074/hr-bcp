@extends('layouts.app')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">Akun Saya</h4>
</div>

@if (request()->query('saved'))
  <div class="alert alert-success">Perubahan akun berhasil disimpan.</div>
@endif

<div class="row justify-content-center g-3">
  <div class="col-12 col-md-8 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <form method="post" enctype="multipart/form-data">
          @csrf
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Email</label>
              <input type="text" class="form-control" value="{{ $user->email }}" disabled>
            </div>
            <div class="col-12">
              <label class="form-label">Password Baru (kosongkan jika tidak diubah)</label>
              <input type="password" name="password" class="form-control" minlength="8">
            </div>
            <div class="col-12">
              <label class="form-label">Konfirmasi Password Baru</label>
              <input type="password" name="password_confirmation" class="form-control" minlength="8">
            </div>
            <div class="col-12">
              <label class="form-label">Tanda Tangan (JPG/PNG)</label>
              <input type="file" name="signature_file" class="form-control" accept=".jpg,.jpeg,.png">
              @if (!empty($user->signature_path))
                <div class="mt-2">
                  <img src="{{ asset($user->signature_path) }}" alt="Tanda Tangan" style="height:80px;">
                </div>
              @endif
            </div>
          </div>

          <div class="mt-3 d-grid d-sm-flex gap-2 justify-content-sm-end">
            <button class="btn btn-success" type="submit">Simpan</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
