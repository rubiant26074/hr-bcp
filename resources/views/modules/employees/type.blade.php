@extends('layouts.app')

@section('content')
<h4 class="mb-3">Staf &amp; Non Staf</h4>

<div class="d-flex gap-2 flex-wrap mb-3">
  <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#typeForm" aria-expanded="false" aria-controls="typeForm">
    {{ $edit ? 'Edit Type' : 'Add Type' }}
  </button>
  <a class="btn btn-outline-secondary" href="{{ route('employees.index') }}">Back</a>
  @if ($edit)
    <a class="btn btn-outline-danger" href="{{ route('employees.type') }}">Batal Edit</a>
  @endif
</div>

<div class="collapse mb-3 {{ $edit ? 'show' : '' }}" id="typeForm">
  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post" action="{{ route('employees.type') }}">
        @csrf
        <input type="hidden" name="action" value="{{ $edit ? 'update' : 'save' }}">
        <input type="hidden" name="id" value="{{ $edit->id ?? '' }}">
        <div class="row g-2 align-items-end">
          <div class="col-md-8">
            <label class="form-label">Jenis</label>
            <input type="text" class="form-control" name="type_name" placeholder="Contoh: Staf / Non Staf" value="{{ old('type_name', $edit->type_name ?? '') }}" required>
          </div>
          <div class="col-md-4">
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
              <th>Jenis</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($types ?? [] as $t)
              <tr>
                <td>{{ $t->id }}</td>
                <td>{{ $t->type_name }}</td>
                <td class="text-end">
                  <a class="icon-btn icon-edit" href="{{ route('employees.type', ['edit' => $t->id]) }}" title="Edit">
                    <span class="icon i-edit" aria-hidden="true"></span>
                  </a>
                  <form method="post" action="{{ route('employees.type') }}" class="d-inline" onsubmit="return confirm('Hapus type ini?');">
                    @csrf
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="{{ $t->id }}">
                    <button type="submit" class="icon-btn icon-delete" title="Delete">
                      <span class="icon i-trash" aria-hidden="true"></span>
                    </button>
                  </form>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="3" class="text-muted">Belum ada data.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
