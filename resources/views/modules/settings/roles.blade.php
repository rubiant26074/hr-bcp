@extends('layouts.app')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">Role Management</h4>
  <a class="btn btn-outline-secondary" href="{{ route('settings.index') }}">Back to Settings</a>
</div>

@if (request()->query('created'))
  <div class="alert alert-success">Role berhasil ditambahkan.</div>
@elseif (request()->query('updated'))
  <div class="alert alert-success">Role berhasil diupdate.</div>
@elseif (request()->query('deleted'))
  <div class="alert alert-success">Role berhasil dihapus.</div>
@elseif (request()->query('error') === 'in_use')
  <div class="alert alert-danger">Role sedang dipakai oleh user, tidak bisa dihapus.</div>
@endif

<div class="row g-3">
  <div class="col-12 col-lg-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-3">{{ $edit ? 'Edit Role' : 'Tambah Role' }}</h6>
        <form method="post">
          @csrf
          <input type="hidden" name="id" value="{{ $edit->id ?? '' }}">
          <div class="mb-3">
            <label class="form-label">Nama Role</label>
            <input type="text" class="form-control" name="name" value="{{ old('name', $edit->name ?? '') }}" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Deskripsi</label>
            <input type="text" class="form-control" name="description" value="{{ old('description', $edit->description ?? '') }}">
          </div>
          <div class="d-flex gap-2 justify-content-end">
            <button class="btn btn-primary" type="submit">{{ $edit ? 'Update' : 'Simpan' }}</button>
            @if ($edit)
              <a class="btn btn-outline-secondary" href="{{ route('settings.roles') }}">Batal</a>
            @endif
          </div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-8">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped table-sm">
            <thead>
              <tr>
                <th>Role</th>
                <th>Deskripsi</th>
                <th class="text-end"></th>
              </tr>
            </thead>
            <tbody>
              @foreach ($roles as $role)
                <tr>
                  <td>{{ $role->name }}</td>
                  <td>{{ $role->description ?? '-' }}</td>
                  <td class="text-end">
                    <a class="icon-btn icon-edit" href="{{ route('settings.roles', ['edit' => $role->id]) }}" title="Edit">
                      <span class="icon i-edit" aria-hidden="true"></span>
                    </a>
                    <form method="post" class="d-inline" onsubmit="return confirm('Hapus role ini?')">
                      @csrf
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="{{ $role->id }}">
                      <button type="submit" class="icon-btn icon-delete" title="Delete">
                        <span class="icon i-trash" aria-hidden="true"></span>
                      </button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
