@extends('layouts.app')

@section('content')
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
  <h4 class="mb-0 text-center text-md-start">{{ $edit ? 'Edit Company' : 'Add Company' }}</h4>
  <a class="btn btn-outline-secondary align-self-center align-self-md-auto" href="{{ route('company.index') }}">Back to List</a>
</div>

<div class="row justify-content-center g-3">
  <div class="col-12 col-md-10 col-lg-8">
    @foreach ($messages as $m)
      <div class="alert alert-info">{{ $m }}</div>
    @endforeach

    <div class="card shadow-sm">
      <div class="card-body">
        @php
          $dayOptions = [
            'Mon' => 'Senin',
            'Tue' => 'Selasa',
            'Wed' => 'Rabu',
            'Thu' => 'Kamis',
            'Fri' => 'Jumat',
            'Sat' => 'Sabtu',
            'Sun' => 'Minggu',
          ];
          $selectedDays = old('work_days', $edit && $edit->work_days_json ? json_decode($edit->work_days_json, true) : ['Mon','Tue','Wed','Thu','Fri']);
          if (!is_array($selectedDays)) {
            $selectedDays = [];
          }
        @endphp
        <form method="post" enctype="multipart/form-data">
          @csrf
          <input type="hidden" name="id" value="{{ $edit->id ?? '' }}">

          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label">Company Name</label>
              <input type="text" class="form-control" name="company_name" value="{{ old('company_name', $edit->company_name ?? '') }}" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Company Code</label>
              <input type="text" class="form-control" name="company_code" value="{{ old('company_code', $edit->company_code ?? '') }}" required>
            </div>
            <div class="col-12">
              <label class="form-label">Address</label>
              <input type="text" class="form-control" name="address" value="{{ old('address', $edit->address ?? '') }}">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">NPWP</label>
              <input type="text" class="form-control" name="npwp" value="{{ old('npwp', $edit->npwp ?? '') }}">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Nama Bank</label>
              <input type="text" class="form-control" name="bank_name" value="{{ old('bank_name', $edit->bank_name ?? '') }}" placeholder="Contoh: BNI / BSI">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">No. Rekening Debet Perusahaan</label>
              <input type="text" class="form-control" name="bank_debit_account_no" value="{{ old('bank_debit_account_no', $edit->bank_debit_account_no ?? '') }}" placeholder="Nomor rekening perusahaan">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Logo (JPG/PNG)</label>
              <input type="file" class="form-control" name="logo" accept=".jpg,.jpeg,.png">
              @if (!empty($edit->logo_path))
                <div class="form-text">Current: <img src="{{ asset_url($edit->logo_path) }}" alt="logo" style="height:24px"></div>
              @endif
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Payroll Day</label>
              <input type="number" min="1" max="31" class="form-control" name="payroll_day" value="{{ old('payroll_day', $edit->payroll_day ?? 25) }}">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">BPJS Kesehatan (%)</label>
              <input type="number" step="0.01" class="form-control" name="bpjs_health_pct" value="{{ old('bpjs_health_pct', $edit->bpjs_health_pct ?? 1) }}">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">JHT (%)</label>
              <input type="number" step="0.01" class="form-control" name="bpjs_jht_pct" value="{{ old('bpjs_jht_pct', $edit->bpjs_jht_pct ?? 2) }}">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">JP (%)</label>
              <input type="number" step="0.01" class="form-control" name="bpjs_jp_pct" value="{{ old('bpjs_jp_pct', $edit->bpjs_jp_pct ?? 1) }}">
            </div>

            <div class="col-12">
              <hr class="my-2">
              <h6 class="mb-1">Aturan Jam Kerja</h6>
              <div class="form-text">Digunakan untuk perhitungan absensi dan lembur.</div>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Jumlah Hari / Minggu</label>
              <input type="number" min="1" max="7" class="form-control" name="work_days_per_week" value="{{ old('work_days_per_week', $edit->work_days_per_week ?? 5) }}">
            </div>
            @php
              $workTimeStart = old('work_time_start', $edit->work_time_start ?? '');
              if (is_string($workTimeStart) && strlen($workTimeStart) >= 5) {
                $workTimeStart = substr($workTimeStart, 0, 5);
              }
              $workTimeEnd = old('work_time_end', $edit->work_time_end ?? '');
              if (is_string($workTimeEnd) && strlen($workTimeEnd) >= 5) {
                $workTimeEnd = substr($workTimeEnd, 0, 5);
              }
            @endphp
            <div class="col-12 col-md-4">
              <label class="form-label">Jam Kerja Masuk</label>
              <div class="input-group">
                <input id="workTimeStart" type="text" class="form-control js-time-24" name="work_time_start" value="{{ $workTimeStart }}" placeholder="HH:MM" inputmode="numeric" pattern="^([01]\d|2[0-3]):[0-5]\d$" title="Format 24 jam (HH:MM)">
                <button class="btn btn-outline-secondary js-time-picker-btn" type="button" data-target="#workTimeStart" aria-label="Pilih jam">
                  <span class="icon-clock" aria-hidden="true"></span>
                </button>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Jam Kerja Keluar</label>
              <div class="input-group">
                <input id="workTimeEnd" type="text" class="form-control js-time-24" name="work_time_end" value="{{ $workTimeEnd }}" placeholder="HH:MM" inputmode="numeric" pattern="^([01]\d|2[0-3]):[0-5]\d$" title="Format 24 jam (HH:MM)">
                <button class="btn btn-outline-secondary js-time-picker-btn" type="button" data-target="#workTimeEnd" aria-label="Pilih jam">
                  <span class="icon-clock" aria-hidden="true"></span>
                </button>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Durasi Kerja (Jam)</label>
              <input type="number" min="0.5" max="24" step="0.25" class="form-control" name="work_duration_hours" value="{{ old('work_duration_hours', $edit->work_duration_hours ?? 8) }}">
            </div>
            <div class="col-12 col-md-8">
              <label class="form-label">Hari Kerja</label>
              <div class="d-flex flex-wrap gap-2">
                @foreach ($dayOptions as $dayKey => $dayLabel)
                  <label class="btn btn-outline-secondary btn-sm">
                    <input type="checkbox" name="work_days[]" value="{{ $dayKey }}" class="form-check-input me-1" {{ in_array($dayKey, $selectedDays, true) ? 'checked' : '' }}>
                    {{ $dayLabel }}
                  </label>
                @endforeach
              </div>
            </div>
          </div>

          <div class="mt-3 d-grid d-sm-flex gap-2 justify-content-sm-end">
            <button class="btn btn-primary" type="submit">{{ $edit ? 'Update' : 'Save' }}</button>
            <a class="btn btn-outline-secondary" href="{{ route('company.index') }}">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var dayChecks = document.querySelectorAll('input[name="work_days[]"]');
  var daysInput = document.querySelector('input[name="work_days_per_week"]');
  var startInput = document.querySelector('input[name="work_time_start"]');
  var endInput = document.querySelector('input[name="work_time_end"]');
  var durationInput = document.querySelector('input[name="work_duration_hours"]');

  function updateDays() {
    if (!daysInput) return;
    var count = 0;
    dayChecks.forEach(function (c) {
      if (c.checked) count++;
    });
    if (count > 0) {
      daysInput.value = count;
    }
  }

  function updateDuration() {
    if (!startInput || !endInput || !durationInput) return;
    if (!startInput.value || !endInput.value) return;
    var s = startInput.value.split(':');
    var e = endInput.value.split(':');
    if (s.length < 2 || e.length < 2) return;
    var sm = parseInt(s[0], 10) * 60 + parseInt(s[1], 10);
    var em = parseInt(e[0], 10) * 60 + parseInt(e[1], 10);
    var diff = em - sm;
    if (diff <= 0) return;
    durationInput.value = (diff / 60).toFixed(2);
  }

  dayChecks.forEach(function (c) {
    c.addEventListener('change', updateDays);
  });
  if (startInput) startInput.addEventListener('change', updateDuration);
  if (endInput) endInput.addEventListener('change', updateDuration);
});
</script>
@endsection
