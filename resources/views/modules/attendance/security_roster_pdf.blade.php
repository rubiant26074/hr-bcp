<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #000; }
    .title-wrap { text-align: center; margin-bottom: 8px; }
    .title { font-size: 44px; font-weight: 800; letter-spacing: 1px; line-height: 1; }
    .subtitle { font-size: 26px; font-weight: 700; line-height: 1.2; margin-top: 4px; }

    table { border-collapse: collapse; width: 100%; table-layout: fixed; }
    .matrix { border: 2px solid #000; }
    .matrix th, .matrix td {
      border: 1px solid #000;
      padding: 2px 3px;
      text-align: center;
      vertical-align: middle;
      line-height: 1.1;
    }
    .matrix .thick-right { border-right: 2px solid #000 !important; }
    .matrix .thick-top { border-top: 2px solid #000 !important; }
    .matrix .thick-bottom { border-bottom: 2px solid #000 !important; }
    .matrix .left { text-align: left; }
    .matrix .site-col { width: 64px; }
    .matrix .no-col { width: 40px; }
    .matrix .name-col { width: 210px; }
    .matrix .day-col { width: 44px; }
    .matrix .shift-col { width: 30px; }
    .matrix .site-cell { font-weight: 700; text-align: left; }
    .matrix .name-cell { white-space: nowrap; }

    .bg-shift { background: #f7eed0; }
    .off-cell { background: #ff1a1a; color: #ff1a1a; }
    .off-text { color: #fff; font-weight: 700; }

    .legend { margin-top: 14px; width: 540px; }
    .legend-title { font-size: 32px; font-weight: 800; margin-bottom: 8px; }
    .legend-table td { border: none; padding: 2px 4px; font-size: 28px; vertical-align: middle; }
    .legend-box { width: 34px; height: 22px; border: 1px solid #000; display: inline-block; text-align: center; line-height: 22px; font-weight: 700; }
    .legend-box.off { background: #ff1a1a; }
  </style>
</head>
<body>
  <div class="title-wrap">
    <div class="title">JADWAL SECURITY BCP-GROUP</div>
    <div class="subtitle">
      @if (!empty($periodStart) && !empty($periodEnd))
        PERIODE {{ date('d/m/Y', strtotime($periodStart)) }} - {{ date('d/m/Y', strtotime($periodEnd)) }}
      @else
        PERIODE BULAN {{ strtoupper(date('F', strtotime(sprintf('%04d-%02d-01', $year, $month)))) }} {{ $year }}
      @endif
    </div>
  </div>

  <table class="matrix">
    <thead>
      <tr>
        <th class="site-col thick-right"></th>
        <th class="no-col thick-right">NO</th>
        <th class="name-col left thick-right">HARI</th>
        @foreach ($days as $d)
          <th class="shift-col">{{ $d['dow'] }}</th>
        @endforeach
      </tr>
      <tr>
        <th class="thick-right"></th>
        <th class="thick-right"></th>
        <th class="left thick-right">TANGGAL</th>
        @foreach ($days as $d)
          <th>{{ $d['day_no'] }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @php $globalNo = 1; @endphp
      @foreach ($groups as $site => $rows)
        @foreach ($rows as $idx => $r)
          <tr class="{{ $idx === 0 ? 'thick-top' : '' }}">
            @if ($idx === 0)
              <td class="site-cell thick-right" rowspan="{{ count($rows) }}">{{ $site }}</td>
            @endif
            <td class="thick-right">{{ $globalNo++ }}</td>
            <td class="left name-cell thick-right">{{ $r['name'] }}</td>
            @foreach ($r['shifts'] as $s)
              @php $v = strtoupper((string) $s); @endphp
              @if ($v === 'OFF')
                <td class="off-cell"><span class="off-text">OFF</span></td>
              @else
                <td class="bg-shift">{{ $v }}</td>
              @endif
            @endforeach
          </tr>
        @endforeach
      @endforeach
    </tbody>
  </table>

  <div class="legend">
    <div class="legend-title">NOTASI :</div>
    <table class="legend-table">
      <tr>
        <td><span class="legend-box off"></span></td>
        <td>=</td>
        <td>OFF = LIBUR</td>
      </tr>
      <tr>
        <td><span class="legend-box">P</span></td>
        <td>=</td>
        <td>PAGI</td>
        <td>07:00 - 15:00</td>
      </tr>
      <tr>
        <td><span class="legend-box">S</span></td>
        <td>=</td>
        <td>SIANG</td>
        <td>15:00 - 23:00</td>
      </tr>
      <tr>
        <td><span class="legend-box">M</span></td>
        <td>=</td>
        <td>MALAM</td>
        <td>23:00 - 07:00</td>
      </tr>
    </table>
  </div>
</body>
</html>
