<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - BCP-HRIS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="{{ asset('css/app.css') }}" rel="stylesheet">
  <style>
    :root {
      --brand-navy: #0f2d3f;
      --brand-teal: #0ea5a4;
      --brand-slate: #1f2937;
      --brand-ice: #f1f5f9;
    }
    body {
      background:
        radial-gradient(1200px 600px at 10% 10%, #e8f6f5 0%, transparent 50%),
        radial-gradient(900px 500px at 90% 10%, #eaf1fb 0%, transparent 55%),
        #f6f7fb;
    }
    .login-shell {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 32px 0;
    }
    .login-card {
      border: 0;
      border-radius: 16px;
      box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
      overflow: hidden;
    }
    .brand-pane {
      background: linear-gradient(135deg, var(--brand-navy), #0b3b4f);
      color: #fff;
      padding: 28px 28px 22px;
      height: 100%;
    }
    .brand-title {
      font-size: 22px;
      font-weight: 700;
      letter-spacing: 0.4px;
    }
    .brand-sub {
      font-size: 13px;
      color: rgba(255,255,255,0.8);
      margin-top: 6px;
    }
    .company-grid {
      margin-top: 18px;
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
    }
    .company-card {
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 12px;
      padding: 10px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .company-logo {
      width: 38px;
      height: 38px;
      border-radius: 10px;
      background: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      flex-shrink: 0;
    }
    .company-logo img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }
    .company-initials {
      font-weight: 700;
      color: var(--brand-navy);
      font-size: 14px;
    }
    .company-name {
      font-size: 12px;
      line-height: 1.3;
      color: rgba(255,255,255,0.9);
    }
    .brand-footer {
      margin-top: 22px;
      font-size: 12px;
      color: rgba(255,255,255,0.9);
      text-align: center;
    }
    .form-pane {
      padding: 28px;
      background: #fff;
    }
    .form-title {
      font-size: 20px;
      font-weight: 700;
      color: var(--brand-slate);
    }
    .form-desc {
      color: #64748b;
      font-size: 13px;
      margin-top: 4px;
    }
    .btn-login {
      background: var(--brand-teal);
      border: 0;
      font-weight: 600;
      padding: 10px 0;
    }
    .btn-login:hover {
      background: #0b8e8d;
    }
  </style>
</head>
<body>
<div class="login-shell">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-9 col-xl-8">
        <div class="card login-card">
          <div class="row g-0">
            <div class="col-md-5">
              <div class="brand-pane h-100">
                <div class="brand-title">BCP-HRIS</div>
                <div class="brand-sub">Multi-company HRIS Control Center</div>
                <div class="company-grid">
                  @foreach ($companies as $c)
                    @php
                      $initials = strtoupper(substr(preg_replace('/[^A-Z]/', '', (string) $c->company_name), 0, 2));
                      if ($initials === '') {
                        $initials = strtoupper(substr((string) $c->company_name, 0, 2));
                      }
                    @endphp
                    <div class="company-card">
                      <div class="company-logo">
                        @if (!empty($c->logo_path))
                          <img src="{{ asset_url($c->logo_path) }}" alt="logo">
                        @else
                          <span class="company-initials">{{ $initials }}</span>
                        @endif
                      </div>
                      <div class="company-name">{{ $c->company_name }}</div>
                    </div>
                  @endforeach
                </div>
                <div class="brand-footer">
                  <div>Copyright &copy; 2026 BCP-Group</div>
                  <div>BCP-HRIS &mdash; V1.0</div>
                </div>
              </div>
            </div>
            <div class="col-md-7">
              <div class="form-pane">
                <div class="form-title">Login</div>
                <div class="form-desc">Masuk untuk mengelola HR multi-company.</div>
                @if ($success ?? false)
                  <div class="alert alert-success mt-3">{{ $success }}</div>
                @endif
                @if ($error)
                  <div class="alert alert-danger mt-3">{{ $error }}</div>
                @endif
                <form method="post" action="{{ route('login') }}" class="mt-3">
                  @csrf
                  <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" required>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="password" required>
                  </div>
                  <button class="btn btn-login w-100" type="submit">Login</button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
