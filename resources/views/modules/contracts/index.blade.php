@extends('layouts.app')

@section('content')
@php
function parseContractNotes($rawNotes) {
    $defaults = [
        'kontrak_terahir' => '',
        'kontrak_1' => '',
        'kotrak_2' => '',
        'rehat' => '',
        'kontrak_1_lanjutan' => '',
        'kotrak_2_lanjutan' => '',
    ];

    if (!is_string($rawNotes) || $rawNotes === '') {
        return ['masa_kontrak' => $defaults, 'notes_text' => ''];
    }

    $decoded = json_decode($rawNotes, true);
    if (!is_array($decoded)) {
        return ['masa_kontrak' => $defaults, 'notes_text' => $rawNotes];
    }

    $masaKontrak = $decoded['masa_kontrak'] ?? [];
    if (!is_array($masaKontrak)) {
        $masaKontrak = [];
    }

    return [
        'masa_kontrak' => array_merge($defaults, $masaKontrak),
        'notes_text' => (string)($decoded['notes_text'] ?? ''),
    ];
}
@endphp

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
  <h4 class="mb-0">Contracts</h4>
  <a class="btn btn-primary" href="{{ route('contracts.form') }}">Add Contract</a>
</div>

@foreach ($messages as $m)
  <div class="alert alert-info">{{ $m }}</div>
@endforeach

@if (current_user_has_global_scope($user))
<form class="mb-3" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label">Company</label>
      <select class="form-select" name="set_company" onchange="this.form.submit()">
        @foreach ($companies as $c)
          <option value="{{ $c->id }}" {{ $companyId == $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
        @endforeach
      </select>
    </div>
  </div>
</form>
@endif

<form id="bulk-delete-form" method="post" class="mb-2 d-flex justify-content-end gap-2" onsubmit="return confirm('Hapus semua contract terpilih?');">
  @csrf
  <input type="hidden" name="action" value="bulk_delete">
  <button class="btn btn-danger btn-sm" type="submit">Delete Selected</button>
</form>

<div class="card shadow-sm">
  <div class="card-body">
    <table class="table table-striped table-sm">
      <thead>
        <tr>
          <th style="width:32px;">
            <input type="checkbox" id="check-all-contracts">
          </th>
          <th>Employee</th>
          <th>Type</th>
          <th>Start</th>
          <th>End</th>
          <th>Kontrak Terahir</th>
          <th>Kontrak I</th>
          <th>Kotrak II</th>
          <th>Rehat</th>
          <th>Kontrak I</th>
          <th>Kotrak II</th>
          <th>Notes</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($contractsActive as $c)
            @php $parsed = parseContractNotes($c->notes ?? ''); @endphp
            @php $masa = $parsed['masa_kontrak']; @endphp
            <tr>
              <td>
                <input type="checkbox" class="contract-check" form="bulk-delete-form" name="delete_ids[]" value="{{ $c->id }}">
              </td>
              <td>{{ $c->employee?->name ?? '-' }}</td>
              <td>{{ $c->contract_type }}</td>
              <td>{{ format_date_id($c->start_date) }}</td>
              <td>{{ format_date_id($c->end_date) }}</td>
              <td>{{ format_date_id($masa['kontrak_terahir']) }}</td>
              <td>{{ format_date_id($masa['kontrak_1']) }}</td>
              <td>{{ format_date_id($masa['kotrak_2']) }}</td>
              <td>{{ format_date_id($masa['rehat']) }}</td>
              <td>{{ format_date_id($masa['kontrak_1_lanjutan']) }}</td>
              <td>{{ format_date_id($masa['kotrak_2_lanjutan']) }}</td>
              <td>{{ $parsed['notes_text'] }}</td>
              <td class="text-end">
                <a class="icon-btn icon-edit" title="Edit" href="{{ route('contracts.form', ['id' => $c->id]) }}">
                  <span class="icon i-edit" aria-hidden="true"></span>
                </a>
                <form method="post" class="d-inline" onsubmit="return confirm('Hapus contract ini?');">
                  @csrf
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="{{ $c->id }}">
                  <button class="icon-btn icon-delete" title="Delete" type="submit">
                    <span class="icon i-trash" aria-hidden="true"></span>
                  </button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="13" class="text-muted">Tidak ada kontrak aktif.</td>
            </tr>
          @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="card shadow-sm mt-3">
  <div class="card-body">
    <div class="fw-semibold mb-2">Arsip Kontrak (Karyawan Tetap)</div>
    <table class="table table-striped table-sm">
      <thead>
        <tr>
          <th>Employee</th>
          <th>Type</th>
          <th>Start</th>
          <th>End</th>
          <th>Kontrak Terahir</th>
          <th>Kontrak I</th>
          <th>Kotrak II</th>
          <th>Rehat</th>
          <th>Kontrak I</th>
          <th>Kotrak II</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($contractsArchive as $c)
          @php $parsed = parseContractNotes($c->notes ?? ''); @endphp
          @php $masa = $parsed['masa_kontrak']; @endphp
          <tr>
            <td>{{ $c->employee?->name ?? '-' }}</td>
            <td>{{ $c->contract_type }}</td>
            <td>{{ format_date_id($c->start_date) }}</td>
            <td>{{ format_date_id($c->end_date) }}</td>
            <td>{{ format_date_id($masa['kontrak_terahir']) }}</td>
            <td>{{ format_date_id($masa['kontrak_1']) }}</td>
            <td>{{ format_date_id($masa['kotrak_2']) }}</td>
            <td>{{ format_date_id($masa['rehat']) }}</td>
            <td>{{ format_date_id($masa['kontrak_1_lanjutan']) }}</td>
            <td>{{ format_date_id($masa['kotrak_2_lanjutan']) }}</td>
            <td>{{ $parsed['notes_text'] }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="11" class="text-muted">Belum ada arsip kontrak.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<script>
const checkAllContracts = document.getElementById('check-all-contracts');
if (checkAllContracts) {
  checkAllContracts.addEventListener('change', function () {
    document.querySelectorAll('.contract-check').forEach(function (cb) {
      cb.checked = checkAllContracts.checked;
    });
  });
}
</script>
@endsection
