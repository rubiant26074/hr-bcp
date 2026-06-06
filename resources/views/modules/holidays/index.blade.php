@extends('layouts.app')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">Libur Nasional</h4>
  <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#holidayForm" aria-expanded="false" aria-controls="holidayForm">
    {{ $edit ? 'Edit Libur' : 'Tambah Libur' }}
  </button>
</div>

@if (!empty($errors))
  @foreach ($errors as $m)
    <div class="alert alert-danger">{{ $m }}</div>
  @endforeach
@endif
@if (!empty($messages))
  @foreach ($messages as $m)
    <div class="alert alert-success">{{ $m }}</div>
  @endforeach
@endif

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="post" class="row g-2 align-items-end mb-3" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="action" value="import_file">
      <div class="col-md-5">
        <label class="form-label">Import CSV/Excel</label>
        <input type="file" class="form-control" name="holiday_file" accept=".csv,.xlsx,.xls" required>
        <div class="form-text">Header wajib: <code>holiday_date</code>, <code>holiday_name</code>.</div>
      </div>
      <div class="col-md-3">
        <button type="submit" class="btn btn-outline-primary w-100">Import File</button>
      </div>
      <div class="col-md-4 text-md-end">
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="{{ route('holidays.template') }}">Download Template</a>
      </div>
    </form>

    <div class="collapse mb-3 {{ $edit ? 'show' : '' }}" id="holidayForm">
      <div class="card shadow-sm">
        <div class="card-body">
          <form method="post" class="row g-2 align-items-end">
      @csrf
      <input type="hidden" name="action" value="{{ $edit ? 'update' : 'create' }}">
      <input type="hidden" name="id" value="{{ $edit->id ?? '' }}">
      <div class="col-md-3">
        <label class="form-label">Tanggal</label>
        <input type="date" class="form-control" name="holiday_date" value="{{ old('holiday_date', $edit->holiday_date ?? '') }}" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Nama Libur</label>
        <input type="text" class="form-control" name="name" placeholder="Contoh: Idul Fitri" value="{{ old('name', $edit->name ?? '') }}">
      </div>
      <div class="col-md-3">
        <button type="submit" class="btn btn-success w-100">{{ $edit ? 'Update' : 'Tambah' }}</button>
      </div>
    </form>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <table class="table table-sm table-striped align-middle">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Nama</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($items as $item)
          <tr>
            <td>{{ format_date_id($item->holiday_date) }}</td>
            <td>{{ $item->name ?? '-' }}</td>
            <td class="text-end">
              <a class="icon-btn icon-edit" href="{{ route('holidays.index', ['edit' => $item->id]) }}" title="Edit">
                <span class="icon i-edit" aria-hidden="true"></span>
              </a>
              <form method="post" class="d-inline" onsubmit="return confirm('Hapus libur ini?')">
                @csrf
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="{{ $item->id }}">
                <button class="icon-btn icon-delete" title="Delete" type="submit">
                  <span class="icon i-trash" aria-hidden="true"></span>
                </button>
              </form>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="3" class="text-muted">Belum ada data libur nasional.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
