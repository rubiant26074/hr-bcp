@extends('layouts.app')

@section('content')
<h4 class="mb-3">Seting Theme</h4>
@foreach ($messages as $m)
  <div class="alert alert-success">{{ $m }}</div>
@endforeach

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post">
      @csrf
      <div class="mb-3">
        <label class="form-label">Tema</label>
        <select class="form-select" name="theme">
          <option value="light" {{ $theme === 'light' ? 'selected' : '' }}>Light</option>
          <option value="dark" {{ $theme === 'dark' ? 'selected' : '' }}>Dark (Beta)</option>
          <option value="mekari" {{ $theme === 'mekari' ? 'selected' : '' }}>Mekari</option>
          <option value="heart" {{ $theme === 'heart' ? 'selected' : '' }}>Merah Hati</option>
          <option value="bcp_form" {{ $theme === 'bcp_form' ? 'selected' : '' }}>BCP Form Style</option>
        </select>
        <div class="form-text">Tema dark masih tahap beta. Theme BCP Form meniru gaya sidebar terang + aksen hijau.</div>
      </div>
      <button class="btn btn-primary" type="submit">Simpan</button>
      <a class="btn btn-outline-secondary" href="{{ route('settings.index') }}">Kembali</a>
    </form>
  </div>
</div>
@endsection
