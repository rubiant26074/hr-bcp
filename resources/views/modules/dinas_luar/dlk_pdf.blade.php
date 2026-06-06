<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
    .header { text-align: center; }
    .logo { height: 40px; }
    .title { font-weight: bold; font-size: 14px; margin-top: 6px; }
    .sub { font-size: 11px; margin-top: 2px; }
    .info-table { width: 100%; margin-top: 10px; }
    .info-table td { padding: 2px 4px; vertical-align: top; }
    .section-title { font-weight: bold; margin-top: 10px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #333; padding: 4px; }
    th { background: #f2f2f2; }
    .no-border td { border: none; }
    .text-right { text-align: right; }
    .signatures { width: 100%; margin-top: 20px; }
    .sign-box { width: 25%; text-align: center; }
    .sign-name { margin-top: 36px; font-weight: bold; }
  </style>
</head>
<body>
  <table class="no-border" style="width:100%;">
    <tr>
      <td style="width:60px;">
        @if (!empty($logoDataUri))
          <img class="logo" src="{{ $logoDataUri }}">
        @endif
      </td>
      <td class="header">
        <div class="title">{{ $company->company_name ?? 'Company' }}</div>
        <div class="sub">Form Pengajuan Dana Dinas Luar Kota</div>
      </td>
      <td style="width:60px;"></td>
    </tr>
  </table>

  <table class="info-table">
    <tr>
      <td width="18%">No Dokumen</td><td width="2%">:</td><td width="30%">{{ $row->doc_no ?? '-' }}</td>
      <td width="18%">Tanggal</td><td width="2%">:</td><td>{{ $row->request_date ? format_date_id($row->request_date) : '-' }}</td>
    </tr>
    <tr>
      <td>Lama Pekerjaan</td><td>:</td><td>{{ $row->work_start ? format_date_id($row->work_start) : '-' }} s/d {{ $row->work_end ? format_date_id($row->work_end) : '-' }}</td>
      <td>Perpanjangan Ke</td><td>:</td><td>{{ $row->extension_no ?? 0 }}</td>
    </tr>
    <tr>
      <td>Customer</td><td>:</td><td>{{ $row->customer ?? '-' }}</td>
      <td>No. WO</td><td>:</td><td>{{ $row->work_order_no ?? '-' }}</td>
    </tr>
    <tr>
      <td>Project</td><td>:</td><td>{{ $row->project ?? '-' }}</td>
      <td>Pekerjaan</td><td>:</td><td>{{ $row->pekerjaan ?? '-' }}</td>
    </tr>
    <tr>
      <td>Lokasi</td><td>:</td><td>{{ $row->lokasi ?? '-' }}</td>
      <td></td><td></td><td></td>
    </tr>
  </table>

  <div class="section-title">A. BIAYA LUMPSUM</div>
  <table>
    <thead>
      <tr>
        <th width="5%">No</th>
        <th>Nama</th>
        <th width="10%">Hari</th>
        <th width="20%">Jumlah</th>
        <th width="20%">Total</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($lumpsums as $i => $r)
        <tr>
          <td class="text-right">{{ $i + 1 }}</td>
          <td>{{ $r->name }}</td>
          <td class="text-right">{{ $r->days }}</td>
          <td class="text-right">{{ format_currency($r->amount) }}</td>
          <td class="text-right">{{ format_currency($r->total) }}</td>
        </tr>
      @endforeach
      @if (count($lumpsums) === 0)
        <tr><td colspan="5">-</td></tr>
      @endif
      <tr>
        <td colspan="4"><strong>Total A</strong></td>
        <td class="text-right"><strong>{{ format_currency($totalA) }}</strong></td>
      </tr>
    </tbody>
  </table>

  <div class="section-title">B. FASILITAS</div>
  <table>
    <thead>
      <tr>
        <th width="5%">No</th>
        <th>Fasilitas</th>
        <th width="20%">Didanai</th>
        <th width="20%">Jumlah</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($facilities as $i => $r)
        <tr>
          <td class="text-right">{{ $i + 1 }}</td>
          <td>{{ $r->name }}</td>
          <td>{{ $r->funded_by }}</td>
          <td class="text-right">{{ format_currency($r->amount) }}</td>
        </tr>
      @endforeach
      @if (count($facilities) === 0)
        <tr><td colspan="4">-</td></tr>
      @endif
      <tr>
        <td colspan="3"><strong>Total B</strong></td>
        <td class="text-right"><strong>{{ format_currency($totalB) }}</strong></td>
      </tr>
    </tbody>
  </table>

  <div class="section-title">C. LAIN-LAIN</div>
  <table>
    <thead>
      <tr>
        <th width="5%">No</th>
        <th>Nama</th>
        <th width="20%">Jumlah</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($others as $i => $r)
        <tr>
          <td class="text-right">{{ $i + 1 }}</td>
          <td>{{ $r->name }}</td>
          <td class="text-right">{{ format_currency($r->amount) }}</td>
        </tr>
      @endforeach
      @if (count($others) === 0)
        <tr><td colspan="3">-</td></tr>
      @endif
      <tr>
        <td colspan="2"><strong>Total C</strong></td>
        <td class="text-right"><strong>{{ format_currency($totalC) }}</strong></td>
      </tr>
    </tbody>
  </table>

  <table style="margin-top:8px;">
    <tr>
      <td><strong>Grand Total A+B+C</strong></td>
      <td class="text-right"><strong>{{ format_currency($grandTotal) }}</strong></td>
    </tr>
  </table>

  <div style="margin-top:8px;"><strong>Catatan:</strong> {{ $row->notes ?? '-' }}</div>

  @php
    $approverNames = [];
    foreach ($steps as $s) {
      if (($s->status ?? '') === 'Approved' && isset($userMap[$s->approved_by ?? 0])) {
        $approverNames[] = $userMap[$s->approved_by]->name;
      }
    }
    $requesterName = isset($userMap[$row->requester_user_id ?? 0]) ? $userMap[$row->requester_user_id]->name : '-';
  @endphp
  <table class="signatures">
    <tr>
      <td class="sign-box">Dibuat Oleh</td>
      <td class="sign-box">Mengetahui</td>
      <td class="sign-box">Mengetahui</td>
      <td class="sign-box">Mengetahui</td>
    </tr>
    <tr>
      <td class="sign-box">
        <div class="sign-name">{{ $requesterName }}</div>
      </td>
      <td class="sign-box">
        <div class="sign-name">{{ $approverNames[0] ?? '-' }}</div>
      </td>
      <td class="sign-box">
        <div class="sign-name">{{ $approverNames[1] ?? '-' }}</div>
      </td>
      <td class="sign-box">
        <div class="sign-name">{{ $approverNames[2] ?? '-' }}</div>
      </td>
    </tr>
  </table>
</body>
</html>
