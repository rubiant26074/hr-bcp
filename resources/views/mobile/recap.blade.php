@extends('mobile.layout')

@php($showNav = true)
@php($activeTab = 'recap')

@section('content')
<h5 class="fw-bold mb-3">Rekap Absensi</h5>

<div class="card card-clean mb-3">
  <div class="card-body">
    <form class="row g-2" method="get" id="recapForm">
      <div class="col-12">
        <select class="form-select" name="mode" id="modeSelect">
          <option value="cutoff" {{ ($mode ?? 'cutoff') === 'cutoff' ? 'selected' : '' }}>Cut-off 20-21</option>
          <option value="date_range" {{ ($mode ?? 'cutoff') === 'date_range' ? 'selected' : '' }}>Rentang Tanggal</option>
        </select>
      </div>

      <div id="cutoffFields" class="contents">
        <div class="col-6">
          <select class="form-select" name="month">
            @for ($m = 1; $m <= 12; $m++)
              <option value="{{ $m }}" {{ (int)$month === $m ? 'selected' : '' }}>{{ sprintf('%02d', $m) }}</option>
            @endfor
          </select>
        </div>
        <div class="col-4">
          <input type="number" class="form-control" name="year" value="{{ $year }}">
        </div>
      </div>

      <div id="rangeFields" class="contents">
        <div class="col-5">
          <input type="date" class="form-control" name="start_date" value="{{ $startDateInput ?? '' }}">
        </div>
        <div class="col-5">
          <input type="date" class="form-control" name="end_date" value="{{ $endDateInput ?? '' }}">
        </div>
      </div>

      <div class="col-2">
        <button class="btn btn-dark w-100" type="submit">OK</button>
      </div>
    </form>
    <div class="small text-muted mt-2">
      Periode: {{ \Carbon\Carbon::parse($start)->format('d M Y') }} - {{ \Carbon\Carbon::parse($end)->format('d M Y') }}
    </div>
    <div class="small text-muted mt-1">Hari tercatat: {{ $summary['hari_tercatat'] }} | Jam kerja: {{ number_format((float)$summary['total_jam_kerja'], 2) }} | Lembur: {{ number_format((float)$summary['total_lembur'], 2) }}</div>
    <div class="small text-secondary mt-1">
      <span class="badge text-bg-danger me-1">TA-IL</span>: Tidak Ada Izin Lembur
    </div>
  </div>
</div>

<div class="card card-clean">
  <div class="card-body">
    @if ($rows->isEmpty())
      <div class="text-muted small">Belum ada data rekap pada periode ini.</div>
    @else
      @foreach ($rows as $row)
        <div class="py-2 border-bottom small">
          <div class="fw-semibold">{{ date('d M Y', strtotime((string) $row->date)) }}</div>
          <div class="text-muted">Masuk: {{ $row->check_in ? date('H:i', strtotime((string) $row->check_in)) : '-' }} | Pulang: {{ $row->check_out ? date('H:i', strtotime((string) $row->check_out)) : '-' }}</div>
          <div class="text-muted">
            Jam kerja: {{ number_format((float)($row->work_hours ?? 0), 2) }} |
            Lembur: {{ number_format((float)($row->overtime_hours ?? 0), 2) }}
            @if ((int)($row->no_overtime_permit ?? 0) === 1)
              <span class="badge text-bg-danger ms-1">TA-IL</span>
            @endif
          </div>
        </div>
      @endforeach
    @endif
  </div>
</div>
<script>
  (function () {
    var mode = document.getElementById('modeSelect');
    var cutoff = document.getElementById('cutoffFields');
    var range = document.getElementById('rangeFields');
    if (!mode || !cutoff || !range) return;

    function syncMode() {
      var isCutoff = mode.value === 'cutoff';
      cutoff.style.display = isCutoff ? 'contents' : 'none';
      range.style.display = isCutoff ? 'none' : 'contents';
    }
    mode.addEventListener('change', syncMode);
    syncMode();
  })();
</script>
@endsection
