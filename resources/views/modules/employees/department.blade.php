@extends('layouts.app')

@section('content')
<h4 class="mb-3">Master Departement</h4>
<div class="d-flex gap-2 flex-wrap mb-3">
  <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#departmentForm" aria-expanded="false" aria-controls="departmentForm">
    {{ $edit ? 'Edit Departement' : 'Add Departement' }}
  </button>
  <a class="btn btn-outline-secondary" href="{{ route('employees.department') }}">Back</a>
  @if ($edit)
    <a class="btn btn-outline-danger" href="{{ route('employees.department') }}">Batal Edit</a>
  @endif
</div>
<div class="collapse mb-3 {{ $edit ? 'show' : '' }}" id="departmentForm">
  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post" action="{{ route('employees.department') }}">
        @csrf
        <input type="hidden" name="action" value="{{ $edit ? 'update' : 'save' }}">
        <input type="hidden" name="id" value="{{ $edit->id ?? '' }}">
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Departement</label>
            <input type="text" class="form-control" name="department_name" placeholder="Contoh: Finance / HR" value="{{ old('department_name', $edit->department_name ?? '') }}" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Keterangan</label>
            <input type="text" class="form-control" name="department_note" placeholder="Keterangan" value="{{ old('department_note', $edit->note ?? '') }}">
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
              <th>Departement</th>
              <th>Keterangan</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($departments ?? [] as $d)
              <tr>
                <td>{{ $d->id }}</td>
                <td>{{ $d->department_name }}</td>
                <td>{{ $d->note }}</td>
                <td class="text-end">
                  <a class="icon-btn icon-edit" href="{{ route('employees.department', ['edit' => $d->id]) }}" title="Edit">
                    <span class="icon i-edit" aria-hidden="true"></span>
                  </a>
                  <form method="post" action="{{ route('employees.department') }}" class="d-inline" onsubmit="return confirm('Hapus departement ini?');">
                    @csrf
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="{{ $d->id }}">
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
