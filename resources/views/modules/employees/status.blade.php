@extends('layouts.app')

@section('content')
<h4 class="mb-3">Form Status Karyawan</h4>
<div class="d-flex gap-2 flex-wrap mb-3">
  <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#statusForm" aria-expanded="false" aria-controls="statusForm">
    {{ $edit ? 'Edit Status' : 'Add Status' }}
  </button>
  <a class="btn btn-outline-secondary" href="{{ route('employees.index') }}">Back</a>
  @if ($edit)
    <a class="btn btn-outline-danger" href="{{ route('employees.status') }}">Batal Edit</a>
  @endif
</div>
<div class="collapse mb-3 {{ $edit ? 'show' : '' }}" id="statusForm">
  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post" action="{{ route('employees.status') }}">
        @csrf
        <input type="hidden" name="action" value="{{ $edit ? 'update' : 'save' }}">
        <input type="hidden" name="id" value="{{ $edit->id ?? '' }}">
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Status Karyawan</label>
            <input type="text" class="form-control" name="status_name" placeholder="Contoh: Kontrak / Tetap" value="{{ old('status_name', $edit->status_name ?? '') }}" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Keterangan</label>
            <input type="text" class="form-control" name="status_note" placeholder="Keterangan" value="{{ old('status_note', $edit->note ?? '') }}">
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-success w-100">{{ $edit ? 'Update' : 'Save' }}</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-12">
    <div class="card shadow-sm">
      <div class="card-body">
        <table class="table table-striped table-sm">
          <thead>
            <tr>
              <th>ID</th>
              <th>Status Karyawan</th>
              <th>Keterangan</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($statuses ?? [] as $s)
              <tr>
                <td>{{ $s->id }}</td>
                <td>{{ $s->status_name }}</td>
                <td>{{ $s->note }}</td>
                <td class="text-end">
                  <a class="icon-btn icon-edit" href="{{ route('employees.status', ['edit' => $s->id]) }}" title="Edit">
                    <span class="icon i-edit" aria-hidden="true"></span>
                  </a>
                  <form method="post" action="{{ route('employees.status') }}" class="d-inline" onsubmit="return confirm('Hapus status ini?');">
                    @csrf
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="{{ $s->id }}">
                    <button type="submit" class="icon-btn icon-delete" title="Delete">
                      <span class="icon i-trash" aria-hidden="true"></span>
                    </button>
                  </form>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="text-muted">Belum ada data.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
