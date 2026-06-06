@extends('layouts.app')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <h4 class="mb-0">Dinas Luar</h4>
  <a class="btn btn-primary" href="{{ route('dinas_luar.form') }}">Tambah Pengajuan</a>
</div>

@foreach ($messages as $m)
  <div class="alert alert-success">{{ $m }}</div>
@endforeach
@if ($errors->has('approval'))
  <div class="alert alert-danger">{{ $errors->first('approval') }}</div>
@endif

<form class="row g-2 align-items-end mb-3">
  <div class="col-md-3">
    <label class="form-label">Status</label>
    <select class="form-select" name="status">
      <option value="">Semua</option>
      <option value="Draft" {{ $statusFilter === 'Draft' ? 'selected' : '' }}>Draft</option>
      <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Pending Approval</option>
      <option value="Approved" {{ $statusFilter === 'Approved' ? 'selected' : '' }}>Approved</option>
      <option value="Rejected" {{ $statusFilter === 'Rejected' ? 'selected' : '' }}>Rejected</option>
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label">Jenis</label>
    <select class="form-select" name="type">
      <option value="">Semua</option>
      <option value="DLK" {{ $typeFilter === 'DLK' ? 'selected' : '' }}>DLK (Luar Kota)</option>
      <option value="DLN" {{ $typeFilter === 'DLN' ? 'selected' : '' }}>DLN (Luar Negeri)</option>
    </select>
  </div>
  <div class="col-md-2">
    <button class="btn btn-outline-primary w-100" type="submit">Filter</button>
  </div>
</form>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead>
          <tr>
            <th>No Dokumen</th>
            <th>Jenis</th>
            <th>Tanggal</th>
            <th>Project</th>
            <th>Status</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($rows as $r)
            @php
              $pendingId = $pendingApproverId[$r->id] ?? null;
              $canApprove = $pendingId && (int) $pendingId === (int) ($user['id'] ?? 0);
            @endphp
            <tr>
              <td>{{ $r->doc_no ?? '-' }}</td>
              <td>{{ $r->request_type }}</td>
              <td>{{ $r->request_date ? format_date_id($r->request_date) : '-' }}</td>
              <td>{{ $r->project ?? '-' }}</td>
              <td>
                @if (($r->status ?? '') === 'Approved')
                  <span class="badge bg-success">Approved</span>
                @elseif (($r->status ?? '') === 'Rejected')
                  <span class="badge bg-danger">Rejected</span>
                @elseif (str_starts_with((string) $r->status, 'Pending'))
                  <span class="badge bg-warning text-dark">{{ $r->status }}</span>
                @else
                  <span class="badge bg-secondary">{{ $r->status }}</span>
                @endif
              </td>
              <td class="text-end">
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('dinas_luar.detail', ['id' => $r->id]) }}">Detail</a>
                <a class="btn btn-outline-primary btn-sm" href="{{ route('dinas_luar.form', ['id' => $r->id]) }}">Edit</a>
                <a class="btn btn-outline-success btn-sm" href="{{ route('dinas_luar.pdf', ['id' => $r->id]) }}">PDF</a>
                <form method="post" class="d-inline" onsubmit="return confirm('Hapus pengajuan ini?');">
                  @csrf
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="{{ $r->id }}">
                  <button class="btn btn-outline-danger btn-sm" type="submit">Hapus</button>
                </form>
                @if (($r->status ?? '') === 'Draft' || ($r->status ?? '') === 'Rejected')
                  <form method="post" class="d-inline">
                    @csrf
                    <input type="hidden" name="action" value="submit">
                    <input type="hidden" name="id" value="{{ $r->id }}">
                    <button class="btn btn-primary btn-sm" type="submit">Ajukan Approval</button>
                  </form>
                @elseif ($canApprove)
                  <form method="post" class="d-inline">
                    @csrf
                    <input type="hidden" name="action" value="approve_step">
                    <input type="hidden" name="id" value="{{ $r->id }}">
                    <button class="btn btn-success btn-sm" type="submit">Approve</button>
                  </form>
                  <form method="post" class="d-inline" onsubmit="return confirm('Tolak pengajuan ini?');">
                    @csrf
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="id" value="{{ $r->id }}">
                    <button class="btn btn-outline-danger btn-sm" type="submit">Reject</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-muted">Belum ada pengajuan.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
