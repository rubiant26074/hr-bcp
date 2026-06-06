@extends('layouts.app')

@section('content')
<h4 class="mb-3">Status Aktif (Master)</h4>
@if (!($tableReady ?? false))
  <div class="alert alert-warning">
    Table <span class="fw-semibold">employee_active_statuses</span> belum ada. Jalankan migration supaya status bisa ditambah/diubah.
  </div>
@endif
<div class="d-flex gap-2 flex-wrap mb-3">
  <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#activeStatusForm" aria-expanded="false" aria-controls="activeStatusForm" {{ !($tableReady ?? false) ? 'disabled' : '' }}>
    {{ $edit ? 'Edit Status' : 'Add Status' }}
  </button>
  <a class="btn btn-outline-secondary" href="{{ route('employees.index') }}">Back</a>
  @if ($edit)
    <a class="btn btn-outline-danger" href="{{ route('employees.active_status') }}">Batal Edit</a>
  @endif
</div>

<div class="collapse mb-3 {{ $edit ? 'show' : '' }}" id="activeStatusForm">
  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post" action="{{ route('employees.active_status') }}">
        @csrf
        <input type="hidden" name="action" value="{{ $edit ? 'update' : 'save' }}">
        <input type="hidden" name="id" value="{{ $edit->id ?? '' }}">
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Status Aktif</label>
            <input type="text" class="form-control" name="status_name" placeholder="Contoh: Active" value="{{ old('status_name', $edit->status_name ?? '') }}" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Urutan</label>
            <input type="number" class="form-control" name="sort_order" min="0" step="1" value="{{ old('sort_order', $edit->sort_order ?? 0) }}">
          </div>
          <div class="col-md-3">
            <label class="form-label d-block">Masuk Arsip</label>
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" name="is_archive" id="is_archive" value="1" {{ old('is_archive', (int)($edit->is_archive ?? 0)) ? 'checked' : '' }}>
              <label class="form-check-label" for="is_archive">Resign/PHK/Habis Kontrak</label>
            </div>
          </div>
          <div class="col-md-8">
            <label class="form-label">Keterangan</label>
            <input type="text" class="form-control" name="status_note" placeholder="Keterangan" value="{{ old('status_note', $edit->note ?? '') }}">
          </div>
          <div class="col-md-4">
            <button type="submit" class="btn btn-success w-100">{{ $edit ? 'Update' : 'Save' }}</button>
          </div>
        </div>
      </form>
      <div class="text-muted mt-2">
        Dipakai untuk dropdown <span class="fw-semibold">Status Aktif</span> di Form Employee, dan untuk menentukan data masuk tab <span class="fw-semibold">Arsip</span>.
      </div>
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
              <th>Status</th>
              <th>Urutan</th>
              <th>Arsip</th>
              <th>Keterangan</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($activeStatusOptions ?? [] as $s)
              <tr>
                <td>{{ $s->id }}</td>
                <td>{{ $s->status_name }}</td>
                <td>{{ $s->sort_order }}</td>
                <td>{{ (int)$s->is_archive ? 'Ya' : '-' }}</td>
                <td>{{ $s->note }}</td>
                <td class="text-end">
                  @if (($tableReady ?? false) && !empty($s->id))
                    <a class="icon-btn icon-edit" href="{{ route('employees.active_status', ['edit' => $s->id]) }}" title="Edit">
                      <span class="icon i-edit" aria-hidden="true"></span>
                    </a>
                    <form method="post" action="{{ route('employees.active_status') }}" class="d-inline" onsubmit="return confirm('Hapus status ini?');">
                      @csrf
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="{{ $s->id }}">
                      <button type="submit" class="icon-btn icon-delete" title="Delete">
                        <span class="icon i-trash" aria-hidden="true"></span>
                      </button>
                    </form>
                  @else
                    <span class="text-muted">-</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-muted">Belum ada data.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
