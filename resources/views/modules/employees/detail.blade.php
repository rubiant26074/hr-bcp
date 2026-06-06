@extends('layouts.app')

@section('content')
@php
function contractNotesText($rawNotes) {
    if (!is_string($rawNotes) || $rawNotes === '') {
        return '';
    }
    $decoded = json_decode($rawNotes, true);
    if (!is_array($decoded)) {
        return $rawNotes;
    }
    return (string)($decoded['notes_text'] ?? '');
}
@endphp
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
  <h4 class="mb-0">Detail Karyawan</h4>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="{{ route('employees.index') }}">Back to List</a>
    <a class="btn btn-primary" href="{{ route('employees.form', ['edit' => $employee->id]) }}">Edit Employee</a>
  </div>
</div>
<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="row">
      <div class="col-md-3">
        @if (!empty($employee->photo_path))
          <img src="{{ asset_url($employee->photo_path) }}" alt="Pas Foto" class="img-thumbnail" style="width:100%; max-width:180px;">
        @else
          <div class="text-muted">Pas Foto belum diupload.</div>
        @endif
      </div>
      <div class="col-md-5">
        <div><strong>NIK:</strong> {{ $employee->nik }}</div>
        <div><strong>NIK Penduduk:</strong> {{ $employee->nik_ktp ?: '-' }}</div>
        <div><strong>Nama:</strong> {{ $employee->name }}</div>
        <div><strong>Tempat Lahir:</strong> {{ $employee->place_of_birth ?: '-' }}</div>
        <div><strong>Tanggal Lahir:</strong> {{ format_date_id($employee->date_of_birth) }}</div>
        <div><strong>Nomor HP:</strong> {{ $employee->phone ?: '-' }}</div>
        <div><strong>Telepon Kontak Darurat:</strong> {{ $employee->emergency_contact_number ?: '-' }}</div>
        <div><strong>Alamat KTP:</strong> {{ $employee->address_ktp ?: '-' }}</div>
        <div><strong>Alamat Domisili:</strong> {{ $employee->domicile_address ?: '-' }}</div>
        <div><strong>Perusahaan:</strong> {{ $employee->company_name }}</div>
        <div><strong>Departement:</strong> {{ $employee->department }}</div>
        <div><strong>Jabatan:</strong> {{ $employee->position }}</div>
        <div><strong>Golongan:</strong> {{ $employee->grade }}</div>
      </div>
      <div class="col-md-4">
        <div><strong>NPWP:</strong> {{ $employee->npwp }}</div>
        <div><strong>Bank:</strong> {{ $employee->bank_name ?: '-' }}</div>
        <div><strong>No. Rekening:</strong> {{ $employee->bank_account_no ?: '-' }}</div>
        <div><strong>PTKP:</strong> {{ $employee->ptkp_status }}</div>
        <div><strong>Status:</strong> {{ $employee->employment_status }}</div>
        <div><strong>Tipe:</strong> {{ $employee->employee_type }}</div>
        <div><strong>Join:</strong> {{ format_date_id($employee->join_date) }}</div>
        <div><strong>Habis Kontrak:</strong> {{ format_date_id($employee->contract_end) }}</div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <h6 class="mb-2">Dokumen</h6>
    <div class="row">
      <div class="col-md-6">
        <div><strong>KTP:</strong>
          @if (!empty($employee->ktp_path))
            <a class="doc-link" href="{{ asset_url($employee->ktp_path) }}" target="_blank">Lihat file</a>
          @else
            <span class="text-muted">-</span>
          @endif
        </div>
        <div><strong>Ijazah:</strong>
          @if (!empty($employee->ijazah_path))
            <a class="doc-link" href="{{ asset_url($employee->ijazah_path) }}" target="_blank">Lihat file</a>
          @else
            <span class="text-muted">-</span>
          @endif
        </div>
        <div><strong>Surat Lamaran:</strong>
          @if (!empty($employee->surat_lamaran_path))
            <a class="doc-link" href="{{ asset_url($employee->surat_lamaran_path) }}" target="_blank">Lihat file</a>
          @else
            <span class="text-muted">-</span>
          @endif
        </div>
        <div><strong>CV:</strong>
          @if (!empty($employee->cv_file_path))
            <a class="doc-link" href="{{ asset_url($employee->cv_file_path) }}" target="_blank">Lihat file</a>
          @else
            <span class="text-muted">-</span>
          @endif
        </div>
        <div><strong>MCU/Surat Sehat:</strong>
          @if (!empty($employee->mcu_file_path))
            <a class="doc-link" href="{{ asset_url($employee->mcu_file_path) }}" target="_blank">Lihat file</a>
          @else
            <span class="text-muted">-</span>
          @endif
        </div>
        <div><strong>KK:</strong>
          @if (!empty($employee->kk_path))
            <a class="doc-link" href="{{ asset_url($employee->kk_path) }}" target="_blank">Lihat file</a>
          @else
            <span class="text-muted">-</span>
          @endif
        </div>
      </div>
      <div class="col-md-6">
        <div><strong>NPWP:</strong>
          @if (!empty($employee->npwp_path))
            <a class="doc-link" href="{{ asset_url($employee->npwp_path) }}" target="_blank">Lihat file</a>
          @else
            <span class="text-muted">-</span>
          @endif
        </div>
        <div><strong>SKCK:</strong>
          @if (!empty($employee->skck_path))
            <a class="doc-link" href="{{ asset_url($employee->skck_path) }}" target="_blank">Lihat file</a>
          @else
            <span class="text-muted">-</span>
          @endif
        </div>
        <div><strong>Dokumen SDM:</strong>
          @if (!empty($documents ?? null) && count($documents) > 0)
            <div class="d-flex flex-column gap-1">
              @foreach ($documents as $doc)
                <a class="doc-link" href="{{ asset_url($doc->file_path) }}" target="_blank">{{ $doc->document_name }}</a>
              @endforeach
            </div>
          @else
            <span class="text-muted">-</span>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>

<h5>Riwayat Kontrak</h5>
<table class="table table-sm table-striped">
  <thead>
    <tr>
      <th>Type</th>
      <th>Start</th>
      <th>End</th>
      <th>Notes</th>
    </tr>
  </thead>
  <tbody>
    @foreach ($contracts as $c)
      <tr>
        <td>{{ $c->contract_type }}</td>
        <td>{{ format_date_id($c->start_date) }}</td>
        <td>{{ format_date_id($c->end_date) }}</td>
        <td>{{ contractNotesText($c->notes ?? '') }}</td>
      </tr>
    @endforeach
  </tbody>
</table>
@endsection
