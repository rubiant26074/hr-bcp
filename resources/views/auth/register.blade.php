<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pendaftaran Email - BCP-HRIS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="{{ asset('css/app.css') }}" rel="stylesheet">
  <style>
    body {
      min-height: 100vh;
      background:
        radial-gradient(1200px 600px at 10% 10%, #e8f6f5 0%, transparent 50%),
        radial-gradient(900px 500px at 90% 10%, #eaf1fb 0%, transparent 55%),
        #f6f7fb;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px 12px;
    }
    .card-register {
      width: 100%;
      max-width: 520px;
      border: 0;
      border-radius: 16px;
      box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
    }
    .brand {
      font-weight: 700;
      color: #0f2d3f;
      letter-spacing: 0.3px;
    }
    .btn-primary {
      background: #0ea5a4;
      border-color: #0ea5a4;
    }
    .btn-primary:hover {
      background: #0b8e8d;
      border-color: #0b8e8d;
    }
  </style>
</head>
<body>
  <div class="card card-register">
    <div class="card-body p-4 p-md-5">
      <div class="brand mb-1">BCP-HRIS</div>
      <h1 class="h4 mb-1">Pendaftaran Email</h1>
      <p class="text-muted small mb-4">Buat akun email dan password. Setelah itu verifikasi email agar akun bisa digunakan.</p>

      @if ($success)
        <div class="alert alert-success">{{ $success }}</div>
      @endif
      @if ($error)
        <div class="alert alert-danger">{{ $error }}</div>
      @endif
      @if ($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $err)
              <li>{{ $err }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form method="post" action="{{ route('register.submit') }}">
        @csrf
        <div class="mb-3">
          <label class="form-label">Nama</label>
          <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <div class="mb-4">
          <label class="form-label">Konfirmasi Password</label>
          <input type="password" name="password_confirmation" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Daftar & Kirim Verifikasi</button>
      </form>
    </div>
  </div>
</body>
</html>
