@extends('layouts.app')

@section('content')
<h4 class="mb-3">Struktur Organisasi</h4>

@if (current_user_has_global_scope($user))
<form class="mb-3" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label">Company</label>
      <select class="form-select" name="set_company" onchange="this.form.submit()">
        @foreach ($companies as $c)
          <option value="{{ $c->id }}" {{ $companyId == $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
        @endforeach
      </select>
    </div>
  </div>
</form>
@endif

<div class="d-flex gap-2 flex-wrap mb-3">
  <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#orgForm" aria-expanded="false" aria-controls="orgForm">
    {{ $edit ? 'Edit Unit' : 'Tambah Unit' }}
  </button>
  <a class="btn btn-outline-secondary" href="{{ route('org_structure.index') }}">Reset</a>
  @if ($edit)
    <a class="btn btn-outline-danger" href="{{ route('org_structure.index') }}">Batal Edit</a>
  @endif
</div>

<div class="collapse mb-3 {{ $edit ? 'show' : '' }}" id="orgForm">
  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post">
        @csrf
        <input type="hidden" name="id" value="{{ $edit->id ?? '' }}">
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Nama Unit (Departemen / Jabatan)</label>
            <select class="form-select" name="name" required>
              <option value="">Pilih Unit</option>
              @if (count($departments))
                <optgroup label="Departemen">
                  @foreach ($departments as $dept)
                    <option value="{{ $dept->department_name }}" {{ (string) old('name', $edit->name ?? '') === (string) $dept->department_name ? 'selected' : '' }}>
                      {{ $dept->department_name }}
                    </option>
                  @endforeach
                </optgroup>
              @endif
              @if (count($positions))
                <optgroup label="Jabatan">
                  @foreach ($positions as $pos)
                    <option value="{{ $pos->position_name }}" {{ (string) old('name', $edit->name ?? '') === (string) $pos->position_name ? 'selected' : '' }}>
                      {{ $pos->position_name }}
                    </option>
                  @endforeach
                </optgroup>
              @endif
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Parent</label>
            <select class="form-select" name="parent_id">
              <option value="">(Tidak ada)</option>
              @foreach ($rows as $r)
                @if (!$edit || (int) $r->id !== (int) $edit->id)
                  <option value="{{ $r->id }}" {{ (int) ($edit->parent_id ?? 0) === (int) $r->id ? 'selected' : '' }}>
                    {{ $r->name }}
                  </option>
                @endif
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Sort</label>
            <input type="number" class="form-control" name="sort_order" min="0" max="9999" value="{{ old('sort_order', $edit->sort_order ?? 0) }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Keterangan</label>
            <input type="text" class="form-control" name="note" value="{{ old('note', $edit->note ?? '') }}">
          </div>
          <div class="col-md-2">
            <button class="btn btn-success w-100" type="submit">{{ $edit ? 'Update' : 'Save' }}</button>
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
          <th>Nama Unit</th>
          <th>Parent</th>
          <th>Sort</th>
          <th>Keterangan</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($rows as $r)
          <tr>
            <td>{{ $r->name }}</td>
            <td>{{ $r->parent_id ? ($parentMap[$r->parent_id]->name ?? '-') : '-' }}</td>
            <td>{{ $r->sort_order }}</td>
            <td>{{ $r->note }}</td>
            <td class="text-end">
              <a class="icon-btn icon-edit" href="{{ route('org_structure.index', ['edit' => $r->id]) }}" title="Edit">
                <span class="icon i-edit" aria-hidden="true"></span>
              </a>
              <form method="post" class="d-inline" onsubmit="return confirm('Hapus unit ini?');">
                @csrf
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="{{ $r->id }}">
                <button type="submit" class="icon-btn icon-delete" title="Delete">
                  <span class="icon i-trash" aria-hidden="true"></span>
                </button>
              </form>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-muted">Belum ada data.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@endsection
