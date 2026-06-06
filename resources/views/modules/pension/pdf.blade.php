<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Daftar Pensiun</title>
  <style>
    @page { size: A4; margin: 16mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
    h2 { margin: 0 0 6px; font-size: 14px; }
    .meta { margin-bottom: 10px; font-size: 9px; color: #555; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #333; padding: 4px 6px; }
    th { background: #f2f2f2; text-align: left; }
  </style>
</head>
<body>
  <h2>Daftar Pensiun - {{ $company->company_name ?? 'Company' }}</h2>
  <div class="meta">
    Dicetak: {{ date('d/m/Y H:i') }}
    @php
      $filters = [];
      if ($ageMin !== null) $filters[] = 'Usia min: ' . $ageMin;
      if ($ageMax !== null) $filters[] = 'Usia max: ' . $ageMax;
      if ($retireYear !== null) $filters[] = 'Tahun pensiun: ' . $retireYear;
      if (($retirementMethod ?? 'government') === 'company_policy') {
          $filters[] = 'Metode: Kebijakan perusahaan';
          $filters[] = 'Usia pensiun: ' . ($customRetireAge ?? 55) . ' tahun';
      } else {
          $filters[] = 'Metode: Peraturan Pemerintah';
      }
    @endphp
    @if (!empty($filters))
      <div>Filter: {{ implode(', ', $filters) }}</div>
    @endif
  </div>
  <table>
    <thead>
      <tr>
        <th>NIK</th>
        <th>Nama</th>
        <th>Tgl Lahir</th>
        <th>Usia</th>
        <th>Usia Pensiun</th>
        <th>Tahun Pensiun</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($rows as $row)
        @php $e = $row['employee']; @endphp
        <tr>
          <td>{{ $e->nik }}</td>
          <td>{{ $e->name }}</td>
          <td>{{ format_date_id($e->date_of_birth) }}</td>
          <td>{{ $row['age'] !== null ? $row['age'] . ' th' : '-' }}</td>
          <td>{{ $row['retire_age'] !== null ? $row['retire_age'] . ' th' : '-' }}</td>
          <td>{{ $row['retire_year'] ?? '-' }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="6">Tidak ada data.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
