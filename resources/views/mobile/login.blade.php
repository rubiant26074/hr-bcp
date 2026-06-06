@extends('mobile.layout')

@section('content')
<div class="pt-5">
  <div class="text-center mb-4">
    <h4 class="fw-bold mb-1">HR-BCP Mobile</h4>
    <div class="text-muted small">Login untuk absensi dan slip gaji</div>
  </div>

  <div class="card card-clean">
    <div class="card-body p-4">
      @if (!empty($error))
        <div class="alert alert-danger py-2">{{ $error }}</div>
      @endif
      @if (!empty($success))
        @php
          $isPendingActivation = stripos((string) $success, 'menunggu aktivasi') !== false
            || stripos((string) $success, 'administrator') !== false;
        @endphp
        <div class="alert {{ $isPendingActivation ? 'alert-warning' : 'alert-success' }} py-2">
          {{ $success }}
        </div>
      @endif
      <form method="post" action="{{ route('mobile.login.submit') }}">
        @csrf
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control form-control-lg" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control form-control-lg" required>
        </div>
        <button class="btn btn-dark w-100 btn-lg" type="submit">Masuk</button>
      </form>
      <div class="text-center mt-3">
        <a href="{{ route('mobile.register') }}" class="small text-decoration-none">Belum punya akun? Register</a>
      </div>
      <div class="text-center mt-2">
        <a href="{{ route('android.apk') }}" class="small text-decoration-none">Download APK Mobile</a>
      </div>
      @php
        $appVersion = (string) (config('app.version') ?? env('APP_VERSION', 'V1.0'));
        if (trim($appVersion) === '') {
            $appVersion = 'V1.0';
        }
      @endphp
      <div class="text-center mt-3 small text-muted" style="line-height:1.5;">
        <div>Copyright &copy; 2026 BCP-Group</div>
        <div>HR-BCP Version : {{ $appVersion }}</div>
        <div>By NyXRubi@nt</div>
      </div>
    </div>
  </div>
</div>
@endsection
