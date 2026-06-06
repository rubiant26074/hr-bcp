@extends('layouts.app')

@section('content')
<h4 class="mb-3">Setting Lokasi Absen</h4>

@if (!empty($messages))
  @foreach ($messages as $m)
    <div class="alert alert-success">{{ $m }}</div>
  @endforeach
@endif

<div class="d-flex gap-2 flex-wrap mb-3">
  <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#locationForm" aria-expanded="false" aria-controls="locationForm">
    {{ $location ? 'Edit Lokasi' : 'Tambah Lokasi' }}
  </button>
  @if ($location)
    <a class="btn btn-outline-danger" href="{{ route('attendance.location') }}">Batal Edit</a>
  @endif
</div>

<div class="collapse mb-3 {{ $location ? 'show' : '' }}" id="locationForm">
  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post" class="row g-2 align-items-end">
        @csrf
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="{{ $location->id ?? '' }}">
        <div class="col-md-4">
          <label class="form-label">Nama Lokasi</label>
          <input type="text" class="form-control" name="location_name" placeholder="Kantor Pusat" value="{{ old('location_name', $location->location_name ?? '') }}">
        </div>
        <div class="col-md-3">
          <label class="form-label">Latitude</label>
          <input type="text" class="form-control" name="latitude" value="{{ old('latitude', $location->latitude ?? '') }}" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Longitude</label>
          <input type="text" class="form-control" name="longitude" value="{{ old('longitude', $location->longitude ?? '') }}" required>
        </div>
        <div class="col-md-2">
          <label class="form-label">Radius (m)</label>
          <input type="number" class="form-control" name="radius_m" value="{{ old('radius_m', $location->radius_m ?? 30) }}" min="1" max="1000" required>
        </div>
        <div class="col-md-12">
          <button type="submit" class="btn btn-success">{{ $location ? 'Update' : 'Simpan' }}</button>
        </div>
      </form>
      <div class="small text-muted mt-2">
        Tip: Ambil koordinat dari Google Maps (klik kanan → "What's here?").
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <table class="table table-sm table-striped align-middle">
      <thead>
        <tr>
          <th>Nama</th>
          <th>Latitude</th>
          <th>Longitude</th>
          <th>Radius (m)</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($locations as $row)
          <tr>
            <td>{{ $row->location_name ?? '-' }}</td>
            <td>{{ $row->latitude }}</td>
            <td>{{ $row->longitude }}</td>
            <td>{{ $row->radius_m }}</td>
            <td class="text-end">
              <a class="icon-btn icon-edit" href="{{ route('attendance.location', ['edit' => $row->id]) }}" title="Edit">
                <span class="icon i-edit" aria-hidden="true"></span>
              </a>
              <form method="post" class="d-inline" onsubmit="return confirm('Hapus lokasi ini?');">
                @csrf
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="{{ $row->id }}">
                <button type="submit" class="icon-btn icon-delete" title="Delete">
                  <span class="icon i-trash" aria-hidden="true"></span>
                </button>
              </form>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-muted">Belum ada lokasi absen.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
