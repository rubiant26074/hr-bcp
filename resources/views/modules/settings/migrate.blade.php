@extends('layouts.app')

@section('content')
<h4 class="mb-3">Update Database</h4>

@foreach ($messages as $m)
  <div class="alert alert-success">{{ $m }}</div>
@endforeach
@foreach ($errors as $e)
  <div class="alert alert-danger">{{ $e }}</div>
@endforeach

<div class="card shadow-sm">
  <div class="card-body">
    <div class="alert alert-warning">
      Fitur ini menjalankan migration Laravel dari browser untuk hosting cPanel yang tidak menyediakan terminal.
      Lakukan backup database terlebih dahulu sebelum menjalankan update.
    </div>

    <form method="post">
      @csrf
      <div class="mb-3">
        <label class="form-label">Ketik MIGRATE untuk konfirmasi</label>
        <input type="text" class="form-control" name="confirm_text" placeholder="MIGRATE">
      </div>
      <button class="btn btn-primary" type="submit">Jalankan Update Database</button>
      <a class="btn btn-outline-secondary" href="{{ route('settings.index') }}">Kembali</a>
    </form>

    @if (!empty($output))
      <div class="mt-3">
        <label class="form-label">Output</label>
        <pre class="bg-light border rounded p-3 small mb-0" style="white-space:pre-wrap;">{{ $output }}</pre>
      </div>
    @endif
  </div>
</div>
@endsection
