@extends('layouts.app')

@section('content')
<h4 class="mb-3">Notifikasi</h4>

@foreach ($messages as $m)
  <div class="alert alert-info">{{ $m }}</div>
@endforeach

<div class="d-flex gap-2 mb-3">
  <form method="post">
    @csrf
    <input type="hidden" name="action" value="read_all">
    <button class="btn btn-outline-secondary btn-sm" type="submit">Tandai Semua Dibaca</button>
  </form>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-sm align-middle">
        <thead>
          <tr>
            <th>Judul</th>
            <th>Pesan</th>
            <th>Waktu</th>
            <th>Status</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($rows as $n)
            <tr>
              <td>{{ $n->title }}</td>
              <td>{{ $n->message }}</td>
              <td>{{ $n->created_at }}</td>
              <td>
                @if ($n->is_read)
                  <span class="badge text-bg-secondary">Dibaca</span>
                @else
                  <span class="badge text-bg-success">Baru</span>
                @endif
              </td>
              <td class="text-end">
                @if (!empty($n->link))
                  <a class="btn btn-outline-primary btn-sm" href="{{ $n->link }}">Lihat</a>
                @endif
                @if (!$n->is_read)
                  <form method="post" class="d-inline">
                    @csrf
                    <input type="hidden" name="action" value="read_one">
                    <input type="hidden" name="id" value="{{ $n->id }}">
                    <button class="btn btn-outline-secondary btn-sm" type="submit">Tandai Dibaca</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-muted">Belum ada notifikasi.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
