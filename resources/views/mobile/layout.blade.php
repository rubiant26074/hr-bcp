<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0f172a">
  @php
    $theme = session('theme', 'bcp_form');
    if ($theme === 'mekari') {
        $bodyClass = 'theme-mekari';
    } elseif ($theme === 'heart') {
        $bodyClass = 'theme-heart';
    } elseif ($theme === 'bcp_form') {
        $bodyClass = 'theme-bcp-form';
    } else {
        $bodyClass = 'theme-light';
    }
  @endphp
  @php
    $appVersion = (string) (config('app.version') ?? env('APP_VERSION', 'V1.0'));
    if (trim($appVersion) === '') {
        $appVersion = 'V1.0';
    }
  @endphp
  <title>{{ $title ?? 'HR-BCP Mobile' }}</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="{{ asset('css/app.css') }}" rel="stylesheet">
  <style>
    body { background: var(--dash-bg); color: var(--dash-title); padding-bottom: 80px; }
    .mobile-wrap { max-width: 520px; margin: 0 auto; }
    .card-clean {
      border: 1px solid var(--dash-card-border);
      background: var(--dash-card-bg);
      border-radius: 14px;
      box-shadow: 0 10px 20px rgba(11,31,58,0.08);
    }
    .mobile-head {
      background: linear-gradient(90deg, #0b1f3a 0%, #143555 60%, #1f4d7a 100%);
      color: #fff;
      border-radius: 14px;
      padding: 12px 14px;
      margin-bottom: 12px;
    }
    .mobile-brand-text {
      flex: 1 1 auto;
      text-align: center;
      min-width: 0;
    }
    .mobile-brand-title {
      font-size: 29px;
      line-height: 1;
      font-weight: 700;
      letter-spacing: 0.2px;
    }
    .mobile-brand-subtitle {
      font-size: 14px;
      color: rgba(255,255,255,0.78);
      line-height: 1.2;
      margin-top: 3px;
      white-space: nowrap;
    }
    @media (max-width: 390px) {
      .mobile-brand-text { min-width: 138px; }
      .mobile-brand-title { font-size: 20px; }
      .mobile-brand-subtitle { font-size: 12px; }
      .company-logo { width: 40px; height: 28px; }
    }
    .bottom-nav { position: fixed; left: 0; right: 0; bottom: 0; background: #fff; border-top: 1px solid #e5e7eb; }
    .bottom-nav .inner { max-width: 520px; margin: 0 auto; }
    .bottom-nav a { text-decoration: none; color: var(--dash-muted); font-size: 12px; font-weight: 600; }
    .bottom-nav a.active { color: var(--dash-title); }
  </style>
</head>
<body class="{{ $bodyClass }}">
  <div class="mobile-wrap p-3">
    @if (!empty($showNav))
      <div class="mobile-head d-flex align-items-center gap-2">
        <div class="mobile-brand-text">
          <div class="mobile-brand-title">BCP-HRIS Mobile</div>
          <div class="mobile-brand-subtitle">Smart HR, One Connection.</div>
        </div>
      </div>
    @endif
    @yield('content')
  </div>

  @if (!empty($showNav))
    <nav class="bottom-nav py-2">
      <div class="inner d-flex justify-content-around">
        <a class="{{ ($activeTab ?? '') === 'home' ? 'active' : '' }}" href="{{ route('mobile.home') }}">Beranda</a>
        <a class="{{ ($activeTab ?? '') === 'attendance' ? 'active' : '' }}" href="{{ route('mobile.attendance') }}">Absensi</a>
        <a class="{{ ($activeTab ?? '') === 'recap' ? 'active' : '' }}" href="{{ route('mobile.recap') }}">Rekap</a>
        <a class="{{ ($activeTab ?? '') === 'payslip' ? 'active' : '' }}" href="{{ route('mobile.payslip') }}">Slip</a>
      </div>
    </nav>
  @endif
</body>
</html>
