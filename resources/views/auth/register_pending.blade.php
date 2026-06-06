<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verifikasi Email - BCP-HRIS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
    .card-pending {
      width: 100%;
      max-width: 560px;
      border: 0;
      border-radius: 16px;
      box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
    }
  </style>
</head>
<body>
  <div class="card card-pending">
    <div class="card-body p-4 p-md-5">
      <h1 class="h4 mb-2">Cek Email untuk Verifikasi</h1>
      <p class="text-muted mb-4">
        Link verifikasi telah dikirim ke:
        <strong>{{ $email ?: '-' }}</strong><br>
        Setelah verifikasi, akun baru bisa login menggunakan email dan password.
      </p>

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

      <form method="post" action="{{ route('register.resend_verification') }}" class="mb-3">
        @csrf
        <input type="hidden" name="email" value="{{ $email }}">
        <button class="btn btn-outline-primary w-100" type="submit">Kirim Ulang Link Verifikasi</button>
      </form>

      <a href="{{ route('login') }}" class="btn btn-light border w-100">Kembali ke Login</a>
    </div>
  </div>
</body>
</html>
