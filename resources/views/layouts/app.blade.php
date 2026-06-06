@php
  $user = current_user();
  $theme = session('theme', 'bcp_form');
  if ($theme === 'mekari') {
      $bodyClass = 'theme-mekari';
  } elseif ($theme === 'heart') {
      $bodyClass = 'theme-heart';
  } elseif ($theme === 'bcp_form') {
      $bodyClass = 'theme-bcp-form';
  } else {
      $bodyClass = 'bg-light theme-light';
  }
  $can = function (string $path) use ($user): bool {
      if (!$user) {
          return false;
      }
      return rbac_route_allowed((string)($user['role'] ?? ''), $path);
  };
  $notifCount = 0;
  if ($user) {
      try {
          if (\Illuminate\Support\Facades\Schema::hasTable('notifications')) {
              $notifCount = (int) \Illuminate\Support\Facades\DB::table('notifications')
                  ->where('company_id', current_company_id())
                  ->where('user_id', (int) ($user['id'] ?? 0))
                  ->where('is_read', 0)
                  ->count();
          }
      } catch (\Throwable $e) {
          $notifCount = 0;
      }
  }
@endphp
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>BCP-HRIS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="{{ asset('css/app.css') }}" rel="stylesheet">
  <style>
    .apk-suggest {
      position: fixed;
      left: 12px;
      right: 12px;
      bottom: 12px;
      z-index: 1090;
      background: #0f172a;
      color: #fff;
      border-radius: 12px;
      padding: 10px 12px;
      box-shadow: 0 8px 24px rgba(0,0,0,.24);
      display: none;
    }
    .apk-suggest .apk-actions { display:flex; gap:8px; margin-top:8px; flex-wrap:wrap; }
  </style>
</head>
<body class="{{ $bodyClass }}">
<div id="apkSuggest" class="apk-suggest" role="dialog" aria-live="polite">
  <div><strong>Gunakan aplikasi Android HR-BCP</strong></div>
  <div class="small text-white-50">Buka app jika sudah terpasang, atau download APK terbaru.</div>
  <div class="apk-actions">
    <button type="button" class="btn btn-success btn-sm" id="btnOpenApk">Buka Aplikasi</button>
    <a class="btn btn-outline-light btn-sm" href="{{ asset('apk/HR-BCP.apk') }}">Download APK</a>
    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCloseApkSuggest">Nanti</button>
  </div>
</div>
@if ($user)
<div class="app-layout">
  <aside class="sidebar" id="appSidebar">
    <div class="sidebar-header">
      <div class="brand-badge">HR</div>
      <a class="brand" href="{{ route('dashboard') }}">BCP-HRIS</a>
      <div class="brand-sub">Smart HR, One Connection.</div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-divider"></div>
      <div class="nav-group" data-group="core">
        <button class="nav-group-toggle" type="button">Core</button>
        <div class="nav-group-body">
      @if ($can('modules/dashboard/index.php'))
      <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="nav-icon c1" aria-hidden="true"></span>Dashboard</a>
      @endif
      @if ($can('modules/notifications/index.php'))
      <a class="nav-link {{ request()->routeIs('notifications.index') ? 'active' : '' }}" href="{{ route('notifications.index') }}"><span class="nav-icon c6" aria-hidden="true"></span>Notifikasi</a>
      @endif
      @if ($can('modules/tv/index.php'))
      <a class="nav-link {{ request()->routeIs('tv.index') ? 'active' : '' }}" href="{{ route('tv.index') }}"><span class="nav-icon c2" aria-hidden="true"></span>TV Dashboard</a>
      @endif
      @if ($can('modules/account/index.php'))
      <a class="nav-link {{ request()->routeIs('account') ? 'active' : '' }}" href="{{ route('account') }}"><span class="nav-icon c7" aria-hidden="true"></span>Profil Saya / Akun</a>
      @endif
      @if ($can('modules/help/index.php'))
      <a class="nav-link {{ request()->routeIs('help.index') ? 'active' : '' }}" href="{{ route('help.index') }}"><span class="nav-icon c8" aria-hidden="true"></span>Bantuan / Panduan</a>
      @endif
        </div>
      </div>

      <div class="nav-divider"></div>
      <div class="nav-group" data-group="master">
        <button class="nav-group-toggle" type="button">Master Data</button>
        <div class="nav-group-body">
      @if ($can('modules/company/index.php'))
      <a class="nav-link {{ request()->is('company*') ? 'active' : '' }}" href="{{ url('/company') }}"><span class="nav-icon c2" aria-hidden="true"></span>Company</a>
      @endif
      {{-- Sementara disembunyikan dari sidebar, modul tetap aktif untuk penggunaan internal. --}}
      @if ($can('modules/employees/index.php'))
      <a class="nav-link {{ request()->is('employees*') ? 'active' : '' }}" href="{{ url('/employees') }}"><span class="nav-icon c3" aria-hidden="true"></span>Employees</a>
      @endif
      @if ($can('modules/pension/index.php'))
      <a class="nav-link {{ request()->is('pension*') ? 'active' : '' }}" href="{{ url('/pension') }}"><span class="nav-icon c3" aria-hidden="true"></span>Pensiun Calc</a>
      @endif
      @if ($can('modules/phk/index.php'))
      <a class="nav-link {{ request()->is('phk*') ? 'active' : '' }}" href="{{ url('/phk') }}"><span class="nav-icon c3" aria-hidden="true"></span>PHK Calc</a>
      @endif
      @if ($can('modules/contracts/index.php'))
      <a class="nav-link {{ request()->is('contracts*') ? 'active' : '' }}" href="{{ url('/contracts') }}"><span class="nav-icon c4" aria-hidden="true"></span>Contracts</a>
      @endif
      @if ($can('modules/leave/index.php'))
      <a class="nav-link {{ request()->is('leave*') ? 'active' : '' }}" href="{{ url('/leave') }}"><span class="nav-icon c5" aria-hidden="true"></span>Management Cuti</a>
      @endif
      @if ($can('modules/holidays/index.php'))
      <a class="nav-link {{ request()->is('holidays*') ? 'active' : '' }}" href="{{ url('/holidays') }}"><span class="nav-icon c2" aria-hidden="true"></span>Libur Nasional</a>
      @endif
        </div>
      </div>

      <div class="nav-divider"></div>
      <div class="nav-group" data-group="operations">
        <button class="nav-group-toggle" type="button">Operations</button>
        <div class="nav-group-body">
      @if ($can('modules/attendance/import.php'))
      <a class="nav-link {{ request()->is('attendance/import') ? 'active' : '' }}" href="{{ url('/attendance/import') }}"><span class="nav-icon c5" aria-hidden="true"></span>Import Absensi</a>
      @endif
      @if ($can('modules/attendance/mobile.php'))
      <a class="nav-link {{ request()->is('attendance/mobile') ? 'active' : '' }}" href="{{ url('/attendance/mobile') }}"><span class="nav-icon c9" aria-hidden="true"></span>Absensi Mobile</a>
      @endif
      @if ($can('modules/attendance/logs.php'))
      <a class="nav-link {{ request()->is('attendance/logs') ? 'active' : '' }}" href="{{ url('/attendance/logs') }}"><span class="nav-icon c6" aria-hidden="true"></span>Log Absensi</a>
      @endif
      @if ($can('modules/attendance/daily.php'))
      <a class="nav-link {{ request()->is('attendance/daily') ? 'active' : '' }}" href="{{ url('/attendance/daily') }}"><span class="nav-icon c7" aria-hidden="true"></span>Rekap Harian</a>
      @endif
      @if ($can('modules/attendance/monthly.php'))
      <a class="nav-link {{ request()->is('attendance/monthly') ? 'active' : '' }}" href="{{ url('/attendance/monthly') }}"><span class="nav-icon c8" aria-hidden="true"></span>Rekap Bulanan</a>
      @endif
      @if ($can('modules/attendance/monthly-employee.php'))
      <a class="nav-link {{ request()->is('attendance/monthly-employee') ? 'active' : '' }}" href="{{ url('/attendance/monthly-employee') }}"><span class="nav-icon c8" aria-hidden="true"></span>Rekap Bulanan Per Employee</a>
      @endif
      @if ($can('modules/payroll/period.php'))
      <a class="nav-link {{ request()->is('payroll/period') ? 'active' : '' }}" href="{{ url('/payroll/period') }}"><span class="nav-icon c9" aria-hidden="true"></span>Payroll Period</a>
      @endif
      @if ($can('modules/payroll/run.php'))
      <a class="nav-link {{ request()->is('payroll/run') ? 'active' : '' }}" href="{{ url('/payroll/run') }}"><span class="nav-icon c10" aria-hidden="true"></span>Run Payroll</a>
      @endif
      @if ($can('modules/payroll/review.php'))
      <a class="nav-link {{ request()->is('payroll/review') ? 'active' : '' }}" href="{{ url('/payroll/review') }}"><span class="nav-icon c11" aria-hidden="true"></span>Review Payroll</a>
      @endif
      @if ($can('modules/payroll/slip.php'))
      <a class="nav-link {{ request()->is('payroll/slip') ? 'active' : '' }}" href="{{ url('/payroll/slip') }}"><span class="nav-icon c12" aria-hidden="true"></span>Slip Gaji</a>
      @endif
        </div>
      </div>

      <div class="nav-divider"></div>
      <div class="nav-group" data-group="reports">
        <button class="nav-group-toggle" type="button">Reports</button>
        <div class="nav-group-body">
      @if ($can('modules/payroll/report.php'))
      <a class="nav-link {{ request()->is('payroll/report') ? 'active' : '' }}" href="{{ url('/payroll/report') }}"><span class="nav-icon c13" aria-hidden="true"></span>Payroll Report</a>
      @endif
      @if ($can('modules/payroll/pph21.php'))
      <a class="nav-link {{ request()->is('payroll/pph21') ? 'active' : '' }}" href="{{ url('/payroll/pph21') }}"><span class="nav-icon c12" aria-hidden="true"></span>PPh21</a>
      @endif
      @if ($can('modules/attendance/report.php'))
      <a class="nav-link {{ request()->is('attendance/report') ? 'active' : '' }}" href="{{ url('/attendance/report') }}"><span class="nav-icon c14" aria-hidden="true"></span>Attendance Report</a>
      @endif
        </div>
      </div>

      <div class="nav-divider"></div>
      <div class="nav-group" data-group="permissions">
        <button class="nav-group-toggle" type="button">HR – Request</button>
        <div class="nav-group-body">
      @if ($can('modules/permissions/absence.php'))
      <a class="nav-link {{ request()->is('permissions/absence') ? 'active' : '' }}" href="{{ url('/permissions/absence') }}"><span class="nav-icon c14" aria-hidden="true"></span>Absence (Leave/Sick)</a>
      @endif
      @if ($can('modules/permissions/out_office.php'))
      <a class="nav-link {{ request()->is('permissions/out-office') ? 'active' : '' }}" href="{{ url('/permissions/out-office') }}"><span class="nav-icon c13" aria-hidden="true"></span>Out of Office</a>
      @endif
      @if ($can('modules/permissions/overtime.php'))
      <a class="nav-link {{ request()->is('permissions/overtime') ? 'active' : '' }}" href="{{ url('/permissions/overtime') }}"><span class="nav-icon c3" aria-hidden="true"></span>Lembur</a>
      @endif
      @if ($can('modules/dinas_luar/index.php'))
      <a class="nav-link {{ request()->is('dinas-luar*') ? 'active' : '' }}" href="{{ url('/dinas-luar') }}"><span class="nav-icon c4" aria-hidden="true"></span>Dinas Luar</a>
      @endif
        </div>
      </div>

      <div class="nav-divider"></div>
      <div class="nav-group" data-group="settings">
        <button class="nav-group-toggle" type="button">Settings</button>
        <div class="nav-group-body">
      @if (($user['role'] ?? '') === 'Super Admin' && $can('modules/settings/theme.php'))
      <a class="nav-link {{ request()->is('settings/theme') ? 'active' : '' }}" href="{{ url('/settings/theme') }}"><span class="nav-icon c3" aria-hidden="true"></span>Setting Theme</a>
      @endif
      @if (($user['role'] ?? '') === 'Super Admin' && $can('modules/settings/backup.php'))
      <a class="nav-link {{ request()->is('settings/backup') ? 'active' : '' }}" href="{{ url('/settings/backup') }}"><span class="nav-icon c4" aria-hidden="true"></span>Backup Database</a>
      @endif
      @if (($user['role'] ?? '') === 'Super Admin' && $can('modules/settings/attendance_location.php'))
      <a class="nav-link {{ request()->is('settings/attendance-location') ? 'active' : '' }}" href="{{ url('/settings/attendance-location') }}"><span class="nav-icon c5" aria-hidden="true"></span>Setting Lokasi Absen</a>
      @endif
      @if (($user['role'] ?? '') === 'Super Admin' && $can('modules/settings/approval.php'))
      <a class="nav-link {{ request()->is('settings/approval') ? 'active' : '' }}" href="{{ url('/settings/approval') }}"><span class="nav-icon c6" aria-hidden="true"></span>Approval Settings</a>
      @endif
      @if (($user['role'] ?? '') === 'Super Admin' && $can('modules/users/index.php'))
      <a class="nav-link {{ request()->is('users*') ? 'active' : '' }}" href="{{ url('/users') }}"><span class="nav-icon c5" aria-hidden="true"></span>User Management</a>
      @endif
      @if (($user['role'] ?? '') === 'Super Admin' && $can('modules/settings/roles.php'))
      <a class="nav-link {{ request()->is('settings/roles') ? 'active' : '' }}" href="{{ url('/settings/roles') }}"><span class="nav-icon c6" aria-hidden="true"></span>Role Management</a>
      @endif
      @if (($user['role'] ?? '') === 'Super Admin' && $can('modules/rbac/index.php'))
      <a class="nav-link {{ request()->is('rbac*') ? 'active' : '' }}" href="{{ url('/rbac') }}"><span class="nav-icon c6" aria-hidden="true"></span>Kontrol Hak Akses (RBAC)</a>
      @endif
      @if ($can('modules/attendance/security-roster.php'))
      <a class="nav-link {{ request()->is('attendance/security-roster') ? 'active' : '' }}" href="{{ url('/attendance/security-roster') }}"><span class="nav-icon c8" aria-hidden="true"></span>Jadwal Shift Security</a>
      @endif
      @if (($user['role'] ?? '') === 'Super Admin' && $can('modules/settings/reset.php'))
      <a class="nav-link {{ request()->is('settings/reset') ? 'active' : '' }}" href="{{ url('/settings/reset') }}"><span class="nav-icon c7" aria-hidden="true"></span>Reset Database</a>
      @endif
        </div>
      </div>
    </nav>
    <div class="sidebar-footer">
      <div class="small text-white-50">{{ $user['email'] ?? '' }}</div>
      <a class="btn btn-outline-light btn-sm w-100 mt-2" href="{{ route('logout') }}">Logout</a>
    </div>
  </aside>

  <main class="main-content">
    <div class="topbar">
      <button class="btn btn-outline-secondary btn-sm d-lg-none" id="sidebarToggle" type="button">Menu</button>
      <div class="live-clock" id="liveClock" aria-live="polite">00:00:00</div>
      @if ($can('modules/notifications/index.php'))
      <a class="notif-bell" href="{{ route('notifications.index') }}" title="Notifikasi">
        <span class="bell-icon" aria-hidden="true"></span>
        @if ($notifCount > 0)
          <span class="bell-badge">{{ $notifCount }}</span>
        @endif
      </a>
      @endif
      <div class="user-info">{{ $user['name'] ?? '' }} ({{ $user['role'] ?? '' }})</div>
    </div>
    <div class="container pt-0 pb-4">
@else
<div class="container py-4">
@endif

@yield('content')

@if ($user)
    <footer class="text-center py-3 small text-muted">
      <div>Copyright © {{ date('Y') }} BCP-HRIS</div>
      <div>Software Version : V1.0</div>
    </footer>
    </div>
  </main>
</div>
</div>
@else
  <footer class="text-center py-3 small text-muted">
    <div>Copyright © {{ date('Y') }} BCP-HRIS</div>
    <div>Software Version : V1.0</div>
  </footer>
</div>
@endif
@yield('modals')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  (function () {
    var toggle = document.getElementById('sidebarToggle');
    if (!toggle) return;
    toggle.addEventListener('click', function () {
      document.body.classList.toggle('sidebar-open');
    });
    document.addEventListener('click', function (e) {
      if (!document.body.classList.contains('sidebar-open')) return;
      var sidebar = document.getElementById('appSidebar');
      if (!sidebar) return;
      if (sidebar.contains(e.target) || e.target === toggle) return;
      document.body.classList.remove('sidebar-open');
    });
  })();

  (function () {
    var box = document.getElementById('apkSuggest');
    var openBtn = document.getElementById('btnOpenApk');
    var closeBtn = document.getElementById('btnCloseApkSuggest');
    if (!box || !openBtn || !closeBtn) return;

    var ua = navigator.userAgent || '';
    var isAndroid = /Android/i.test(ua);
    var isWebView = /\bwv\b|Version\/\d+\.\d+.*Chrome\/\d+/i.test(ua);
    if (!isAndroid || isWebView) return;

    var schemeUrl = 'hrbcp://open?source=web';
    var intentUrl = 'intent://open#Intent;scheme=hrbcp;end';
    var dismissed = localStorage.getItem('apk_suggest_dismissed') === '1';
    if (!dismissed) {
      box.style.display = 'block';
    }

    function openApp() {
      var hiddenAt = Date.now();
      window.location.href = schemeUrl;
      setTimeout(function () {
        if (Date.now() - hiddenAt < 1600) {
          window.location.href = intentUrl;
        }
      }, 700);
    }

    openBtn.addEventListener('click', function () {
      openApp();
    });

    // Best effort auto-open once per session: if app installed, Android will switch to app.
    if (sessionStorage.getItem('apk_auto_open_done') !== '1') {
      sessionStorage.setItem('apk_auto_open_done', '1');
      setTimeout(openApp, 400);
    }

    closeBtn.addEventListener('click', function () {
      box.style.display = 'none';
      localStorage.setItem('apk_suggest_dismissed', '1');
    });
  })();

  (function () {
    var clock = document.getElementById('liveClock');
    if (!clock) return;

    function pad(v) {
      return String(v).padStart(2, '0');
    }

    function tick() {
      var now = new Date();
      var hh = pad(now.getHours());
      var mm = pad(now.getMinutes());
      var ss = pad(now.getSeconds());
      clock.textContent = hh + ':' + mm + ':' + ss;
    }

    tick();
    setInterval(tick, 1000);
  })();

  (function () {
    var groups = document.querySelectorAll('.nav-group');
    if (!groups.length) return;
    var stored = {};
    try {
      stored = JSON.parse(localStorage.getItem('sidebar_groups') || '{}');
    } catch (e) {
      stored = {};
    }

    groups.forEach(function (group) {
      var key = group.getAttribute('data-group');
      var toggle = group.querySelector('.nav-group-toggle');
      var activeLink = group.querySelector('.nav-link.active');
      if (stored[key] === 'collapsed' && !activeLink) {
        group.classList.add('collapsed');
      }
      if (activeLink) {
        group.classList.remove('collapsed');
      }
      if (toggle) {
        toggle.addEventListener('click', function () {
          group.classList.toggle('collapsed');
          if (key) {
            stored[key] = group.classList.contains('collapsed') ? 'collapsed' : 'open';
            localStorage.setItem('sidebar_groups', JSON.stringify(stored));
          }
        });
      }
    });
  })();

  (function () {
    var inputs = document.querySelectorAll('input.js-time-24');
    if (!inputs.length) return;

    function normalize(val) {
      if (!val) return '';
      var raw = String(val).trim();
      if (raw === '') return '';
      if (/^\d{1,2}$/.test(raw)) {
        return raw.padStart(2, '0') + ':00';
      }
      if (/^\d{1,2}:\d{1,2}$/.test(raw)) {
        var parts = raw.split(':');
        var hh = parts[0].padStart(2, '0');
        var mm = parts[1].padStart(2, '0');
        return hh + ':' + mm;
      }
      return raw;
    }

    inputs.forEach(function (input) {
      input.addEventListener('blur', function () {
        var next = normalize(input.value);
        if (next) input.value = next;
      });
    });
  })();

  (function () {
    var buttons = document.querySelectorAll('.js-time-picker-btn');
    if (!buttons.length) return;
    var popover = null;
    var activeInput = null;

    function closePicker() {
      if (popover && popover.parentNode) {
        popover.parentNode.removeChild(popover);
      }
      popover = null;
      activeInput = null;
    }

    function buildOptions(step) {
      var opts = [];
      for (var h = 0; h < 24; h++) {
        for (var m = 0; m < 60; m += step) {
          var hh = String(h).padStart(2, '0');
          var mm = String(m).padStart(2, '0');
          opts.push(hh + ':' + mm);
        }
      }
      return opts;
    }

    function openPicker(input, btn) {
      closePicker();
      activeInput = input;
      var step = parseInt(input.getAttribute('data-step') || '15', 10);
      if (isNaN(step) || step <= 0 || step > 60) step = 15;

      popover = document.createElement('div');
      popover.className = 'time-picker-popover';
      var list = document.createElement('div');
      list.className = 'time-picker-list';
      buildOptions(step).forEach(function (val) {
        var item = document.createElement('button');
        item.type = 'button';
        item.className = 'time-picker-item';
        item.textContent = val;
        item.addEventListener('click', function () {
          input.value = val;
          input.dispatchEvent(new Event('change', { bubbles: true }));
          closePicker();
        });
        list.appendChild(item);
      });
      popover.appendChild(list);
      document.body.appendChild(popover);

      var rect = btn.getBoundingClientRect();
      var top = rect.bottom + window.scrollY + 6;
      var left = rect.left + window.scrollX - 140;
      if (left < 8) left = 8;
      popover.style.top = top + 'px';
      popover.style.left = left + 'px';
    }

    buttons.forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var target = btn.getAttribute('data-target');
        if (!target) return;
        var input = document.querySelector(target);
        if (!input) return;
        if (popover && activeInput === input) {
          closePicker();
          return;
        }
        openPicker(input, btn);
      });
    });

    document.addEventListener('click', function (e) {
      if (!popover) return;
      if (popover.contains(e.target)) return;
      if (e.target.closest('.js-time-picker-btn')) return;
      closePicker();
    });
  })();
</script>
</body>
</html>
