@php
  $now = date('Y-m-d H:i:s');
  $maxPresent = 0;
  foreach ($presentSeries as $v) {
      if ($v > $maxPresent) {
          $maxPresent = $v;
      }
  }
  $maxPresent = max(1, $maxPresent);
  $lastScanText = '-';
  if (!empty($lastActivity?->scan_time)) {
      $lastScanText = format_datetime_id($lastActivity->scan_time);
      if (!empty($lastActivity?->name)) {
          $lastScanText .= ' • ' . $lastActivity->name;
      }
  }
  $periodLabel = $latestPeriod ? ($latestPeriod->month . '/' . $latestPeriod->year) : '-';
  $periodStatus = $latestPeriod ? ($latestPeriod->status ?? '-') : '-';
@endphp
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>TV Dashboard KPI - BCP HRIS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #0b1220;
      --bg-2: #0f1b2d;
      --card: #121f33;
      --card-2: #172640;
      --text: #e9f1ff;
      --muted: #9fb0c8;
      --accent: #40c9ff;
      --accent-2: #8cffc7;
      --warn: #ffcf5b;
      --danger: #ff7b7b;
      --shadow: 0 20px 45px rgba(4, 10, 26, 0.55);
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: "Space Grotesk", system-ui, sans-serif;
      color: var(--text);
      background: radial-gradient(1200px 700px at 20% 0%, #14305a 0%, var(--bg) 60%);
    }
    .screen {
      min-height: 100vh;
      padding: 24px 32px 28px;
      display: flex;
      flex-direction: column;
      gap: 18px;
    }
    header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }
    .title {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .title h1 {
      margin: 0;
      font-family: "DM Serif Display", serif;
      font-size: 32px;
      letter-spacing: 0.5px;
    }
    .title .meta {
      color: var(--muted);
      font-size: 14px;
    }
    .clock {
      text-align: right;
      font-size: 28px;
      font-weight: 600;
    }
    .clock .sub {
      font-size: 12px;
      color: var(--muted);
      letter-spacing: 0.6px;
      text-transform: uppercase;
    }
    .grid {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 14px;
    }
    .grid-2 {
      display: grid;
      grid-template-columns: 2.2fr 1.3fr;
      gap: 14px;
    }
    .panel {
      background: linear-gradient(180deg, var(--card), var(--card-2));
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 16px;
      padding: 16px;
      box-shadow: var(--shadow);
    }
    .kpi {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .kpi .value {
      font-size: 28px;
      font-weight: 700;
      letter-spacing: 0.3px;
    }
    .kpi .label {
      font-size: 12px;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.8px;
    }
    .chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(64, 201, 255, 0.15);
      color: var(--accent);
      padding: 4px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 600;
      letter-spacing: 0.5px;
      text-transform: uppercase;
    }
    .chip.warn { color: var(--warn); background: rgba(255, 207, 91, 0.15); }
    .chip.danger { color: var(--danger); background: rgba(255, 123, 123, 0.15); }
    .section-title {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 12px;
      font-size: 14px;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    .trend {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 8px;
      align-items: end;
      height: 140px;
    }
    .bar {
      background: linear-gradient(180deg, var(--accent), #2e7dff);
      border-radius: 8px;
      width: 100%;
      min-height: 8px;
      box-shadow: 0 8px 20px rgba(64, 201, 255, 0.25);
    }
    .bar-label {
      margin-top: 6px;
      font-size: 12px;
      color: var(--muted);
      text-align: center;
    }
    .list {
      display: grid;
      grid-template-columns: 1fr;
      gap: 8px;
      font-size: 13px;
      color: var(--text);
    }
    .list-item {
      display: flex;
      justify-content: space-between;
      gap: 8px;
      color: var(--text);
    }
    .list-item span:first-child {
      color: var(--muted);
    }
    .badge {
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 12px;
      background: rgba(140, 255, 199, 0.15);
      color: var(--accent-2);
      font-weight: 600;
    }
    .badge.warn {
      background: rgba(255, 207, 91, 0.15);
      color: var(--warn);
    }
    .badge.danger {
      background: rgba(255, 123, 123, 0.15);
      color: var(--danger);
    }
    .split {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;
    }
    .footer-note {
      color: var(--muted);
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.8px;
    }
    .slides-wrap {
      position: relative;
      flex: 1 1 auto;
      min-height: 0;
      display: flex;
      flex-direction: column;
    }
    .slide-page {
      display: none;
      flex-direction: column;
      gap: 12px;
      min-height: 0;
      flex: 1 1 auto;
      animation: tvFadeIn 0.35s ease;
    }
    .slide-page.active {
      display: flex;
    }
    .slide-indicator {
      display: flex;
      justify-content: flex-end;
      align-items: center;
      gap: 8px;
      font-size: 12px;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.8px;
      margin-bottom: 4px;
    }
    .slide-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: rgba(255,255,255,0.2);
    }
    .slide-dot.active {
      background: var(--accent);
      box-shadow: 0 0 0 4px rgba(64, 201, 255, 0.12);
    }
    .slide-page section {
      margin: 0;
    }
    .slide-main .grid-kpi {
      grid-template-columns: repeat(6, minmax(0, 1fr));
    }
    .slide-main .grid-main {
      flex: 1 1 auto;
      min-height: 0;
    }
    .slide-main .grid-main .panel {
      height: 100%;
    }
    .slide-detail .grid-detail {
      grid-template-columns: repeat(4, minmax(0, 1fr));
      flex: 1 1 auto;
      min-height: 0;
    }
    .slide-detail .grid-detail-bottom {
      grid-template-columns: repeat(2, minmax(0, 1fr));
      flex: 0 0 auto;
    }
    .slide-detail .grid-detail .panel {
      min-height: 0;
    }
    @keyframes tvFadeIn {
      from { opacity: 0.2; transform: translateY(4px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .donut-wrap {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 10px;
    }
    .donut {
      width: 92px;
      height: 92px;
      border-radius: 50%;
      background: conic-gradient(#1f3557 0deg, #1f3557 360deg);
      position: relative;
      flex: 0 0 auto;
    }
    .donut::after {
      content: "";
      position: absolute;
      inset: 18px;
      border-radius: 50%;
      background: var(--card-2);
      border: 1px solid rgba(255,255,255,0.06);
    }
    .donut-center {
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 2;
      font-size: 11px;
      color: var(--muted);
      font-weight: 600;
      letter-spacing: 0.6px;
      text-transform: uppercase;
    }
    @media (max-width: 1400px) {
      .slide-main .grid-kpi { grid-template-columns: repeat(3, minmax(0, 1fr)); }
      .slide-detail .grid-detail { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 1200px) {
      .grid { grid-template-columns: repeat(3, 1fr); }
      .grid-2 { grid-template-columns: 1fr; }
      .slide-detail .grid-detail-bottom { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <div class="screen">
    <header>
      <div class="title">
        <h1>TV KPI Dashboard</h1>
        <div class="meta">Overall HR Performance • {{ format_date_id($today) }} • Range 7 Hari ({{ format_date_id($rangeStartStr) }} — {{ format_date_id($rangeEndStr) }})</div>
      </div>
      <div class="clock">
        <div id="liveClock">00:00:00</div>
        <div class="sub">Slide 15 detik • Auto refresh 60 detik</div>
      </div>
    </header>

    <div class="slides-wrap">
      <div class="slide-indicator">
        <span id="slideLabel">Slide 1/2 - Ringkasan Utama</span>
        <span class="slide-dot active"></span>
        <span class="slide-dot"></span>
      </div>
      <div class="slide-page slide-main active" data-slide="1">
    <section class="grid grid-kpi">
      <div class="panel kpi">
        <div class="label">Total Karyawan</div>
        <div class="value">{{ number_format($totalEmployees) }}</div>
        <div class="chip">Aktif</div>
      </div>
      <div class="panel kpi">
        <div class="label">Hadir Minggu Ini</div>
        <div class="value">{{ number_format($presentToday) }}</div>
        <div class="chip">{{ $attendancePct }}% Attendance</div>
      </div>
      <div class="panel kpi">
        <div class="label">Tidak Hadir</div>
        <div class="value">{{ number_format($absentToday) }}</div>
        <div class="chip danger">Absensi</div>
      </div>
      <div class="panel kpi">
        <div class="label">Terlambat</div>
        <div class="value">{{ number_format($lateToday) }}</div>
        <div class="chip warn">Minggu Ini</div>
      </div>
      <div class="panel kpi">
        <div class="label">Lembur</div>
        <div class="value">{{ number_format($overtimeToday) }}</div>
        <div class="chip">Minggu Ini</div>
      </div>
      <div class="panel kpi">
        <div class="label">Karyawan Baru 30 Hari</div>
        <div class="value">{{ number_format($new30Count) }}</div>
        <div class="chip">New Join</div>
      </div>
    </section>

    <section class="grid-2 grid-main">
      <div class="panel">
        <div class="section-title">
          <span>Trend Kehadiran 7 Hari</span>
          <span class="badge">{{ $attendancePctRange }}% Avg</span>
        </div>
        <div class="trend">
          @foreach ($presentSeries as $idx => $val)
            @php $height = round(($val / $maxPresent) * 120) + 8; @endphp
            <div>
              <div class="bar" style="height: {{ $height }}px;"></div>
              <div class="bar-label">{{ $labels[$idx] ?? '' }}</div>
            </div>
          @endforeach
        </div>
        <div class="split" style="margin-top: 14px;">
          <div class="kpi">
            <div class="label">Total Hadir (7 Hari)</div>
            <div class="value">{{ number_format($presentTotal) }}</div>
          </div>
          <div class="kpi">
            <div class="label">Total Tidak Hadir (7 Hari)</div>
            <div class="value">{{ number_format($absentTotal) }}</div>
          </div>
          <div class="kpi">
            <div class="label">Lembur (7 Hari)</div>
            <div class="value">{{ number_format($overtimeSumRange, 1) }}</div>
          </div>
          <div class="kpi">
            <div class="label">Terlambat (7 Hari)</div>
            <div class="value">{{ number_format($lateCountRange) }}</div>
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="section-title">
          <span>Alert & Ringkasan</span>
          <span class="badge warn">Realtime</span>
        </div>
        <div class="list">
          <div class="list-item">
            <span>Kontrak Habis ≤ 7 Hari</span>
            <span class="badge danger">{{ number_format($pkwt7Count) }}</span>
          </div>
          <div class="list-item">
            <span>Kontrak Habis ≤ 30 Hari</span>
            <span class="badge warn">{{ number_format($pkwt30Count) }}</span>
          </div>
          <div class="list-item">
            <span>Payroll Period</span>
            <span class="badge">{{ $periodLabel }}</span>
          </div>
          <div class="list-item">
            <span>Status Payroll</span>
            <span class="badge">{{ $periodStatus }}</span>
          </div>
          <div class="list-item">
            <span>Total Payroll (Last Closed)</span>
            <span class="badge">{{ number_format($payrollTotal, 0) }}</span>
          </div>
          <div class="list-item">
            <span>Last Scan</span>
            <span class="badge">{{ $lastScanText }}</span>
          </div>
        </div>
      </div>
    </section>
      </div>

      <div class="slide-page slide-detail" data-slide="2">
    <section class="grid grid-detail">
      <div class="panel">
        <div class="section-title">10 Personel Terbaik Absensi ({{ format_date_id($rangeStartStr) }} - {{ format_date_id($rangeEndStr) }})</div>
        <div class="list">
          @forelse ($topAttendancePeople as $row)
            <div class="list-item">
              <span>{{ $row->name ?: '-' }} ({{ $row->position ?: '-' }})</span>
              <span class="badge">{{ $row->present_days }}H / {{ $row->present_days > 0 ? $row->late_days : '-' }}T / {{ $row->absent_days }}A</span>
            </div>
          @empty
            <div class="list-item"><span>-</span><span class="badge">0</span></div>
          @endforelse
        </div>
      </div>
      <div class="panel">
        <div class="section-title">10 Personel Terburuk Absensi ({{ format_date_id($rangeStartStr) }} - {{ format_date_id($rangeEndStr) }})</div>
        <div class="list">
          @forelse ($bottomAttendancePeople as $row)
            <div class="list-item">
              <span>{{ $row->name ?: '-' }} ({{ $row->position ?: '-' }})</span>
              <span class="badge danger">{{ $row->absent_days }}A / {{ $row->present_days > 0 ? $row->late_days : '-' }}T / {{ $row->present_days }}H</span>
            </div>
          @empty
            <div class="list-item"><span>-</span><span class="badge">0</span></div>
          @endforelse
        </div>
      </div>
      <div class="panel">
        <div class="section-title">Headcount per Company</div>
        <div class="list">
          @forelse ($companyHeadcount as $row)
            <div class="list-item">
              <span>{{ $row->company_code }} {{ $row->company_name }}</span>
              <span class="badge">{{ number_format($row->total) }}</span>
            </div>
          @empty
            <div class="list-item"><span>-</span><span class="badge">0</span></div>
          @endforelse
        </div>
      </div>
      <div class="panel">
        <div class="section-title">Kehadiran Minggu Ini</div>
        <div class="list">
          @forelse ($attendanceByCompany as $row)
            <div class="list-item">
              <span>{{ $row->company_code }} {{ $row->company_name }}</span>
              <span class="badge">{{ number_format($row->present_count) }}</span>
            </div>
          @empty
            <div class="list-item"><span>-</span><span class="badge">0</span></div>
          @endforelse
        </div>
      </div>
      <div class="panel">
        <div class="section-title">Distribusi Status</div>
        <div class="donut-wrap">
          <div class="donut" id="donutStatus"><span class="donut-center">STATUS</span></div>
        </div>
        <div class="list">
          @forelse ($statusRows as $row)
            <div class="list-item">
              <span>{{ $row->label }}</span>
              <span class="badge">{{ number_format($row->total) }}</span>
            </div>
          @empty
            <div class="list-item"><span>-</span><span class="badge">0</span></div>
          @endforelse
        </div>
      </div>
      <div class="panel">
        <div class="section-title">Jenis Karyawan</div>
        <div class="donut-wrap">
          <div class="donut" id="donutType"><span class="donut-center">JENIS</span></div>
        </div>
        <div class="list">
          @forelse ($typeRows as $row)
            <div class="list-item">
              <span>{{ $row->label }}</span>
              <span class="badge">{{ number_format($row->total) }}</span>
            </div>
          @empty
            <div class="list-item"><span>-</span><span class="badge">0</span></div>
          @endforelse
        </div>
      </div>
      <div class="panel">
        <div class="section-title">Top Posisi</div>
        <div class="list">
          @forelse ($positionRows as $row)
            <div class="list-item">
              <span>{{ $row->label }}</span>
              <span class="badge">{{ number_format($row->total) }}</span>
            </div>
          @empty
            <div class="list-item"><span>-</span><span class="badge">0</span></div>
          @endforelse
        </div>
      </div>
      <div class="panel">
        <div class="section-title">Top Grade</div>
        <div class="list">
          @forelse ($gradeRows as $row)
            <div class="list-item">
              <span>{{ $row->label }}</span>
              <span class="badge">{{ number_format($row->total) }}</span>
            </div>
          @empty
            <div class="list-item"><span>-</span><span class="badge">0</span></div>
          @endforelse
        </div>
      </div>
    </section>

    <section class="grid-2 grid-detail-bottom">
      <div class="panel">
        <div class="section-title">Top Lembur per Posisi (7 Hari)</div>
        <div class="list">
          @forelse ($deptRows as $row)
            <div class="list-item">
              <span>{{ $row->dept ?? 'N/A' }}</span>
              <span class="badge">{{ number_format((float) $row->ot, 1) }}</span>
            </div>
          @empty
            <div class="list-item"><span>-</span><span class="badge">0</span></div>
          @endforelse
        </div>
      </div>
      <div class="panel">
        <div class="section-title">Payroll per Company (Closed)</div>
        <div class="list">
          @forelse ($payrollByCompany as $row)
            <div class="list-item">
              <span>{{ $row->company_code }} {{ $row->company_name }}</span>
              <span class="badge">{{ number_format((float) $row->total, 0) }}</span>
            </div>
          @empty
            <div class="list-item"><span>-</span><span class="badge">0</span></div>
          @endforelse
        </div>
        <div class="footer-note" style="margin-top: 12px;">
          Updated {{ format_datetime_id($now) }}
        </div>
      </div>
    </section>
      </div>
    </div>
  </div>

  <script>
    (function () {
      var clock = document.getElementById('liveClock');
      function pad(v) { return String(v).padStart(2, '0'); }
      function tick() {
        var now = new Date();
        clock.textContent = pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
      }
      tick();
      setInterval(tick, 1000);
      var slidePages = Array.prototype.slice.call(document.querySelectorAll('.slide-page'));
      var slideDots = Array.prototype.slice.call(document.querySelectorAll('.slide-indicator .slide-dot'));
      var slideLabel = document.getElementById('slideLabel');
      var activeIndex = 0;
      function renderSlide(index) {
        slidePages.forEach(function (el, idx) {
          el.classList.toggle('active', idx === index);
        });
        slideDots.forEach(function (el, idx) {
          el.classList.toggle('active', idx === index);
        });
        if (slideLabel) {
          slideLabel.textContent = index === 0 ? 'Slide 1/2 - Ringkasan Utama' : 'Slide 2/2 - Detail Analitik';
        }
      }
      if (slidePages.length > 1) {
        setInterval(function () {
          activeIndex = (activeIndex + 1) % slidePages.length;
          renderSlide(activeIndex);
        }, 15000);
      }
      setInterval(function () { window.location.reload(); }, 60000);
    })();

    (function () {
      function buildDonut(elId, rawItems) {
        var el = document.getElementById(elId);
        if (!el) return;

        var items = (rawItems || []).filter(function (item) {
          return Number(item.total || 0) > 0;
        });

        var palette = ['#40c9ff', '#8cffc7', '#ffcf5b', '#ff7b7b', '#8d7dff', '#39b98a', '#5ea5ff', '#f78fb3'];
        if (!items.length) {
          el.style.background = 'conic-gradient(#1f3557 0deg, #1f3557 360deg)';
          return;
        }

        var total = items.reduce(function (sum, item) { return sum + Number(item.total || 0); }, 0);
        if (total <= 0) {
          el.style.background = 'conic-gradient(#1f3557 0deg, #1f3557 360deg)';
          return;
        }

        var angle = 0;
        var stops = [];
        items.forEach(function (item, idx) {
          var slice = (Number(item.total || 0) / total) * 360;
          var next = angle + slice;
          var color = palette[idx % palette.length];
          stops.push(color + ' ' + angle.toFixed(2) + 'deg ' + next.toFixed(2) + 'deg');
          angle = next;
        });

        el.style.background = 'conic-gradient(' + stops.join(', ') + ')';
      }

      buildDonut('donutStatus', @json(collect($statusRows)->map(function ($r) { return ['label' => $r->label, 'total' => (int) $r->total]; })->values()));
      buildDonut('donutType', @json(collect($typeRows)->map(function ($r) { return ['label' => $r->label, 'total' => (int) $r->total]; })->values()));
    })();
  </script>
</body>
</html>
