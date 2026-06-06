@extends('layouts.app')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">User Management</h4>
  <a class="btn btn-primary" href="{{ route('users.form') }}">Add User</a>
</div>

@if (request()->query('created'))
  <div class="alert alert-success">User berhasil ditambahkan.</div>
@elseif (request()->query('updated'))
  <div class="alert alert-success">User berhasil diupdate.</div>
@elseif (request()->query('deleted'))
  <div class="alert alert-success">User berhasil dihapus.</div>
@elseif (request()->query('error') === 'self_delete')
  <div class="alert alert-danger">User aktif tidak bisa dihapus.</div>
@endif

<div class="card shadow-sm">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-sm">
        <thead>
          <tr>
            <th>Nama</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Tanda Tangan</th>
            <th>Company</th>
            <th>Employee</th>
            <th>Departement</th>
            <th class="text-end"></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($users as $u)
            <tr>
              <td>{{ $u->name }}</td>
              <td>{{ $u->email }}</td>
              <td>{{ $u->role }}</td>
              <td>
                @if ((int) ($u->is_active ?? 1) === 1)
                  <span class="badge text-bg-success">Aktif</span>
                @else
                  <span class="badge text-bg-warning">Pending</span>
                @endif
              </td>
              <td>
                @if (!empty($u->signature_path))
                  <img src="{{ asset($u->signature_path) }}" alt="Tanda Tangan" style="height:36px;">
                @else
                  -
                @endif
              </td>
              <td>{{ $u->company_name ?? '-' }}</td>
              <td>
                @if (!empty($u->employee_id))
                  {{ ($u->employee_name ?? '-') }} ({{ $u->employee_nik ?? '-' }})
                @else
                  -
                @endif
              </td>
              <td>{{ $u->employee_department ?? '-' }}</td>
              <td class="text-end">
                <a class="icon-btn icon-edit" title="Edit" href="{{ route('users.form', ['id' => $u->id]) }}">
                  <span class="icon i-edit" aria-hidden="true"></span>
                </a>
                @if ((int) $u->id !== (int) (current_user()['id'] ?? 0))
                  <form method="post" class="d-inline" onsubmit="return confirm('Ubah status aktivasi user ini?')">
                    @csrf
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="id" value="{{ $u->id }}">
                    <input type="hidden" name="active" value="{{ (int)($u->is_active ?? 1) === 1 ? 0 : 1 }}">
                    <button type="submit" class="btn btn-sm {{ (int)($u->is_active ?? 1) === 1 ? 'btn-outline-warning' : 'btn-outline-success' }}">
                      {{ (int)($u->is_active ?? 1) === 1 ? 'Nonaktifkan' : 'Aktifkan' }}
                    </button>
                  </form>
                @endif
                @if ((int) $u->id !== (int) (current_user()['id'] ?? 0))
                  <form method="post" class="d-inline" onsubmit="return confirm('Hapus user ini?')">
                    @csrf
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="{{ $u->id }}">
                    <button type="submit" class="icon-btn icon-delete" title="Delete">
                      <span class="icon i-trash" aria-hidden="true"></span>
                    </button>
                  </form>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
