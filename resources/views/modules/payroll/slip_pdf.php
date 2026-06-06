<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2d3d; font-size: 12px; }
    .slip-wrap { border: 1px solid #e6ebf2; border-radius: 10px; padding: 16px; }
    .slip-brand { display: table; width: 100%; }
    .slip-brand-logo { display: table-cell; width: 60px; vertical-align: middle; }
    .slip-brand-logo img { width: 50px; height: auto; }
    .slip-brand-text { display: table-cell; vertical-align: middle; }
    .slip-title { font-size: 18px; font-weight: 700; color: #1f3553; }
    .slip-period { margin-top: 4px; font-weight: 600; color: #1f3553; }
    .slip-divider { border-top: 1px solid #e6ebf2; margin: 12px 0; }
    .slip-section-title { font-size: 11px; font-weight: 700; margin-bottom: 6px; color: #1f3553; }
    .slip-kv { width: 100%; border-collapse: collapse; }
    .slip-kv td { padding: 2px 0; vertical-align: top; }
    .slip-kv .label { width: 110px; color: #6b7a8b; }
    .grid-2 { width: 100%; border-collapse: collapse; }
    .grid-2 td { width: 50%; vertical-align: top; padding-right: 8px; }
    .slip-card { border: 1px solid #dfe6ef; border-radius: 8px; overflow: hidden; }
    .slip-card .head { background: #2b4f77; color: #fff; font-weight: 700; padding: 6px 10px; font-size: 11px; }
    .slip-table { width: 100%; border-collapse: collapse; font-size: 11px; }
    .slip-table th, .slip-table td { padding: 5px 8px; border-bottom: 1px solid #edf1f6; }
    .slip-table th { background: #f3f6fb; text-align: left; }
    .slip-table td:last-child, .slip-table th:last-child { text-align: right; }
    .slip-total-row { background: #f7f9fd; font-weight: 700; }
    .slip-summary { border: 1px solid #dfe6ef; border-radius: 8px; overflow: hidden; margin-top: 10px; }
    .slip-summary .head { background: #2b4f77; color: #fff; font-weight: 700; padding: 6px 10px; font-size: 11px; }
    .slip-summary table { width: 100%; border-collapse: collapse; }
    .slip-summary td { padding: 6px 10px; border-bottom: 1px solid #edf1f6; }
    .slip-summary td:last-child { text-align: right; font-weight: 700; }
    .take-home { background: #2b4f77; color: #fff; font-weight: 800; }
    .ta-il-badge {
      display: inline-block;
      margin-left: 6px;
      padding: 1px 8px;
      border-radius: 999px;
      font-size: 10px;
      font-weight: 700;
      line-height: 1.3;
      color: #fff;
      background: #dc3545;
      vertical-align: middle;
    }
    .ta-il-note { margin-top: 4px; font-size: 10px; color: #6c757d; }
    .slip-sign-fixed {
      position: fixed;
      left: 20px;
      bottom: 22px;
      font-size: 11px;
      color: #1f2d3d;
      line-height: 1.45;
    }
  </style>
</head>
<body>
  <div class="slip-wrap">
    <div class="slip-brand">
      <?php if (!empty($logoDataUri)): ?>
      <div class="slip-brand-logo"><img src="<?= h($logoDataUri) ?>" alt="logo"></div>
      <?php elseif (!empty($logoFileUri)): ?>
      <div class="slip-brand-logo"><img src="<?= h($logoFileUri) ?>" alt="logo"></div>
      <?php endif; ?>
      <div class="slip-brand-text">
        <div class="slip-title"><?= h($item->company_name) ?></div>
        <?php if ($currentPeriod): ?>
          <div class="slip-period">Periode: <?= h($currentPeriod->month) ?>/<?= h($currentPeriod->year) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="slip-divider"></div>
    <div class="slip-section-title">DATA KARYAWAN</div>
    <table class="grid-2">
      <tr>
        <td>
          <table class="slip-kv">
            <tr><td class="label">NIK</td><td><?= h($item->nik) ?></td></tr>
            <tr><td class="label">Nama</td><td><?= h($item->name) ?></td></tr>
            <tr><td class="label">Golongan</td><td><?= h($item->grade) ?></td></tr>
          </table>
        </td>
        <td>
          <table class="slip-kv">
            <tr><td class="label">Jabatan</td><td><?= h($item->position) ?></td></tr>
            <tr><td class="label">Perusahaan</td><td><?= h($item->company_name) ?></td></tr>
          </table>
        </td>
      </tr>
    </table>

    <div class="slip-divider"></div>
    <table class="grid-2">
      <tr>
        <td>
          <div class="slip-card">
            <div class="head">RINCIAN PENERIMAAN</div>
            <table class="slip-table">
              <thead><tr><th style="width:48px;">No.</th><th>Komponen</th><th>Jumlah</th></tr></thead>
              <tbody>
                <tr><td>A1</td><td>Gaji Pokok</td><td><?= format_currency($item->basic_salary) ?></td></tr>
                <tr>
                  <td>A2</td>
                  <td>
                    Lembur ( Lembur: <?= rtrim(rtrim(number_format((float)($lemburHours ?? 0), 2, '.', ''), '0'), '.') ?> Jam | TA-IL: <?= rtrim(rtrim(number_format((float)($taIlHours ?? 0), 2, '.', ''), '0'), '.') ?> Jam )
                    <?php if (!empty($hasTaIl)): ?><span class="ta-il-badge">TA-IL</span><?php endif; ?>
                    <?php if (!empty($hasTaIl)): ?><div class="ta-il-note">TA-IL: Tidak Ada Izin Lembur</div><?php endif; ?>
                  </td>
                  <td><?= format_currency($item->a2_overtime) ?></td>
                </tr>
                <tr><td>A3</td><td>Tunjangan Makan</td><td><?= format_currency($item->a3_meal) ?></td></tr>
                <tr><td>A4</td><td>Tunjangan Transport</td><td><?= format_currency($item->a4_transport) ?></td></tr>
                <tr><td>A5</td><td>Tunjangan Kinerja</td><td><?= format_currency($item->a5_performance) ?></td></tr>
                <tr><td>A6</td><td>Tunjangan Jabatan</td><td><?= format_currency($item->a6_position) ?></td></tr>
                <tr><td>A7</td><td>Tunjangan Anak & Istri</td><td><?= format_currency($item->a7_family) ?></td></tr>
                <tr><td>A8</td><td>Tunjangan Komunikasi</td><td><?= format_currency($item->a8_communication) ?></td></tr>
                <tr><td>A9</td><td>Tunjangan Lain</td><td><?= format_currency($item->a9_other) ?></td></tr>
                <tr><td>A10</td><td>THR</td><td><?= format_currency($item->a10_thr) ?></td></tr>
                <tr><td>A11</td><td>Bonus</td><td><?= format_currency($item->a11_bonus) ?></td></tr>
                <tr><td>A12</td><td>Rapel Gaji</td><td><?= format_currency($item->a12_rapel_gaji ?? 0) ?></td></tr>
                <tr><td>A13</td><td>Tunjangan Pajak</td><td><?= format_currency($item->a12_tax_allowance) ?></td></tr>
                <tr><td>A14</td><td>Tunjangan BPJS</td><td><?= format_currency($item->a13_bpjs_allowance) ?></td></tr>
                <tr class="slip-total-row"><td colspan="2">Total Penerimaan</td><td><?= format_currency($item->total_penerimaan) ?></td></tr>
              </tbody>
            </table>
          </div>
        </td>
        <td>
          <div class="slip-card">
            <div class="head">RINCIAN POTONGAN</div>
            <table class="slip-table">
              <thead><tr><th style="width:48px;">No.</th><th>Komponen</th><th>Jumlah</th></tr></thead>
              <tbody>
                <tr><td>B1</td><td>Pinjaman</td><td><?= format_currency($item->b1_loan) ?></td></tr>
                <tr><td>B2</td><td>Absensi</td><td><?= format_currency($item->b2_absence) ?></td></tr>
                <tr><td>B3</td><td>Subsidi</td><td><?= format_currency($item->b3_subsidy) ?></td></tr>
                <tr><td>B4</td><td>BPJS Kesehatan (1%)</td><td><?= format_currency($item->b4_bpjs_health) ?></td></tr>
                <tr><td>B5</td><td>JHT (2%)</td><td><?= format_currency($item->b5_jht) ?></td></tr>
                <tr><td>B6</td><td>JP (1%)</td><td><?= format_currency($item->b6_jp) ?></td></tr>
                <tr><td>B7</td><td>PPH 21</td><td><?= format_currency($item->b7_pph21) ?></td></tr>
                <tr><td>B8</td><td>Lain-lain</td><td><?= format_currency($item->b8_other) ?></td></tr>
                <tr class="slip-total-row"><td colspan="2">Total Potongan</td><td><?= format_currency($item->total_potongan) ?></td></tr>
              </tbody>
            </table>
          </div>
          <div class="slip-summary">
            <div class="head">RINGKASAN GAJI</div>
            <table>
              <tr><td>Total Penerimaan</td><td><?= format_currency($item->total_penerimaan) ?></td></tr>
              <tr><td>Total Potongan</td><td><?= format_currency($item->total_potongan) ?></td></tr>
              <tr class="take-home"><td>Gaji Bersih</td><td><?= format_currency($item->gaji_bersih) ?></td></tr>
              <tr><td>Pembulatan</td><td><?= format_currency($item->pembulatan) ?></td></tr>
            </table>
          </div>
        </td>
      </tr>
    </table>
  </div>
  <div class="slip-sign-fixed">
    <div>Mengetahui,</div>
    <div>HRD Manager</div>
    <div>PT. BERKAH CIPTA PERSADA</div>
  </div>
</body>
</html>
