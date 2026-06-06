@extends('layouts.app')

@section('content')
<h4 class="mb-3 text-danger">Reset Database</h4>

@foreach ($messages as $m)
  <div class="alert alert-success">{{ $m }}</div>
@endforeach
@foreach ($errors as $e)
  <div class="alert alert-danger">{{ $e }}</div>
@endforeach

<div class="card shadow-sm border-danger">
  <div class="card-body">
    <div class="alert alert-warning">
      Tindakan ini akan menghapus data operasional berikut:
      <ul class="mb-0">
        <li>attendance_logs</li>
        <li>attendance_daily</li>
        <li>payroll_items</li>
        <li>payroll_period</li>
      </ul>
      Data karyawan (employees) dan master data tidak dihapus.
    </div>

    <form method="post">
      @csrf
      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" name="confirm_reset" id="confirmReset" value="1">
        <label class="form-check-label" for="confirmReset">Saya mengerti dan ingin melakukan reset database.</label>
      </div>
      <div class="mb-3">
        <label class="form-label">Ketik RESET untuk konfirmasi</label>
        <input type="text" class="form-control" name="confirm_text" placeholder="RESET">
      </div>
      <button class="btn btn-danger" type="submit">Reset Database</button>
      <a class="btn btn-outline-secondary" href="{{ route('settings.index') }}">Batal</a>
    </form>
  </div>
</div>
@endsection
