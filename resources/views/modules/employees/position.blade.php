@extends('layouts.app')

@section('content')
<h4 class="mb-3">Form Jabatan</h4>

<div class="d-flex gap-2 flex-wrap mb-3">
  <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#positionForm" aria-expanded="false" aria-controls="positionForm">
    {{ $edit ? 'Edit Jabatan' : 'Add Jabatan' }}
  </button>
  <a class="btn btn-outline-secondary" href="{{ route('employees.index') }}">Back</a>
  @if ($edit)
    <a class="btn btn-outline-danger" href="{{ route('employees.position') }}">Batal Edit</a>
  @endif
</div>

<div class="collapse mb-3 {{ $edit ? 'show' : '' }}" id="positionForm">
  <div class="card shadow-sm">
  <div class="card-body">
    <form method="post" action="{{ route('employees.position') }}">
      @csrf
      <input type="hidden" name="action" value="{{ $edit ? 'update' : 'save' }}">
      <input type="hidden" name="id" value="{{ $edit->id ?? '' }}">
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Jabatan</label>
          <input type="text" class="form-control" name="position_name" placeholder="Contoh: Supervisor" value="{{ old('position_name', $edit->position_name ?? '') }}" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Keterangan</label>
          <input type="text" class="form-control" name="position_note" placeholder="Keterangan" value="{{ old('position_note', $edit->note ?? '') }}">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-success w-100">{{ $edit ? 'Update' : 'Save' }}</button>
        </div>
      </div>
    </form>
  </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <table class="table table-striped table-sm">
      <thead>
        <tr>
          <th>ID</th>
          <th>Jabatan</th>
          <th>Keterangan</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($positions ?? [] as $p)
          <tr>
            <td>{{ $p->id }}</td>
            <td>{{ $p->position_name }}</td>
            <td>{{ $p->note }}</td>
            <td class="text-end">
              <a class="icon-btn icon-edit" href="{{ route('employees.position', ['edit' => $p->id]) }}" title="Edit">
                <span class="icon i-edit" aria-hidden="true"></span>
              </a>
              <form method="post" action="{{ route('employees.position') }}" class="d-inline" onsubmit="return confirm('Hapus jabatan ini?');">
                @csrf
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="{{ $p->id }}">
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
@endsection
