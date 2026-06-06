<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Surat Lembur</title>
  <style>
    @page { size: 210mm 148mm; margin: 14mm 20mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; margin: 0; line-height: 1.2; }
    .page { width: 100%; min-height: 148mm; padding: 0; box-sizing: border-box; }
    .title { text-align: center; font-size: 13px; font-weight: 700; letter-spacing: 0.3px; }
    .subtitle { text-align: center; font-size: 8px; color: #555; margin-top: 1px; }
    .section { margin-top: 4px; }
    .box { border: 1px solid #333; padding: 4px; max-width: 100%; box-sizing: border-box; }
    .table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .header-table { table-layout: fixed; }
    .header-left { width: 60%; }
    .header-right { width: 40%; text-align: right; }
    .table td { padding: 1px 3px; vertical-align: top; }
    .label { width: 110px; color: #555; }
    .muted { color: #666; }
    .sign-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    .sign-cell { width: 33.33%; text-align: center; vertical-align: bottom; }
    .sign-box { height: 30px; }
    .sign-img { max-height: 28px; max-width: 140px; display: inline-block; }
    .sign-line { border-top: 1px solid #333; padding-top: 1px; }
    .small { font-size: 8px; }

    @media screen {
      body { background: #f2f4f7; padding: 24px 0; }
      .page { margin: 0 auto; background: #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.08); max-width: 200mm; }
    }
  </style>
</head>
<body>
  <div class="page">
  <table class="table header-table">
    <tr>
      <td class="header-left">
        @if (!empty($logoDataUri))
          <img src="{{ $logoDataUri }}" alt="Logo" style="height:38px; margin-bottom:4px;">
        @endif
        <div><strong>{{ $company->company_name ?? 'Company' }}</strong></div>
        <div class="small muted">BCP-HRIS -- V1.0</div>
      </td>
      <td class="header-right">
        <div class="small">Nomor: LEMBUR/{{ (int) $item->id }}/{{ strtoupper($company->company_code ?? '') }}/{{ date('Y') }}</div>
        <div class="small">Tanggal Cetak: {{ date('d/m/Y') }}</div>
      </td>
    </tr>
  </table>

  <div class="title">SURAT PERMOHONAN LEMBUR</div>
  <div class="subtitle">Permohonan Lembur Karyawan</div>

  <div class="section box">
    <table class="table">
      <tr>
        <td class="label">Nama</td>
        <td>{{ $employee->name ?? '-' }}</td>
        <td class="label">NIK</td>
        <td style="text-align:right;">{{ $employee->nik ?? '-' }}</td>
      </tr>
      <tr>
        <td class="label">Jabatan</td>
        <td>{{ $employee->position ?? '-' }}</td>
        <td class="label">Status</td>
        <td style="text-align:right;">{{ $item->status ?? '-' }}</td>
      </tr>
      <tr>
        <td class="label">Tanggal</td>
        <td>{{ format_date_id($item->date) }}</td>
        <td class="label">Jam</td>
        <td style="text-align:right;">{{ format_time_id($item->time_start) }} - {{ format_time_id($item->time_end) }}</td>
      </tr>
      <tr>
        <td class="label">Alasan</td>
        <td colspan="3">{{ $item->reason ?? '-' }}</td>
      </tr>
    </table>
  </div>

  <div class="section">
    <div class="small muted">Dengan ini menyatakan pengajuan lembur sesuai data di atas.</div>
  </div>

  <table class="sign-table">
    <tr>
      <td class="sign-cell">
        <div class="small">Pemohon</div>
        <div class="sign-box">
          @if (!empty($requesterSignature))
            <img class="sign-img" src="{{ $requesterSignature }}" alt="Ttd Pemohon">
          @else
            <div class="small muted">-</div>
          @endif
        </div>
        <div class="sign-line">{{ $requesterUser->name ?? ($employee->name ?? '-') }}</div>
      </td>
      <td class="sign-cell">
        <div class="small">Approval 1</div>
        <div class="sign-box">
          @if (!empty($approver1Signature))
            <img class="sign-img" src="{{ $approver1Signature }}" alt="Ttd Approval 1">
          @else
            <div class="small muted">{{ $item->atasan_signature ?: '-' }}</div>
          @endif
        </div>
        <div class="sign-line">{{ $approver1User->name ?? '-' }}</div>
      </td>
      <td class="sign-cell">
        <div class="small">Approval Final</div>
        <div class="sign-box">
          @if (!empty($approver2Signature))
            <img class="sign-img" src="{{ $approver2Signature }}" alt="Ttd Approval Final">
          @else
            <div class="small muted">{{ $item->hrd_signature ?: '-' }}</div>
          @endif
        </div>
        <div class="sign-line">{{ $approver2User->name ?? '-' }}</div>
      </td>
    </tr>
  </table>

  <div class="section small muted">
    <strong>Catatan:</strong> Dokumen ini dihasilkan otomatis oleh sistem HR.
  </div>
  </div>
</body>
</html>
