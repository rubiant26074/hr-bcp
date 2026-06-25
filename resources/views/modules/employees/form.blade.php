@extends('layouts.app')

@section('content')
<h4 class="mb-3">Form Employee</h4>
<style>
  .form-text:empty { display: none; }
</style>

<div class="card shadow-sm">
  <div class="card-body pb-0">
    <form method="post" enctype="multipart/form-data" id="employeeForm">
      @csrf
      <input type="hidden" name="view" value="{{ request('view', session('employees_view_mode', 'active_tetap')) }}">
      @php
        $canProfile = $canProfile ?? true;
        $canPayroll = $canPayroll ?? true;
        $activeTab = $canProfile ? 'profile' : 'payroll';
        $resourceCompanyId = (int) (collect($companies ?? [])->first(function ($c) {
          return mb_strtolower(trim((string) ($c->company_name ?? ''))) === 'pt. resource mitra bersama';
        })->id ?? 0);
        $currentFormCompanyId = (int) old('company_id', $companyId ?? 0);
        $showPlacementField = $resourceCompanyId > 0 && $currentFormCompanyId === $resourceCompanyId;
        $placementCompanyIdVal = old('placement_company_id', $edit->placement_company_id ?? '');
      @endphp
      <input type="hidden" name="id" value="{{ $edit->id ?? '' }}">
      <input type="hidden" name="draft_photo_path" id="draft_photo_path" value="">
      <input type="hidden" name="current_photo_path" id="current_photo_path" value="{{ $edit->photo_path ?? '' }}">
      <input type="hidden" name="draft_ktp_path" id="draft_ktp_path" value="">
      <input type="hidden" name="current_ktp_path" id="current_ktp_path" value="{{ $edit->ktp_path ?? '' }}">
      <input type="hidden" name="draft_ijazah_path" id="draft_ijazah_path" value="">
      <input type="hidden" name="current_ijazah_path" id="current_ijazah_path" value="{{ $edit->ijazah_path ?? '' }}">
      <input type="hidden" name="draft_surat_lamaran_path" id="draft_surat_lamaran_path" value="">
      <input type="hidden" name="current_surat_lamaran_path" id="current_surat_lamaran_path" value="{{ $edit->surat_lamaran_path ?? '' }}">
      <input type="hidden" name="draft_cv_file_path" id="draft_cv_file_path" value="">
      <input type="hidden" name="current_cv_file_path" id="current_cv_file_path" value="{{ $edit->cv_file_path ?? '' }}">
      <input type="hidden" name="draft_mcu_file_path" id="draft_mcu_file_path" value="">
      <input type="hidden" name="current_mcu_file_path" id="current_mcu_file_path" value="{{ $edit->mcu_file_path ?? '' }}">
      <input type="hidden" name="draft_kk_path" id="draft_kk_path" value="">
      <input type="hidden" name="current_kk_path" id="current_kk_path" value="{{ $edit->kk_path ?? '' }}">
      <input type="hidden" name="draft_npwp_path" id="draft_npwp_path" value="">
      <input type="hidden" name="current_npwp_path" id="current_npwp_path" value="{{ $edit->npwp_path ?? '' }}">
      <input type="hidden" name="draft_skck_path" id="draft_skck_path" value="">
      <input type="hidden" name="current_skck_path" id="current_skck_path" value="{{ $edit->skck_path ?? '' }}">
      <input type="hidden" name="mutasi_to_company_id" id="mutasi_to_company_id" value="{{ old('mutasi_to_company_id', '') }}">
      <input type="hidden" name="mutasi_note" id="mutasi_note" value="{{ old('mutasi_note', '') }}">

      <ul class="nav nav-tabs" id="empFormTabs" role="tablist">
        @if ($canProfile)
        <li class="nav-item" role="presentation">
          <button class="nav-link {{ $activeTab === 'profile' ? 'active' : '' }}" id="tab-profile-tab" data-bs-toggle="tab" data-bs-target="#tab-profile" type="button" role="tab">Data Karyawan</button>
        </li>
        @endif
        @if ($canPayroll)
        <li class="nav-item" role="presentation">
          <button class="nav-link {{ $activeTab === 'payroll' ? 'active' : '' }}" id="tab-payroll-tab" data-bs-toggle="tab" data-bs-target="#tab-payroll" type="button" role="tab">Payroll Settings</button>
        </li>
        @endif
      </ul>

      <div class="tab-content pt-3">
        @if ($canProfile)
        <div class="tab-pane fade {{ $activeTab === 'profile' ? 'show active' : '' }}" id="tab-profile" role="tabpanel">
      <div class="row gy-2" style="--bs-gutter-x:10px;">
        @if (current_user_has_global_scope($user))
        <div class="col-12">
          <label class="form-label">Company</label>
          <select class="form-select" name="company_id" id="company_id" required>
            @foreach ($companies as $c)
              <option value="{{ $c->id }}" {{ $companyId == $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
            @endforeach
          </select>
        </div>
        @endif

        <div class="col-md-6 {{ $showPlacementField ? '' : 'd-none' }}" id="placement_company_wrap" data-resource-company-id="{{ $resourceCompanyId }}">
          <label class="form-label">Penempatan</label>
          <select class="form-select" name="placement_company_id" id="placement_company_id">
            <option value="">Pilih Penempatan</option>
            @foreach (($companies ?? []) as $c)
              <option value="{{ $c->id }}" {{ (string) $placementCompanyIdVal === (string) $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
            @endforeach
          </select>
          <div class="form-text">Khusus untuk entitas PT. Resource Mitra Bersama.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label">NIK (Nomor Induk Pegawai)</label>
          <input type="text" class="form-control" name="nik" value="{{ $edit->nik ?? '' }}" readonly>
          <div class="form-text">Otomatis dari perusahaan & tanggal join.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">NIK (Nomor Induk Kependudukan)</label>
          <input type="text" class="form-control" name="nik_ktp" value="{{ $edit->nik_ktp ?? '' }}">
        </div>
        <div class="col-12">
          <label class="form-label">Alamat KTP</label>
          <textarea class="form-control" name="address_ktp" id="address_ktp" rows="2">{{ old('address_ktp', $edit->address_ktp ?? '') }}</textarea>
          <div class="form-text">Akan diisi otomatis dari upload KTP gambar jika OCR Windows berhasil. Tetap bisa diedit manual.</div>
          <div class="form-text">
            <button class="btn btn-sm btn-outline-secondary mt-1" type="button" id="btnReadStoredKtp">Baca dari KTP tersimpan</button>
            <button class="btn btn-sm btn-outline-secondary mt-1 ms-1" type="button" id="btnForceOcrKtp">Paksa OCR ulang</button>
          </div>
          <div class="form-text" id="ktp_ocr_hint"></div>
        </div>
        <div class="col-12">
          <label class="form-label">Alamat Domisili</label>
          <textarea class="form-control" name="domicile_address" rows="2">{{ old('domicile_address', $edit->domicile_address ?? '') }}</textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label">Nama</label>
          <input type="text" class="form-control" name="name" value="{{ $edit->name ?? '' }}" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Status Aktif</label>
          @php $activeVal = old('active_status', $edit->active_status ?? 'Active'); @endphp
          <select class="form-select" name="active_status" id="active_status">
            @foreach (($activeStatusOptions ?? ['Active','Non Active','Dalam Proses Resign','Mutasi','Resign','PHK','Habis Kontrak']) as $s)
              <option value="{{ $s }}" {{ $activeVal === $s ? 'selected' : '' }}>{{ $s }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Tempat Lahir</label>
          <input type="text" class="form-control" name="place_of_birth" value="{{ old('place_of_birth', $edit->place_of_birth ?? '') }}" placeholder="Contoh: Jakarta">
        </div>
        <div class="col-md-6">
          <label class="form-label">Tanggal Lahir</label>
          <input type="date" class="form-control" name="date_of_birth" value="{{ old('date_of_birth', $edit->date_of_birth ?? '') }}">
        </div>
        <div class="col-md-6">
          <label class="form-label">Nomor HP</label>
          <input type="text" class="form-control" name="phone" value="{{ $edit->phone ?? '' }}" placeholder="08xxxxxxxxxx">
        </div>
        <div class="col-md-6">
          <label class="form-label">Nomor Tlp Saudara/Famili</label>
          <input type="text" class="form-control" name="emergency_contact_number" value="{{ $edit->emergency_contact_number ?? '' }}" placeholder="Nomor kontak darurat">
        </div>
        <div class="col-md-6">
          <label class="form-label">Nama Bank</label>
          <input type="text" class="form-control" name="bank_name" value="{{ $edit->bank_name ?? '' }}" placeholder="Contoh: BCA">
        </div>
        <div class="col-md-6">
          <label class="form-label">NPWP</label>
          <input type="text" class="form-control" name="npwp" value="{{ $edit->npwp ?? '' }}">
        </div>
        <div class="col-md-6">
          <label class="form-label">Nomor Rekening</label>
          <input type="text" class="form-control" name="bank_account_no" value="{{ $edit->bank_account_no ?? '' }}" placeholder="Nomor rekening">
        </div>
        <div class="col-md-6">
          <label class="form-label">PTKP Status</label>
          @php $ptkpVal = old('ptkp_status', $edit->ptkp_status ?? 'TK/0'); @endphp
          <select class="form-select" name="ptkp_status" id="ptkp_status" required>
            @foreach (['TK/0','TK/1','TK/2','TK/3','K/0','K/1','K/2','K/3','HB/0','HB/1','HB/2','HB/3'] as $s)
              <option value="{{ $s }}" {{ $ptkpVal === $s ? 'selected' : '' }}>{{ $s }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Status Karyawan</label>
          @php $statusVal = old('employment_status', $edit->employment_status ?? ''); @endphp
          @php $newEmploymentStatusVal = old('new_employment_status', ''); @endphp
          <select class="form-select" name="employment_status" id="employment_status">
            <option value="">Pilih Status</option>
            @foreach (($statuses ?? []) as $s)
              <option value="{{ $s->status_name }}" {{ $statusVal === $s->status_name ? 'selected' : '' }}>
                {{ $s->status_name }}
              </option>
            @endforeach
            <option value="__new__" {{ $newEmploymentStatusVal !== '' ? 'selected' : '' }}>+ Tambah Status Baru</option>
          </select>
          <div class="mt-2 {{ $newEmploymentStatusVal !== '' ? '' : 'd-none' }}" id="new_employment_status_wrap">
            <input type="text" class="form-control" name="new_employment_status" id="new_employment_status" value="{{ $newEmploymentStatusVal }}" placeholder="Ketik status karyawan baru">
            <div class="form-text">Saat disimpan, status baru ini otomatis masuk ke master status karyawan.</div>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Staff / Non Staff</label>
          @php $typeVal = old('employee_type', $edit->employee_type ?? ''); @endphp
          @php $newEmployeeTypeVal = old('new_employee_type', ''); @endphp
          <select class="form-select" name="employee_type" id="employee_type_select">
            <option value="">Pilih Tipe</option>
            @foreach (($types ?? []) as $t)
              <option value="{{ $t->type_name }}" {{ $typeVal === $t->type_name ? 'selected' : '' }}>
                {{ $t->type_name }}
              </option>
            @endforeach
            <option value="__new__" {{ $newEmployeeTypeVal !== '' ? 'selected' : '' }}>+ Tambah Tipe Baru</option>
          </select>
          <div class="mt-2 {{ $newEmployeeTypeVal !== '' ? '' : 'd-none' }}" id="new_employee_type_wrap">
            <input type="text" class="form-control" name="new_employee_type" id="new_employee_type" value="{{ $newEmployeeTypeVal }}" placeholder="Ketik tipe baru">
            <div class="form-text">Saat disimpan, tipe baru ini otomatis masuk ke master staff / non staff.</div>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Departement</label>
          @php $deptVal = old('department', $edit->department ?? ''); @endphp
          @php $newDepartmentVal = old('new_department', ''); @endphp
          <select class="form-select" name="department" id="department_select">
            <option value="">Pilih Departement</option>
            @foreach (($departments ?? []) as $d)
              <option value="{{ $d->department_name }}" {{ $deptVal === $d->department_name ? 'selected' : '' }}>
                {{ $d->department_name }}
              </option>
            @endforeach
            <option value="__new__" {{ $newDepartmentVal !== '' ? 'selected' : '' }}>+ Tambah Departement Baru</option>
          </select>
          <div class="mt-2 {{ $newDepartmentVal !== '' ? '' : 'd-none' }}" id="new_department_wrap">
            <input type="text" class="form-control" name="new_department" id="new_department" value="{{ $newDepartmentVal }}" placeholder="Ketik departement baru">
            <div class="form-text">Saat disimpan, departement baru ini otomatis masuk ke master departement.</div>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Jabatan</label>
          @php $positionVal = old('position', $edit->position ?? ''); @endphp
          @php $newPositionVal = old('new_position', ''); @endphp
          <select class="form-select" name="position" id="position_select">
            <option value="">Pilih Jabatan</option>
            @foreach (($positions ?? []) as $p)
              <option value="{{ $p->position_name }}" {{ $positionVal === $p->position_name ? 'selected' : '' }}>
                {{ $p->position_name }}
              </option>
            @endforeach
            <option value="__new__" {{ $newPositionVal !== '' ? 'selected' : '' }}>+ Tambah Jabatan Baru</option>
          </select>
          <div class="mt-2 {{ $newPositionVal !== '' ? '' : 'd-none' }}" id="new_position_wrap">
            <input type="text" class="form-control" name="new_position" id="new_position" value="{{ $newPositionVal }}" placeholder="Ketik jabatan baru">
            <div class="form-text">Saat disimpan, jabatan baru ini otomatis masuk ke master jabatan.</div>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Golongan</label>
          @php $gradeVal = old('grade', $edit->grade ?? ''); @endphp
          @php $newGradeVal = old('new_grade', ''); @endphp
          <select class="form-select" name="grade" id="grade_select">
            <option value="">Pilih Golongan</option>
            @foreach (($grades ?? []) as $g)
              <option value="{{ $g->grade_name }}" {{ $gradeVal === $g->grade_name ? 'selected' : '' }}>
                {{ $g->grade_name }}
              </option>
            @endforeach
            <option value="__new__" {{ $newGradeVal !== '' ? 'selected' : '' }}>+ Tambah Golongan Baru</option>
          </select>
          <div class="mt-2 {{ $newGradeVal !== '' ? '' : 'd-none' }}" id="new_grade_wrap">
            <input type="text" class="form-control" name="new_grade" id="new_grade" value="{{ $newGradeVal }}" placeholder="Ketik golongan baru">
            <div class="form-text">Saat disimpan, golongan baru ini otomatis masuk ke master golongan.</div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="row gy-2" style="--bs-gutter-x:10px;">
            <div class="col-6">
              <label class="form-label">Tanggal Join</label>
              <input type="date" class="form-control" name="join_date" value="{{ $edit->join_date ?? '' }}">
            </div>
            <div class="col-6">
              <label class="form-label">Last Working Date</label>
              <input type="date" class="form-control" name="last_working_date" value="{{ old('last_working_date', $edit->last_working_date ?? '') }}">
              <div class="form-text">Isi untuk status Dalam Proses Resign supaya payroll terakhir tetap dihitung proporsional.</div>
            </div>
            <div class="col-6">
              <label class="form-label">Habis Kontrak</label>
              <input type="date" class="form-control" name="contract_end" value="{{ $edit->contract_end ?? '' }}">
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Upload Pas Foto (Jpg/Png)</label>
          <input type="file" class="form-control" name="photo" accept=".jpg,.jpeg,.png">
          <div class="form-text" id="draft_photo_hint"></div>
          @if (!empty($edit->photo_path))
            <div class="form-text">Current: <img src="{{ asset_url($edit->photo_path) }}" alt="photo" style="height:24px"></div>
          @endif
        </div>
        <div class="col-md-6">
          <label class="form-label">Upload KTP</label>
          <input type="file" class="form-control" name="ktp_file" accept=".jpg,.jpeg,.png,.pdf">
          <div class="form-text" id="draft_ktp_hint"></div>
          @if (!empty($edit->ktp_path))
            <div class="form-text">Current: <a class="doc-link" href="{{ asset_url($edit->ktp_path) }}" target="_blank">Lihat file</a></div>
          @endif
        </div>
        <div class="col-md-6">
          <div class="row gy-2" style="--bs-gutter-x:10px;">
            <div class="col-6">
              <label class="form-label">Upload Ijazah</label>
              <input type="file" class="form-control" name="ijazah_file" accept=".jpg,.jpeg,.png,.pdf">
              <div class="form-text" id="draft_ijazah_hint"></div>
              @if (!empty($edit->ijazah_path))
                <div class="form-text">Current: <a class="doc-link" href="{{ asset_url($edit->ijazah_path) }}" target="_blank">Lihat file</a></div>
              @endif
            </div>
            <div class="col-6">
              <label class="form-label">Upload Surat Lamaran Kerja</label>
              <input type="file" class="form-control" name="surat_lamaran_file" accept=".jpg,.jpeg,.png,.pdf">
              <div class="form-text" id="draft_surat_lamaran_hint"></div>
              @if (!empty($edit->surat_lamaran_path))
                <div class="form-text">Current: <a class="doc-link" href="{{ asset_url($edit->surat_lamaran_path) }}" target="_blank">Lihat file</a></div>
              @endif
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Upload NPWP</label>
          <input type="file" class="form-control" name="npwp_file" accept=".jpg,.jpeg,.png,.pdf">
          <div class="form-text" id="draft_npwp_hint"></div>
          @if (!empty($edit->npwp_path))
            <div class="form-text">Current: <a class="doc-link" href="{{ asset_url($edit->npwp_path) }}" target="_blank">Lihat file</a></div>
          @endif
        </div>
        <div class="col-md-6">
          <label class="form-label">Upload MCU/Surat Sehat</label>
          <input type="file" class="form-control" name="mcu_file" accept=".jpg,.jpeg,.png,.pdf">
          <div class="form-text" id="draft_mcu_hint"></div>
          @if (!empty($edit->mcu_file_path))
            <div class="form-text">Current: <a class="doc-link" href="{{ asset_url($edit->mcu_file_path) }}" target="_blank">Lihat file</a></div>
          @endif
        </div>
        <div class="col-md-6">
          <label class="form-label">Upload KK</label>
          <input type="file" class="form-control" name="kk_file" accept=".jpg,.jpeg,.png,.pdf">
          <div class="form-text" id="draft_kk_hint"></div>
          @if (!empty($edit->kk_path))
            <div class="form-text">Current: <a class="doc-link" href="{{ asset_url($edit->kk_path) }}" target="_blank">Lihat file</a></div>
          @endif
        </div>
        <div class="col-md-6">
          <label class="form-label">Upload CV</label>
          <input type="file" class="form-control" name="cv_file" accept=".jpg,.jpeg,.png,.pdf">
          <div class="form-text" id="draft_cv_hint"></div>
          @if (!empty($edit->cv_file_path))
            <div class="form-text">Current: <a class="doc-link" href="{{ asset_url($edit->cv_file_path) }}" target="_blank">Lihat file</a></div>
          @endif
        </div>
        <div class="col-md-6">
          <label class="form-label">Upload SKCK</label>
          <input type="file" class="form-control" name="skck_file" accept=".jpg,.jpeg,.png,.pdf">
          <div class="form-text" id="draft_skck_hint"></div>
          @if (!empty($edit->skck_path))
            <div class="form-text">Current: <a class="doc-link" href="{{ asset_url($edit->skck_path) }}" target="_blank">Lihat file</a></div>
          @endif
        </div>
        <div class="col-12">
          <label class="form-label">Dokumen SDM (bisa lebih dari satu)</label>
          <div id="hrdDocsWrap" class="border rounded p-2">
            <div class="row g-2 align-items-end mb-2" id="hrdDocsTemplate" style="display:none;">
              <div class="col-md-5">
                <input type="text" class="form-control" placeholder="Nama dokumen" data-hrd-name>
              </div>
              <div class="col-md-5">
                <input type="file" class="form-control" data-hrd-file accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx">
              </div>
              <div class="col-md-2">
                <button type="button" class="btn btn-outline-danger w-100" data-hrd-remove>Hapus</button>
              </div>
            </div>
            <div id="hrdDocsList"></div>
            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="btnAddHrdDoc">Tambah Dokumen SDM</button>
          </div>
          @if (!empty($documents ?? null) && count($documents) > 0)
            <div class="mt-2">
              <div class="fw-semibold">Dokumen SDM tersimpan</div>
              @foreach ($documents as $doc)
                <div class="form-check d-flex align-items-center gap-2">
                  <input class="form-check-input" type="checkbox" name="delete_hrd_docs[]" value="{{ $doc->id }}" id="del_hrd_{{ $doc->id }}">
                  <label class="form-check-label" for="del_hrd_{{ $doc->id }}">
                    {{ $doc->document_name }}
                  </label>
                  <a class="doc-link ms-auto" href="{{ asset_url($doc->file_path) }}" target="_blank">Lihat file</a>
                </div>
              @endforeach
              <div class="form-text">Centang untuk menghapus dokumen saat simpan.</div>
        </div>
        @endif
      </div>
      </div>
        </div>
        @endif

        @if ($canPayroll)
        <div class="tab-pane fade {{ $activeTab === 'payroll' ? 'show active' : '' }}" id="tab-payroll" role="tabpanel">
        <div class="fw-semibold mb-2">Payroll Settings (Per Karyawan)</div>
        @php
          $absenceModeVal = old('absence_mode', (string) (optional($payroll)->absence_mode ?? 'auto'));
          $manualPresentDaysVal = old('manual_present_days', (float) (optional($payroll)->manual_present_days ?? 0));
        @endphp
        <div class="row g-2">
          <div class="col-12">
            <div class="alert alert-info py-2 mb-1">
              <div class="fw-semibold">Setting Absensi Payroll (Per Karyawan)</div>
              <div class="small">Disimpan per karyawan, tidak memengaruhi karyawan lain.</div>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Mode Absensi Payroll</label>
            <select class="form-select" name="absence_mode" id="absence_mode">
              <option value="auto" {{ $absenceModeVal === 'auto' ? 'selected' : '' }}>Otomatis dari absensi harian</option>
              <option value="manual" {{ $absenceModeVal === 'manual' ? 'selected' : '' }}>Manual (input hari masuk)</option>
            </select>
          </div>
          <div class="col-md-6 {{ $absenceModeVal === 'manual' ? '' : 'd-none' }}" id="manual_present_days_wrap">
            <label class="form-label">Hari Kerja Masuk</label>
            <input type="number" min="0" step="0.5" class="form-control" name="manual_present_days" id="manual_present_days" value="{{ $manualPresentDaysVal }}">
            <div class="form-text">Dipakai saat mode manual untuk karyawan ini saja.</div>
          </div>
          <div class="col-6">
            <label class="form-label" id="basic_salary_label">Gaji Pokok</label>
            <input type="text" class="form-control js-currency" name="basic_salary" id="basic_salary" value="{{ format_currency_id(optional($payroll)->basic_salary ?? 0, 2, false) }}">
            <div class="form-text" id="basic_salary_hint">Nilai gaji pokok bulanan.</div>
          </div>
          <div class="col-6">
            <label class="form-label">Lembur</label>
            <input type="text" class="form-control js-currency" name="a2_overtime" value="{{ format_currency_id(optional($payroll)->a2_overtime ?? 0, 2, false) }}" readonly>
            <div class="form-text">Preview nominal lembur. Perhitungan final mengikuti mode jam lembur saat payroll run.</div>
          </div>
          <div class="col-6">
            <label class="form-label">Mode Jam Lembur</label>
            @php $overtimeModeVal = old('overtime_mode', optional($payroll)->overtime_mode ?? 'auto'); @endphp
            <select class="form-select" name="overtime_mode" id="overtime_mode">
              <option value="auto" {{ $overtimeModeVal === 'auto' ? 'selected' : '' }}>Otomatis (dari absensi)</option>
              <option value="manual" {{ $overtimeModeVal === 'manual' ? 'selected' : '' }}>Manual (input HR)</option>
            </select>
            <div class="form-text">Jika manual, HR dapat mengisi jam lembur sendiri.</div>
          </div>
          <div class="col-12 {{ $overtimeModeVal === 'manual' ? '' : 'd-none' }}" id="overtime_manual_hours_wrap">
            <div class="row g-2">
              <div class="col-md-3">
                <label class="form-label">Lembur Jam-1</label>
                <input type="number" step="0.01" min="0" class="form-control js-manual-ot" name="overtime_manual_hour_1" id="overtime_manual_hour_1" value="{{ old('overtime_manual_hour_1', (float) (optional($payroll)->overtime_manual_hour_1 ?? 0)) }}">
              </div>
              <div class="col-md-3">
                <label class="form-label">Lembur Jam-2</label>
                <input type="number" step="0.01" min="0" class="form-control js-manual-ot" name="overtime_manual_hour_2" id="overtime_manual_hour_2" value="{{ old('overtime_manual_hour_2', (float) (optional($payroll)->overtime_manual_hour_2 ?? 0)) }}">
              </div>
              <div class="col-md-3">
                <label class="form-label">Libur 8 Jam Pertama</label>
                <input type="number" step="0.01" min="0" class="form-control js-manual-ot" name="overtime_manual_holiday_8" id="overtime_manual_holiday_8" value="{{ old('overtime_manual_holiday_8', (float) (optional($payroll)->overtime_manual_holiday_8 ?? 0)) }}">
              </div>
              <div class="col-md-3">
                <label class="form-label">Libur Jam Ke-9</label>
                <input type="number" step="0.01" min="0" class="form-control js-manual-ot" name="overtime_manual_holiday_9" id="overtime_manual_holiday_9" value="{{ old('overtime_manual_holiday_9', (float) (optional($payroll)->overtime_manual_holiday_9 ?? 0)) }}">
              </div>
              <div class="col-12">
                <input type="hidden" name="overtime_manual_hours" id="overtime_manual_hours" value="{{ old('overtime_manual_hours', (float) (optional($payroll)->overtime_manual_hours ?? 0)) }}">
                <div class="form-text">Dipakai jika mode jam lembur = Manual. Rumus umum: (Gaji Pokok / 173) x (Jam-1*1.5 + Jam-2*2 + Libur8*2 + Libur9*3). Khusus status HARIAN: flat 1x per jam.</div>
              </div>
            </div>
          </div>
          <div class="col-6">
            <label class="form-label">Rate Lembur / Jam</label>
            <input type="text" class="form-control js-currency" name="a2_overtime_flat" value="{{ format_currency_id(optional($payroll)->a2_overtime_flat ?? 0, 2, false) }}" readonly>
            <div class="form-text" id="overtime_rate_hint">Otomatis dari rumus: Gaji Pokok / 173.</div>
          </div>
          <div class="col-6">
            <label class="form-label">Tunjangan Makan</label>
            <input type="text" class="form-control js-currency" name="a3_meal" value="{{ format_currency_id(optional($payroll)->a3_meal ?? 0, 2, false) }}">
          </div>
          <div class="col-6">
            <label class="form-label">Tunjangan Transport</label>
            <input type="text" class="form-control js-currency" name="a4_transport" value="{{ format_currency_id(optional($payroll)->a4_transport ?? 0, 2, false) }}">
          </div>
          <div class="col-6">
            <label class="form-label">Tunjangan Kinerja</label>
            <input type="text" class="form-control js-currency" name="a5_performance" value="{{ format_currency_id(optional($payroll)->a5_performance ?? 0, 2, false) }}">
          </div>
          <div class="col-6">
            <label class="form-label">Tunjangan Jabatan</label>
            <input type="text" class="form-control js-currency" name="a6_position" value="{{ format_currency_id(optional($payroll)->a6_position ?? 0, 2, false) }}">
          </div>
          <div class="col-6">
            <label class="form-label">Tunjangan Anak & Istri</label>
            <input type="text" class="form-control js-currency" name="a7_family" value="{{ format_currency_id(optional($payroll)->a7_family ?? 0, 2, false) }}">
          </div>
          <div class="col-6">
            <label class="form-label">Tunjangan Komunikasi</label>
            <input type="text" class="form-control js-currency" name="a8_communication" value="{{ format_currency_id(optional($payroll)->a8_communication ?? 0, 2, false) }}">
          </div>
          <div class="col-6">
            <label class="form-label">Tunjangan Lain</label>
            <input type="text" class="form-control js-currency" name="a9_other" value="{{ format_currency_id(optional($payroll)->a9_other ?? 0, 2, false) }}">
          </div>
          <div class="col-6">
            <label class="form-label">THR</label>
            <input type="text" class="form-control js-currency" name="a10_thr" value="{{ format_currency_id(optional($payroll)->a10_thr ?? 0, 2, false) }}">
          </div>
          <div class="col-6">
            <label class="form-label">Bonus</label>
            <input type="text" class="form-control js-currency" name="a11_bonus" value="{{ format_currency_id(optional($payroll)->a11_bonus ?? 0, 2, false) }}">
          </div>
          <div class="col-6">
            <label class="form-label">Rapel Gaji</label>
            <input type="text" class="form-control js-currency" name="a12_rapel_gaji" value="{{ format_currency_id(optional($payroll)->a12_rapel_gaji ?? 0, 2, false) }}">
          </div>
          <div class="col-6">
            <label class="form-label">Tunjangan Pajak</label>
            <input type="text" class="form-control js-currency bg-light" id="a12_tax_allowance" name="a12_tax_allowance" value="{{ format_currency_id(optional($payroll)->a12_tax_allowance ?? 0, 2, false) }}" readonly>
          </div>
          <div class="col-6">
            <label class="form-label">Tunjangan BPJS</label>
            <input type="text" class="form-control js-currency bg-light" id="a13_bpjs_allowance" name="a13_bpjs_allowance" value="{{ format_currency_id(optional($payroll)->a13_bpjs_allowance ?? 0, 2, false) }}" readonly>
            <div class="form-text">Aktif untuk status ALL-IN dan KOMISARIS.</div>
          </div>

          <div class="col-6">
            <label class="form-label">Pinjaman</label>
            <input type="text" class="form-control js-currency" name="b1_loan" value="{{ format_currency_id(optional($payroll)->b1_loan ?? 0, 2, false) }}">
          </div>
          <div class="col-6">
            <label class="form-label">Potongan Absensi</label>
            <input type="text" class="form-control js-currency" name="b2_absence" value="{{ format_currency_id($absencePreview['amount'] ?? (optional($payroll)->b2_absence ?? 0), 2, false) }}" readonly>
            <div class="form-text">Otomatis dari tidak hadir: {{ $absencePreview['absence_days'] ?? 0 }} hari x ((A1 + A5 + A6 + A7) / {{ number_format((float) ($absencePreview['absence_divisor'] ?? 26), 0, ',', '.') }}). Cuti/Sakit dengan surat dokter tidak dipotong.</div>
          </div>
          <div class="col-6">
            <label class="form-label">Subsidi 5%</label>
            <input type="text" class="form-control js-currency" name="b3_subsidy" value="{{ format_currency_id(optional($payroll)->b3_subsidy ?? 0, 2, false) }}">
          </div>
          <div class="col-6">
            <label class="form-label">BPJS Kesehatan (1%)</label>
            <input type="text" class="form-control js-currency" name="b4_bpjs_health" id="b4_bpjs_health" value="{{ format_currency_id(optional($payroll)->b4_bpjs_health ?? 0, 2, false) }}" readonly>
          </div>
          <div class="col-6">
            <label class="form-label">JHT (2%)</label>
            <input type="text" class="form-control js-currency" name="b5_jht" id="b5_jht" value="{{ format_currency_id(optional($payroll)->b5_jht ?? 0, 2, false) }}" readonly>
          </div>
          <div class="col-6">
            <label class="form-label">JP (1%)</label>
            <input type="text" class="form-control js-currency" name="b6_jp" id="b6_jp" value="{{ format_currency_id(optional($payroll)->b6_jp ?? 0, 2, false) }}" readonly>
          </div>
          <div class="col-6">
            <label class="form-label">PPH21</label>
            <input type="text" class="form-control js-currency" name="b7_pph21" id="b7_pph21" value="{{ format_currency_id(optional($payroll)->b7_pph21 ?? 0, 2, false) }}" readonly>
            <div class="form-text">Estimasi Jan-Nov (TER). Perhitungan Desember mengikuti rekonsiliasi tahunan saat payroll run.</div>
          </div>
          <div class="col-6">
            <label class="form-label">Lain-lain</label>
            <input type="text" class="form-control js-currency" name="b8_other" value="{{ format_currency_id(optional($payroll)->b8_other ?? 0, 2, false) }}">
          </div>
        </div>
      </div>
        @endif
      </div>

      @if ($canProfile || $canPayroll)
      <div class="d-flex gap-2 justify-content-end mt-3">
        @if ($canProfile)
        <button class="btn btn-outline-secondary" type="button" id="btnDraft">Draft</button>
        @endif
        <button class="btn btn-primary" type="submit">Simpan</button>
        <a class="btn btn-outline-secondary" href="{{ route('employees.index') }}">Kembali</a>
      </div>
      @endif

    </form>
  </div>
</div>

<div class="modal fade" id="mutasiModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Mutasi Karyawan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="window.__empMutasiCancel && window.__empMutasiCancel()"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2 text-muted">
          Karyawan akan dipindahkan ke entitas perusahaan tujuan, dan di entitas sebelumnya masuk <span class="fw-semibold">Arsip Mutasi</span>.
        </div>
        <div class="mb-3">
          <label class="form-label">Mutasi ke Perusahaan</label>
          <select class="form-select" id="mutasi_to_company_id_modal">
            <option value="">-- pilih perusahaan --</option>
            @foreach (($companies ?? []) as $c)
              @if ((int)($c->id ?? 0) !== (int)($companyId ?? 0))
                <option value="{{ $c->id }}">{{ $c->company_name }}</option>
              @endif
            @endforeach
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Catatan (opsional)</label>
          <input type="text" class="form-control" id="mutasi_note_modal" placeholder="Contoh: Mutasi karena kebutuhan proyek">
        </div>
        @if (empty($edit->id ?? null))
          <div class="alert alert-warning mb-0">Mutasi hanya bisa dilakukan setelah karyawan disimpan.</div>
        @endif
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" onclick="window.__empMutasiCancel && window.__empMutasiCancel()">Batal</button>
        <button type="button" class="btn btn-primary" onclick="window.__empMutasiConfirm && window.__empMutasiConfirm()">Lanjut</button>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    var activeStatusEl = document.getElementById('active_status');
    var mutasiToCompanyEl = document.getElementById('mutasi_to_company_id');
    var mutasiNoteEl = document.getElementById('mutasi_note');
    var lastActiveStatus = activeStatusEl ? activeStatusEl.value : '';

    function openMutasiModalIfNeeded() {
      if (!activeStatusEl) return;
      if ((activeStatusEl.value || '').trim() !== 'Mutasi') return;
      if (!mutasiToCompanyEl || mutasiToCompanyEl.value) return;
      if (!window.bootstrap) return;
      var modalEl = document.getElementById('mutasiModal');
      if (!modalEl) return;
      var modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
      modal.show();
    }

    if (activeStatusEl) {
      activeStatusEl.addEventListener('focus', function () {
        lastActiveStatus = activeStatusEl.value;
      });
      activeStatusEl.addEventListener('change', function () {
        if ((activeStatusEl.value || '').trim() === 'Mutasi') {
          openMutasiModalIfNeeded();
        } else {
          if (mutasiToCompanyEl) mutasiToCompanyEl.value = '';
          if (mutasiNoteEl) mutasiNoteEl.value = '';
        }
      });

      window.addEventListener('load', function () {
        openMutasiModalIfNeeded();
      });
    }

    window.__empMutasiCancel = function () {
      if (activeStatusEl) activeStatusEl.value = lastActiveStatus || 'Active';
      if (mutasiToCompanyEl) mutasiToCompanyEl.value = '';
      if (mutasiNoteEl) mutasiNoteEl.value = '';
    };

    window.__empMutasiConfirm = function () {
      var toEl = document.getElementById('mutasi_to_company_id_modal');
      var noteEl = document.getElementById('mutasi_note_modal');
      if (!toEl || !mutasiToCompanyEl) return;
      if (!toEl.value) {
        alert('Pilih perusahaan tujuan mutasi.');
        return;
      }
      mutasiToCompanyEl.value = toEl.value || '';
      if (mutasiNoteEl) {
        mutasiNoteEl.value = noteEl ? (noteEl.value || '') : '';
      }
      if (window.bootstrap) {
        var modalEl = document.getElementById('mutasiModal');
        if (modalEl) window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      }
    };
  })();
</script>
<script>
  (function () {
    function bindInlineMaster(selectId, wrapId, inputId) {
      var select = document.getElementById(selectId);
      var wrap = document.getElementById(wrapId);
      var input = document.getElementById(inputId);
      if (!select || !wrap || !input) return;

      function syncState() {
        var isNew = select.value === '__new__';
        wrap.classList.toggle('d-none', !isNew);
        input.required = isNew;
        if (isNew) {
          input.focus();
        } else {
          input.value = '';
        }
      }

      select.addEventListener('change', syncState);
      syncState();
    }

    bindInlineMaster('employment_status', 'new_employment_status_wrap', 'new_employment_status');
    bindInlineMaster('employee_type_select', 'new_employee_type_wrap', 'new_employee_type');
    bindInlineMaster('department_select', 'new_department_wrap', 'new_department');
    bindInlineMaster('position_select', 'new_position_wrap', 'new_position');
    bindInlineMaster('grade_select', 'new_grade_wrap', 'new_grade');
  })();
</script>
<script>
  (function () {
    var basic = document.getElementById('basic_salary');
    var bpjsHealth = document.getElementById('b4_bpjs_health');
    var jht = document.getElementById('b5_jht');
    var jp = document.getElementById('b6_jp');
    var pph21 = document.getElementById('b7_pph21');
    var taxAllowance = document.getElementById('a12_tax_allowance');
    var bpjsAllowance = document.getElementById('a13_bpjs_allowance');
    var ptkp = document.getElementById('ptkp_status');
    var empStatus = document.getElementById('employment_status');
    var overtimeMode = document.getElementById('overtime_mode');
    var absenceMode = document.getElementById('absence_mode');
    var manualPresentDaysWrap = document.getElementById('manual_present_days_wrap');
    var manualPresentDays = document.getElementById('manual_present_days');
    var overtimeManualHours = document.getElementById('overtime_manual_hours');
    var overtimeManualHour1 = document.getElementById('overtime_manual_hour_1');
    var overtimeManualHour2 = document.getElementById('overtime_manual_hour_2');
    var overtimeManualHoliday8 = document.getElementById('overtime_manual_holiday_8');
    var overtimeManualHoliday9 = document.getElementById('overtime_manual_holiday_9');
    var overtimeManualWrap = document.getElementById('overtime_manual_hours_wrap');
    var overtimeAmount = document.getElementsByName('a2_overtime')[0];
    var overtimeFlat = document.getElementsByName('a2_overtime_flat')[0];
    var overtimeRateHint = document.getElementById('overtime_rate_hint');
    if (!basic || !bpjsHealth || !jht || !jp) return;

    var terTables = {!! json_encode(ter_tables()) !!};
    var ptkpMap = {
      'TK/0': 'A',
      'TK/1': 'A',
      'TK/2': 'B',
      'TK/3': 'B',
      'K/0': 'B',
      'K/1': 'C',
      'K/2': 'C',
      'K/3': 'C',
      'HB/0': 'A',
      'HB/1': 'A',
      'HB/2': 'B',
      'HB/3': 'B'
    };

    function getRate(category, bruto) {
      var rows = terTables[category] || [];
      for (var i = 0; i < rows.length; i++) {
        var max = rows[i][0];
        var rate = rows[i][1];
        if (bruto <= max) return rate;
      }
      return 0;
    }

    function parseIdNumber(value) {
      if (value === null || value === undefined) return 0;
      if (typeof value === 'number') {
        return isFinite(value) ? value : 0;
      }

      var cleaned = String(value).trim().replace(/[^0-9,.-]/g, '');
      if (!cleaned) return 0;

      var lastComma = cleaned.lastIndexOf(',');
      var lastDot = cleaned.lastIndexOf('.');
      var decimalSep = null;
      if (lastComma >= 0 && lastDot >= 0) {
        decimalSep = lastComma > lastDot ? ',' : '.';
      } else if (lastComma >= 0) {
        decimalSep = ',';
      } else if (lastDot >= 0) {
        var dotParts = cleaned.split('.');
        var tail = dotParts[dotParts.length - 1] || '';
        var groupedThousands = dotParts.length > 1 && tail.length === 3 && dotParts.slice(1).every(function (part) {
          return part.length === 3;
        });
        decimalSep = groupedThousands ? null : '.';
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

    var initialOvertimeAmount = parseIdNumber(overtimeAmount ? overtimeAmount.value : '0');
    var initialBasicSalary = parseIdNumber(basic ? basic.value : '0');
    var initialBaseRate = initialBasicSalary > 0 ? (initialBasicSalary / 173) : 0;
    var impliedAutoWeightedHours = initialBaseRate > 0 ? (initialOvertimeAmount / initialBaseRate) : 0;

    function valByName(name) {
      var el = document.getElementsByName(name)[0];
      if (!el) return 0;
      return el.value || '0';
    }

    function brutoPajakBulanan() {
      var gajiPokok = parseIdNumber(valByName('basic_salary'));
      var lembur = parseIdNumber(valByName('a2_overtime'));
      var tunjanganTetap = parseIdNumber(valByName('a6_position')) + parseIdNumber(valByName('a7_family')) + parseIdNumber(valByName('a8_communication'));
      var tunjanganTidakTetap = parseIdNumber(valByName('a3_meal')) + parseIdNumber(valByName('a4_transport')) + parseIdNumber(valByName('a5_performance')) + parseIdNumber(valByName('a9_other'));
      var bonusInsentif = parseIdNumber(valByName('a10_thr')) + parseIdNumber(valByName('a11_bonus')) + parseIdNumber(valByName('a12_rapel_gaji'));
      var tunjanganLain = parseIdNumber(valByName('a12_tax_allowance')) + parseIdNumber(valByName('a13_bpjs_allowance'));
      return gajiPokok + lembur + tunjanganTetap + tunjanganTidakTetap + bonusInsentif + tunjanganLain;
    }

    function syncOvertimeModeUi() {
      var mode = overtimeMode ? (overtimeMode.value || 'auto') : 'auto';
      var isManual = mode === 'manual';
      var statusVal = (empStatus && empStatus.value) ? empStatus.value.toUpperCase().trim() : '';
      var isHarian = statusVal.indexOf('HARIAN') !== -1;
      if (overtimeManualWrap) {
        overtimeManualWrap.classList.toggle('d-none', !isManual);
      }
      if (overtimeManualHours) {
        overtimeManualHours.required = isManual;
      }
      if (overtimeManualHour1) overtimeManualHour1.required = isManual;
      if (overtimeManualHour2) overtimeManualHour2.required = isManual;
      if (overtimeManualHoliday8) overtimeManualHoliday8.required = isManual;
      if (overtimeManualHoliday9) overtimeManualHoliday9.required = isManual;

      var base = parseIdNumber(basic ? basic.value : '0');
      var baseRate = isHarian ? base : (base > 0 ? (base / 173) : 0);
      if (overtimeFlat) {
        overtimeFlat.value = formatIdNumber(baseRate);
      }
      if (overtimeRateHint) {
        overtimeRateHint.textContent = isHarian
          ? 'Status HARIAN: lembur flat 1x per jam dengan rate Gaji per Jam.'
          : 'Otomatis dari rumus: Gaji Pokok / 173.';
      }

      if (overtimeAmount) {
        if (isManual) {
          var h1 = parseIdNumber(overtimeManualHour1 ? overtimeManualHour1.value : '0');
          var h2 = parseIdNumber(overtimeManualHour2 ? overtimeManualHour2.value : '0');
          var h8 = parseIdNumber(overtimeManualHoliday8 ? overtimeManualHoliday8.value : '0');
          var h9 = parseIdNumber(overtimeManualHoliday9 ? overtimeManualHoliday9.value : '0');
          var totalHours = h1 + h2 + h8 + h9;
          var weighted = (h1 * 1.5) + (h2 * 2) + (h8 * 2) + (h9 * 3);
          var billableHours = isHarian ? totalHours : weighted;
          if (overtimeManualHours) {
            overtimeManualHours.value = totalHours.toFixed(2);
          }
          overtimeAmount.value = formatIdNumber(billableHours * baseRate);
        } else {
          // Auto mode preview should follow current basic salary change.
          // We keep weighted hours implied from initial loaded payroll value.
          overtimeAmount.value = formatIdNumber(baseRate * impliedAutoWeightedHours);
          if (overtimeManualHours) {
            overtimeManualHours.value = '0';
          }
        }
      }
    }

    function syncAbsenceModeUi() {
      if (!absenceMode || !manualPresentDaysWrap || !manualPresentDays) return;
      var isManual = (absenceMode.value || 'auto') === 'manual';
      manualPresentDaysWrap.classList.toggle('d-none', !isManual);
      manualPresentDays.required = isManual;
    }

    function calcAll() {
      syncOvertimeModeUi();
      var statusVal = (empStatus && empStatus.value) ? empStatus.value.toUpperCase().trim() : '';
      var allInOrKomisaris = statusVal.indexOf('ALL-IN') !== -1 || statusVal === 'KOMISARIS';
      if (taxAllowance) {
        taxAllowance.readOnly = true;
        taxAllowance.classList.add('bg-light');
      }
      if (bpjsAllowance) {
        bpjsAllowance.readOnly = true;
        bpjsAllowance.classList.add('bg-light');
      }

      if (statusVal.indexOf('HARIAN') !== -1 || statusVal.indexOf('PERCOBAAN') !== -1) {
        bpjsHealth.value = formatIdNumber(0);
        jht.value = formatIdNumber(0);
        jp.value = formatIdNumber(0);
      } else {
        var base = parseIdNumber(basic.value || '0') || 0;
        bpjsHealth.value = formatIdNumber(base * 0.01);
        jht.value = formatIdNumber(base * 0.02);
        jp.value = formatIdNumber(base * 0.01);
      }

      if (pph21 && ptkp) {
        var bruto = brutoPajakBulanan();
        var cat = ptkpMap[ptkp.value] || 'A';
        var rate = getRate(cat, bruto);
        pph21.value = formatIdNumber(bruto * rate);
      }

      if (allInOrKomisaris) {
        var autoBpjsAllowance = parseIdNumber(bpjsHealth.value) + parseIdNumber(jht.value) + parseIdNumber(jp.value);
        if (bpjsAllowance) {
          bpjsAllowance.value = formatIdNumber(autoBpjsAllowance);
        }
        if (taxAllowance && pph21) {
          taxAllowance.value = formatIdNumber(pph21.value);
        }
      } else {
        if (bpjsAllowance) {
          bpjsAllowance.value = formatIdNumber(0);
        }
        if (taxAllowance) {
          taxAllowance.value = formatIdNumber(0);
        }
      }
    }

    document.querySelectorAll(
      'input[name="basic_salary"], input[name="a2_overtime"], input[name="a2_overtime_flat"], input[name="a3_meal"], input[name="a4_transport"], input[name="a5_performance"], input[name="a6_position"], input[name="a7_family"], input[name="a8_communication"], input[name="a9_other"], input[name="a10_thr"], input[name="a11_bonus"], input[name="a12_rapel_gaji"], input[name="a12_tax_allowance"], input[name="a13_bpjs_allowance"]'
    ).forEach(function (el) {
      el.addEventListener('input', calcAll);
      el.addEventListener('blur', function () {
        el.value = formatIdNumber(el.value);
      });
    });
    function syncBasicSalaryLabel() {
      if (!empStatus) return;
      var statusVal = (empStatus.value || '').toUpperCase().trim();
      var isHarian = statusVal.indexOf('HARIAN') !== -1;
      var label = document.getElementById('basic_salary_label');
      var hint = document.getElementById('basic_salary_hint');
      if (label) label.textContent = isHarian ? 'Gaji per Jam' : 'Gaji Pokok';
      if (hint) hint.textContent = isHarian ? 'Status Harian: isi nominal gaji per jam.' : 'Nilai gaji pokok bulanan.';
    }

    if (ptkp) ptkp.addEventListener('change', calcAll);
    if (empStatus) empStatus.addEventListener('change', function () { syncBasicSalaryLabel(); calcAll(); });
    if (overtimeMode) overtimeMode.addEventListener('change', calcAll);
    if (absenceMode) absenceMode.addEventListener('change', syncAbsenceModeUi);
    if (overtimeManualHours) overtimeManualHours.addEventListener('input', calcAll);
    [overtimeManualHour1, overtimeManualHour2, overtimeManualHoliday8, overtimeManualHoliday9].forEach(function (el) {
      if (el) el.addEventListener('input', calcAll);
    });
    document.querySelectorAll('.js-currency').forEach(function (el) {
      el.addEventListener('blur', function () {
        el.value = formatIdNumber(el.value);
      });
      el.value = formatIdNumber(el.value);
    });
    var form = document.getElementById('employeeForm');
    if (form) {
      form.addEventListener('submit', function () {
        document.querySelectorAll('.js-currency').forEach(function (input) {
          input.value = parseIdNumber(input.value).toString();
        });
      });
    }
    syncBasicSalaryLabel();
    syncAbsenceModeUi();
    calcAll();
  })();
</script>
<script>
  (function () {
    var form = document.getElementById('employeeForm');
    var btnDraft = document.getElementById('btnDraft');
    if (!form || !btnDraft) return;

    var draftKey = 'employee_form_draft';
    var fieldNames = [
      'company_id','nik','nik_ktp','address_ktp','domicile_address','name','active_status','place_of_birth','date_of_birth','phone','emergency_contact_number',
      'npwp','bank_name','bank_account_no','ptkp_status','employment_status','new_employment_status','employee_type','new_employee_type','department','new_department',
      'position','new_position','grade','new_grade','join_date','contract_end','placement_company_id'
    ];

    function setDraftHints(data) {
      var map = {
        photo: {path: data.draft_photo_path, hint: 'draft_photo_hint', input: 'draft_photo_path', label: 'Pas Foto'},
        ktp_file: {path: data.draft_ktp_path, hint: 'draft_ktp_hint', input: 'draft_ktp_path', label: 'KTP'},
        ijazah_file: {path: data.draft_ijazah_path, hint: 'draft_ijazah_hint', input: 'draft_ijazah_path', label: 'Ijazah'},
        surat_lamaran_file: {path: data.draft_surat_lamaran_path, hint: 'draft_surat_lamaran_hint', input: 'draft_surat_lamaran_path', label: 'Surat Lamaran Kerja'},
        cv_file: {path: data.draft_cv_file_path, hint: 'draft_cv_hint', input: 'draft_cv_file_path', label: 'CV'},
        mcu_file: {path: data.draft_mcu_file_path, hint: 'draft_mcu_hint', input: 'draft_mcu_file_path', label: 'MCU/Surat Sehat'},
        kk_file: {path: data.draft_kk_path, hint: 'draft_kk_hint', input: 'draft_kk_path', label: 'KK'},
        npwp_file: {path: data.draft_npwp_path, hint: 'draft_npwp_hint', input: 'draft_npwp_path', label: 'NPWP'},
        skck_file: {path: data.draft_skck_path, hint: 'draft_skck_hint', input: 'draft_skck_path', label: 'SKCK'}
      };
      Object.keys(map).forEach(function (key) {
        var item = map[key];
        var hintEl = document.getElementById(item.hint);
        var inputEl = document.getElementById(item.input);
        if (inputEl) inputEl.value = item.path || '';
        if (hintEl) {
          if (item.path) {
            hintEl.innerHTML = 'Draft: <a class="doc-link" href="/' + item.path + '" target="_blank">Lihat file</a>';
          } else {
            hintEl.innerHTML = '';
          }
        }
      });
    }

    function saveDraft() {
      var data = {};
      fieldNames.forEach(function (name) {
        var el = form.querySelector('[name="' + name + '"]');
        if (el) data[name] = el.value || '';
      });

      var fd = new FormData();
      fd.append('_token', '{{ csrf_token() }}');
      ['photo','ktp_file','ijazah_file','surat_lamaran_file','cv_file','mcu_file','kk_file','npwp_file','skck_file'].forEach(function (name) {
        var input = form.querySelector('[name="' + name + '"]');
        if (input && input.files && input.files[0]) {
          fd.append(name, input.files[0]);
        }
      });

      fetch('{{ route('employees.draft_upload') }}', {
        method: 'POST',
        body: fd
      }).then(function (res) {
        if (!res.ok) throw new Error('Upload draft gagal.');
        return res.json();
      }).then(function (json) {
        var paths = json.paths || {};
        var ocr = json.ocr || {};
        if (paths.photo) data.draft_photo_path = paths.photo;
        if (paths.ktp_file) data.draft_ktp_path = paths.ktp_file;
        if (paths.ijazah_file) data.draft_ijazah_path = paths.ijazah_file;
        if (paths.surat_lamaran_file) data.draft_surat_lamaran_path = paths.surat_lamaran_file;
        if (paths.cv_file) data.draft_cv_file_path = paths.cv_file;
        if (paths.mcu_file) data.draft_mcu_file_path = paths.mcu_file;
        if (paths.kk_file) data.draft_kk_path = paths.kk_file;
        if (paths.npwp_file) data.draft_npwp_path = paths.npwp_file;
        if (paths.skck_file) data.draft_skck_path = paths.skck_file;
        if (ocr.ktp_address) {
          data.address_ktp = ocr.ktp_address;
          var addr = form.querySelector('[name="address_ktp"]');
          if (addr) addr.value = ocr.ktp_address;
          var ocrHint = document.getElementById('ktp_ocr_hint');
          if (ocrHint) ocrHint.textContent = 'Alamat KTP berhasil dibaca otomatis.';
        }

        setDraftHints(data);
        localStorage.setItem(draftKey, JSON.stringify(data));
        alert('Draft tersimpan di browser.');
      }).catch(function () {
        alert('Gagal menyimpan draft.');
      });
    }

    function uploadKtpAndFillAddress() {
      var ktpInput = form.querySelector('[name="ktp_file"]');
      if (!ktpInput || !ktpInput.files || !ktpInput.files[0]) return;
      var fd = new FormData();
      fd.append('_token', '{{ csrf_token() }}');
      fd.append('ktp_file', ktpInput.files[0]);
      var ocrHint = document.getElementById('ktp_ocr_hint');
      if (ocrHint) {
        ocrHint.textContent = 'Membaca alamat dari file KTP...';
      }
      fetch('{{ route('employees.draft_upload') }}', {
        method: 'POST',
        body: fd
      }).then(function (res) {
        if (!res.ok) throw new Error('Upload KTP gagal.');
        return res.json();
      }).then(function (json) {
        var paths = json.paths || {};
        var ocr = json.ocr || {};
        if (paths.ktp_file) {
          document.getElementById('draft_ktp_path').value = paths.ktp_file;
          var hint = document.getElementById('draft_ktp_hint');
          if (hint) {
            hint.innerHTML = 'Draft: <a class="doc-link" href="/' + paths.ktp_file + '" target="_blank">Lihat file</a>';
          }
        }
        if (ocr.ktp_address) {
          var addr = form.querySelector('[name="address_ktp"]');
          if (addr) addr.value = ocr.ktp_address;
          if (ocrHint) ocrHint.textContent = 'Alamat KTP berhasil dibaca otomatis.';
        } else if (ocrHint) {
          ocrHint.textContent = ocr.ktp_reason || 'Alamat KTP belum berhasil dibaca otomatis. Isi manual bila perlu.';
        }
      }).catch(function () {
        if (ocrHint) {
          ocrHint.textContent = 'OCR KTP gagal diproses. Isi alamat manual.';
        }
      });
    }

    function readStoredKtpAndFillAddress(force) {
      var existingPath = document.getElementById('draft_ktp_path').value || document.getElementById('current_ktp_path').value;
      if (!existingPath) return;
      var fd = new FormData();
      fd.append('_token', '{{ csrf_token() }}');
      fd.append('existing_ktp_path', existingPath);
      if (force) {
        fd.append('force_ocr', '1');
      }
      var ocrHint = document.getElementById('ktp_ocr_hint');
      if (ocrHint) {
        ocrHint.textContent = force ? 'Paksa OCR ulang dari KTP tersimpan...' : 'Membaca alamat dari KTP tersimpan...';
      }
      fetch('{{ route('employees.draft_upload') }}', {
        method: 'POST',
        body: fd
      }).then(function (res) {
        if (!res.ok) throw new Error('Baca KTP tersimpan gagal.');
        return res.json();
      }).then(function (json) {
        var ocr = json.ocr || {};
        if (ocr.ktp_address) {
          var addr = form.querySelector('[name="address_ktp"]');
          if (addr) addr.value = ocr.ktp_address;
          if (ocrHint) ocrHint.textContent = 'Alamat KTP berhasil dibaca dari file tersimpan.';
        } else if (ocrHint) {
          ocrHint.textContent = ocr.ktp_reason || 'Alamat KTP dari file tersimpan belum berhasil dibaca otomatis.';
        }
      }).catch(function () {
        if (ocrHint) {
          ocrHint.textContent = 'Baca KTP tersimpan gagal. Isi alamat manual atau upload ulang file KTP.';
        }
      });
    }

    function loadDraft() {
      var editIdEl = form.querySelector('[name="id"]');
      if (editIdEl && editIdEl.value) {
        return;
      }
      var raw = localStorage.getItem(draftKey);
      if (!raw) return;
      try {
        var data = JSON.parse(raw);
        fieldNames.forEach(function (name) {
          if (!(name in data)) return;
          var el = form.querySelector('[name="' + name + '"]');
          if (el) el.value = data[name];
        });
        setDraftHints(data);
      } catch (e) {}
    }

    btnDraft.addEventListener('click', saveDraft);
    var ktpInput = form.querySelector('[name="ktp_file"]');
    if (ktpInput) {
      ktpInput.addEventListener('change', uploadKtpAndFillAddress);
    }
    var btnReadStoredKtp = document.getElementById('btnReadStoredKtp');
    if (btnReadStoredKtp) {
      btnReadStoredKtp.addEventListener('click', readStoredKtpAndFillAddress);
    }
    var btnForceOcrKtp = document.getElementById('btnForceOcrKtp');
    if (btnForceOcrKtp) {
      btnForceOcrKtp.addEventListener('click', function () {
        readStoredKtpAndFillAddress(true);
      });
    }
    loadDraft();
    var addressInput = form.querySelector('[name="address_ktp"]');
    if (addressInput && !addressInput.value) {
      readStoredKtpAndFillAddress();
    }

    var companySelect = document.getElementById('company_id');
    var placementWrap = document.getElementById('placement_company_wrap');
    var placementSelect = document.getElementById('placement_company_id');
    function syncPlacementVisibility() {
      if (!placementWrap) return;
      var resourceId = parseInt(placementWrap.getAttribute('data-resource-company-id') || '0', 10);
      if (!resourceId) return;
      var activeCompanyId = companySelect ? parseInt(companySelect.value || '0', 10) : {{ (int) ($companyId ?? 0) }};
      var show = activeCompanyId === resourceId;
      placementWrap.classList.toggle('d-none', !show);
      if (!show && placementSelect) {
        placementSelect.value = '';
      }
    }
    if (companySelect) {
      companySelect.addEventListener('change', syncPlacementVisibility);
    }
    syncPlacementVisibility();
  })();
</script>
<script>
  (function () {
    var wrap = document.getElementById('hrdDocsWrap');
    var list = document.getElementById('hrdDocsList');
    var template = document.getElementById('hrdDocsTemplate');
    var addBtn = document.getElementById('btnAddHrdDoc');
    if (!wrap || !list || !template || !addBtn) return;

    var index = 0;

    function addRow() {
      var row = template.cloneNode(true);
      row.removeAttribute('id');
      row.style.display = '';

      var nameInput = row.querySelector('[data-hrd-name]');
      var fileInput = row.querySelector('[data-hrd-file]');
      var removeBtn = row.querySelector('[data-hrd-remove]');
      if (!nameInput || !fileInput || !removeBtn) return;

      nameInput.setAttribute('name', 'hrd_docs[' + index + '][name]');
      fileInput.setAttribute('name', 'hrd_docs[' + index + '][file]');
      index++;

      removeBtn.addEventListener('click', function () {
        row.remove();
      });

      list.appendChild(row);
    }

    addBtn.addEventListener('click', addRow);
    addRow();
  })();
</script>
@endsection
