@extends('layouts.app')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <h4 class="mb-0">{{ $edit ? 'Edit Dinas Luar' : 'Tambah Dinas Luar' }}</h4>
  <a class="btn btn-outline-secondary" href="{{ route('dinas_luar.index') }}">Kembali</a>
</div>

@if ($errors->any())
  @foreach ($errors->all() as $m)
    <div class="alert alert-danger">{{ $m }}</div>
  @endforeach
@endif

@php
  $amountReadonlyAttr = $canManageAmounts ? '' : 'readonly';
@endphp

<form method="post">
  @csrf
  <input type="hidden" name="id" value="{{ $edit->id ?? '' }}">

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Jenis Dinas</label>
          <select class="form-select" name="request_type" id="requestType" required>
            <option value="DLK" {{ old('request_type', $edit->request_type ?? 'DLK') === 'DLK' ? 'selected' : '' }}>DLK (Luar Kota)</option>
            <option value="DLN" {{ old('request_type', $edit->request_type ?? '') === 'DLN' ? 'selected' : '' }}>DLN (Luar Negeri)</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">No Dokumen</label>
          <input type="text" class="form-control" value="{{ old('doc_no', $edit->doc_no ?? 'Otomatis saat disimpan') }}" readonly>
          <div class="form-text">Format otomatis: `DLK-26 0300005` / `DLN-26 0300001`</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Tanggal</label>
          <input type="date" class="form-control" name="request_date" value="{{ old('request_date', $edit->request_date ?? '') }}">
        </div>
        <div class="col-md-3">
          <label class="form-label">Perpanjangan Ke</label>
          <input type="number" class="form-control" name="extension_no" min="0" value="{{ old('extension_no', $edit->extension_no ?? 0) }}">
        </div>
        <div class="col-md-4">
          <label class="form-label">Lama Pekerjaan (Mulai)</label>
          <input type="date" class="form-control" name="work_start" value="{{ old('work_start', $edit->work_start ?? '') }}">
        </div>
        <div class="col-md-4">
          <label class="form-label">Lama Pekerjaan (Selesai)</label>
          <input type="date" class="form-control" name="work_end" value="{{ old('work_end', $edit->work_end ?? '') }}">
        </div>
        <div class="col-md-4">
          <label class="form-label">Customer</label>
          <input type="text" class="form-control" name="customer" value="{{ old('customer', $edit->customer ?? '') }}">
        </div>
        <div class="col-md-4">
          <label class="form-label">No. WO</label>
          <input type="text" class="form-control" name="work_order_no" value="{{ old('work_order_no', $edit->work_order_no ?? '') }}">
        </div>
        <div class="col-md-4">
          <label class="form-label">Project</label>
          <input type="text" class="form-control" name="project" value="{{ old('project', $edit->project ?? '') }}">
        </div>
        <div class="col-md-4">
          <label class="form-label">Pekerjaan</label>
          <input type="text" class="form-control" name="pekerjaan" value="{{ old('pekerjaan', $edit->pekerjaan ?? '') }}">
        </div>
        <div class="col-md-4">
          <label class="form-label">Lokasi</label>
          <input type="text" class="form-control" name="lokasi" value="{{ old('lokasi', $edit->lokasi ?? '') }}">
        </div>
        <div class="col-md-4 dln-only">
          <label class="form-label">Negara</label>
          <input type="text" class="form-control" name="country" value="{{ old('country', $edit->country ?? '') }}">
        </div>
        <div class="col-md-4 dln-only">
          <label class="form-label">Kota</label>
          <input type="text" class="form-control" name="city" value="{{ old('city', $edit->city ?? '') }}">
        </div>
        <div class="col-md-4 dln-only">
          <label class="form-label">No Paspor</label>
          <input type="text" class="form-control" name="passport_no" value="{{ old('passport_no', $edit->passport_no ?? '') }}">
        </div>
        <div class="col-md-4 dln-only">
          <label class="form-label">Paspor Expired</label>
          <input type="date" class="form-control" name="passport_expiry" value="{{ old('passport_expiry', $edit->passport_expiry ?? '') }}">
        </div>
        <div class="col-md-4 dln-only">
          <label class="form-label">Currency</label>
          <input type="text" class="form-control" name="currency" value="{{ old('currency', $edit->currency ?? '') }}">
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <div class="fw-semibold mb-2">A. Biaya Lumpsum</div>
      @unless ($canManageAmounts)
        <div class="alert alert-info py-2 px-3 small">
          Kolom `Jumlah` diisi oleh HRD. Pengaju hanya mengisi item, hari, dan rincian kebutuhan.
        </div>
      @endunless
      <div class="table-responsive">
        <table class="table table-sm table-bordered" id="tableLumpsum">
          <thead>
            <tr>
              <th>Nama</th>
              <th>Hari</th>
              <th>Jumlah</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse ($lumpsums as $row)
              <tr>
                <td>
                  <input type="hidden" name="lumpsum_id[]" value="{{ $row->id }}">
                  <input type="text" class="form-control form-control-sm" name="lumpsum_name[]" value="{{ $row->name }}">
                </td>
                <td><input type="number" class="form-control form-control-sm" name="lumpsum_days[]" value="{{ $row->days }}"></td>
                <td>
                  <input type="number" step="0.01" class="form-control form-control-sm" name="lumpsum_amount[]" value="{{ $row->amount }}" {{ $amountReadonlyAttr }} placeholder="{{ $canManageAmounts ? '' : 'Diisi HRD' }}">
                </td>
                <td><button type="button" class="btn btn-outline-danger btn-sm btn-remove-row">Hapus</button></td>
              </tr>
            @empty
              <tr>
                <td>
                  <input type="hidden" name="lumpsum_id[]" value="">
                  <input type="text" class="form-control form-control-sm" name="lumpsum_name[]">
                </td>
                <td><input type="number" class="form-control form-control-sm" name="lumpsum_days[]" value="1"></td>
                <td>
                  <input type="number" step="0.01" class="form-control form-control-sm" name="lumpsum_amount[]" {{ $amountReadonlyAttr }} placeholder="{{ $canManageAmounts ? '' : 'Diisi HRD' }}">
                </td>
                <td><button type="button" class="btn btn-outline-danger btn-sm btn-remove-row">Hapus</button></td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="addLumpsum">Tambah Baris</button>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <div class="fw-semibold mb-2">B. Fasilitas</div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered" id="tableFacility">
          <thead>
            <tr>
              <th>Fasilitas</th>
              <th>Didanai</th>
              <th>Jumlah</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse ($facilities as $row)
              <tr>
                <td>
                  <input type="hidden" name="facility_id[]" value="{{ $row->id }}">
                  <input type="text" class="form-control form-control-sm" name="facility_name[]" value="{{ $row->name }}">
                </td>
                <td><input type="text" class="form-control form-control-sm" name="facility_funded[]" value="{{ $row->funded_by }}"></td>
                <td>
                  <input type="number" step="0.01" class="form-control form-control-sm" name="facility_amount[]" value="{{ $row->amount }}" {{ $amountReadonlyAttr }} placeholder="{{ $canManageAmounts ? '' : 'Diisi HRD' }}">
                </td>
                <td><button type="button" class="btn btn-outline-danger btn-sm btn-remove-row">Hapus</button></td>
              </tr>
            @empty
              <tr>
                <td>
                  <input type="hidden" name="facility_id[]" value="">
                  <input type="text" class="form-control form-control-sm" name="facility_name[]">
                </td>
                <td><input type="text" class="form-control form-control-sm" name="facility_funded[]"></td>
                <td>
                  <input type="number" step="0.01" class="form-control form-control-sm" name="facility_amount[]" {{ $amountReadonlyAttr }} placeholder="{{ $canManageAmounts ? '' : 'Diisi HRD' }}">
                </td>
                <td><button type="button" class="btn btn-outline-danger btn-sm btn-remove-row">Hapus</button></td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="addFacility">Tambah Baris</button>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <div class="fw-semibold mb-2">C. Lain-lain</div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered" id="tableOther">
          <thead>
            <tr>
              <th>Nama</th>
              <th>Jumlah</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse ($others as $row)
              <tr>
                <td>
                  <input type="hidden" name="other_id[]" value="{{ $row->id }}">
                  <input type="text" class="form-control form-control-sm" name="other_name[]" value="{{ $row->name }}">
                </td>
                <td>
                  <input type="number" step="0.01" class="form-control form-control-sm" name="other_amount[]" value="{{ $row->amount }}" {{ $amountReadonlyAttr }} placeholder="{{ $canManageAmounts ? '' : 'Diisi HRD' }}">
                </td>
                <td><button type="button" class="btn btn-outline-danger btn-sm btn-remove-row">Hapus</button></td>
              </tr>
            @empty
              <tr>
                <td>
                  <input type="hidden" name="other_id[]" value="">
                  <input type="text" class="form-control form-control-sm" name="other_name[]">
                </td>
                <td>
                  <input type="number" step="0.01" class="form-control form-control-sm" name="other_amount[]" {{ $amountReadonlyAttr }} placeholder="{{ $canManageAmounts ? '' : 'Diisi HRD' }}">
                </td>
                <td><button type="button" class="btn btn-outline-danger btn-sm btn-remove-row">Hapus</button></td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="addOther">Tambah Baris</button>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <label class="form-label">Catatan</label>
      <textarea class="form-control" name="notes" rows="3">{{ old('notes', $edit->notes ?? '') }}</textarea>
    </div>
  </div>

  <div class="d-flex gap-2 justify-content-end">
    <button class="btn btn-primary" type="submit">Simpan</button>
    <a class="btn btn-outline-secondary" href="{{ route('dinas_luar.index') }}">Batal</a>
  </div>
</form>

<script>
  (function () {
    function addRow(tableId, tpl) {
      var tbody = document.querySelector(tableId + ' tbody');
      if (!tbody) return;
      var tr = document.createElement('tr');
      tr.innerHTML = tpl;
      tbody.appendChild(tr);
    }

    function bindRemove(container) {
      container.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-remove-row');
        if (!btn) return;
        var tr = btn.closest('tr');
        if (tr) tr.parentNode.removeChild(tr);
      });
    }

    var amountReadonlyAttr = @json($canManageAmounts ? '' : 'readonly');
    var amountPlaceholderAttr = @json($canManageAmounts ? '' : ' placeholder="Diisi HRD"');

    var tplLumpsum = '<td><input type="hidden" name="lumpsum_id[]" value=""><input type="text" class="form-control form-control-sm" name="lumpsum_name[]"></td>' +
      '<td><input type="number" class="form-control form-control-sm" name="lumpsum_days[]" value="1"></td>' +
      '<td><input type="number" step="0.01" class="form-control form-control-sm" name="lumpsum_amount[]" ' + amountReadonlyAttr + amountPlaceholderAttr + '></td>' +
      '<td><button type="button" class="btn btn-outline-danger btn-sm btn-remove-row">Hapus</button></td>';
    var tplFacility = '<td><input type="hidden" name="facility_id[]" value=""><input type="text" class="form-control form-control-sm" name="facility_name[]"></td>' +
      '<td><input type="text" class="form-control form-control-sm" name="facility_funded[]"></td>' +
      '<td><input type="number" step="0.01" class="form-control form-control-sm" name="facility_amount[]" ' + amountReadonlyAttr + amountPlaceholderAttr + '></td>' +
      '<td><button type="button" class="btn btn-outline-danger btn-sm btn-remove-row">Hapus</button></td>';
    var tplOther = '<td><input type="hidden" name="other_id[]" value=""><input type="text" class="form-control form-control-sm" name="other_name[]"></td>' +
      '<td><input type="number" step="0.01" class="form-control form-control-sm" name="other_amount[]" ' + amountReadonlyAttr + amountPlaceholderAttr + '></td>' +
      '<td><button type="button" class="btn btn-outline-danger btn-sm btn-remove-row">Hapus</button></td>';

    var btnAddLumpsum = document.getElementById('addLumpsum');
    var btnAddFacility = document.getElementById('addFacility');
    var btnAddOther = document.getElementById('addOther');

    if (btnAddLumpsum) btnAddLumpsum.addEventListener('click', function () { addRow('#tableLumpsum', tplLumpsum); });
    if (btnAddFacility) btnAddFacility.addEventListener('click', function () { addRow('#tableFacility', tplFacility); });
    if (btnAddOther) btnAddOther.addEventListener('click', function () { addRow('#tableOther', tplOther); });

    bindRemove(document.getElementById('tableLumpsum'));
    bindRemove(document.getElementById('tableFacility'));
    bindRemove(document.getElementById('tableOther'));

    function toggleDln() {
      var type = document.getElementById('requestType');
      var dlnFields = document.querySelectorAll('.dln-only');
      var show = type && type.value === 'DLN';
      dlnFields.forEach(function (el) {
        el.style.display = show ? '' : 'none';
      });
    }
    var typeSelect = document.getElementById('requestType');
    if (typeSelect) {
      typeSelect.addEventListener('change', toggleDln);
      toggleDln();
    }
  })();
</script>
@endsection
