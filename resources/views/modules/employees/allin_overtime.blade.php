@extends('layouts.app')

@section('content')
<h4 class="mb-3">Setting Lembur All-In</h4>

@if (request()->query('ok'))
  <div class="alert alert-success">Setting lembur All-In berhasil disimpan.</div>
@endif

@if (current_user_has_global_scope($user))
<form class="mb-3" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label">Company</label>
      <select class="form-select" name="set_company" onchange="this.form.submit()">
        @foreach ($companies as $c)
          <option value="{{ $c->id }}" {{ (int)$companyId === (int)$c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
        @endforeach
      </select>
    </div>
  </div>
</form>
@endif

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post">
      @csrf
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Gaji Pokok 6 jt - 7 jt</label>
          <input type="text" class="form-control js-currency" name="allin_ot_rate_6_7" value="{{ format_currency_id((float)($company->allin_ot_rate_6_7 ?? 30000), 2, false) }}">
        </div>
        <div class="col-md-6">
          <label class="form-label">Gaji Pokok 7 jt - 8 jt</label>
          <input type="text" class="form-control js-currency" name="allin_ot_rate_7_8" value="{{ format_currency_id((float)($company->allin_ot_rate_7_8 ?? 35000), 2, false) }}">
        </div>
        <div class="col-md-6">
          <label class="form-label">Gaji Pokok 8 jt - 9 jt</label>
          <input type="text" class="form-control js-currency" name="allin_ot_rate_8_9" value="{{ format_currency_id((float)($company->allin_ot_rate_8_9 ?? 40000), 2, false) }}">
        </div>
        <div class="col-md-6">
          <label class="form-label">Gaji Pokok 9 jt - 10 jt</label>
          <input type="text" class="form-control js-currency" name="allin_ot_rate_9_10" value="{{ format_currency_id((float)($company->allin_ot_rate_9_10 ?? 45000), 2, false) }}">
        </div>
        <div class="col-12">
          <div class="form-text">Gaji pokok di atas 10 jt tidak mendapatkan lembur.</div>
        </div>
      </div>
      <div class="d-flex gap-2 justify-content-end mt-3">
        <button class="btn btn-primary" type="submit">Simpan</button>
        <a class="btn btn-outline-secondary" href="{{ route('employees.index') }}">Kembali</a>
      </div>
    </form>
  </div>
</div>

<script>
  (function () {
    function parseIdNumber(value) {
      if (value === null || value === undefined) return 0;
      var cleaned = String(value).trim().replace(/[^0-9,.-]/g, '');
      if (!cleaned) return 0;
      var lastComma = cleaned.lastIndexOf(',');
      var lastDot = cleaned.lastIndexOf('.');
      var decimalSep = null;
      if (lastComma >= 0 || lastDot >= 0) {
        decimalSep = lastComma > lastDot ? ',' : '.';
      }
      if (decimalSep) {
        var intPart = cleaned;
        var decPart = '';
        var sepIndex = cleaned.lastIndexOf(decimalSep);
        if (sepIndex >= 0) {
          intPart = cleaned.substring(0, sepIndex);
          decPart = cleaned.substring(sepIndex + 1);
        }
        intPart = intPart.replace(/[.,]/g, '');
        decPart = decPart.replace(/[.,]/g, '');
        cleaned = intPart + (decPart ? '.' + decPart : '');
      } else {
        cleaned = cleaned.replace(/[.,]/g, '');
      }
      var num = parseFloat(cleaned);
      return isNaN(num) ? 0 : num;
    }

    function formatIdNumber(value) {
      var num = parseIdNumber(value);
      return num.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    document.querySelectorAll('.js-currency').forEach(function (el) {
      el.addEventListener('blur', function () {
        el.value = formatIdNumber(el.value);
      });
      el.value = formatIdNumber(el.value);
    });

    var form = document.querySelector('form[method="post"]');
    if (form) {
      form.addEventListener('submit', function () {
        document.querySelectorAll('.js-currency').forEach(function (input) {
          input.value = parseIdNumber(input.value).toString();
        });
      });
    }
  })();
</script>
@endsection
