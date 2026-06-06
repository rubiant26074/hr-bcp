<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\EmployeeActiveStatus;
use App\Models\EmployeeMutation;
use App\Models\EmployeeStatus;
use App\Models\EmployeeType;
use App\Models\EmployeePosition;
use App\Models\EmployeeGrade;
use App\Models\EmployeeDepartment;
use App\Models\EmployeeDocument;
use App\Services\KtpOcrService;
use App\Services\EmployeeContractSyncService;
use App\Services\PayrollService;
use App\Services\PayrollSettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;

class EmployeesController extends Controller
{
    private function firstByNormalizedValue(string $modelClass, string $column, string $value)
    {
        return $modelClass::query()
            ->whereRaw('LOWER(' . $column . ') = ?', [mb_strtolower($value)])
            ->first();
    }

    private function listGlobalOrCompany($query, int $companyId, string $orderBy)
    {
        $global = (clone $query)->where('company_id', 0)->orderBy($orderBy)->get();
        if ($global->isNotEmpty()) {
            return $global;
        }
        return (clone $query)->where('company_id', $companyId)->orderBy($orderBy)->get();
    }

    public function index(Request $request)
    {
        $user = current_user();
        $allowedViewModes = ['active_all', 'active_tetap', 'active_kontrak', 'active_percobaan', 'active_harian', 'active_komisaris', 'archive', 'mutasi'];
        $requestedView = trim((string) $request->query('view', $request->input('view', '')));
        $sessionView = trim((string) session('employees_view_mode', 'active_tetap'));

        if ($requestedView !== '' && in_array($requestedView, $allowedViewModes, true)) {
            $viewMode = $requestedView;
            session(['employees_view_mode' => $viewMode]);
        } else {
            $viewMode = in_array($sessionView, $allowedViewModes, true) ? $sessionView : 'active_tetap';
            session(['employees_view_mode' => $viewMode]);
        }

        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            return redirect()->route('employees.index', ['view' => $viewMode]);
        }
        $companyId = current_company_id();

        if ($request->isMethod('post') && $request->input('action') === 'delete') {
            $deleteId = (int) $request->input('id');
            $target = Employee::find($deleteId);
            if ($target) {
                if ($user['role'] === 'Super Admin' || (int)$target->company_id === (int)$companyId) {
                    $target->delete();
                }
            }
            return redirect()->route('employees.index', ['view' => $viewMode]);
        }

        if ($request->isMethod('post') && $request->input('action') === 'delete_bulk') {
            $ids = collect((array) $request->input('ids', []))
                ->map(static fn ($v) => (int) $v)
                ->filter(static fn ($v) => $v > 0)
                ->unique()
                ->values();

            if ($ids->isNotEmpty()) {
                $bulkQuery = Employee::query()->whereIn('id', $ids->all());
                if (($user['role'] ?? '') !== 'Super Admin') {
                    $bulkQuery->where('company_id', $companyId);
                }
                $bulkQuery->delete();
            }

            return redirect()->back();
        }

        if ($request->isMethod('post') && $request->input('action') === 'delete_mutation') {
            if (($user['role'] ?? '') !== 'Super Admin') {
                abort(403, 'Hanya admin yang dapat menghapus arsip mutasi.');
            }
            if (!Schema::hasTable((new EmployeeMutation())->getTable())) {
                abort(500, 'Table employee_mutations belum ada. Jalankan migration terlebih dahulu.');
            }

            $mutationId = (int) $request->input('mutation_id', 0);
            if ($mutationId > 0) {
                $mutation = EmployeeMutation::find($mutationId);
                if ($mutation) {
                    if ((int) $mutation->from_company_id === (int) $companyId || current_user_has_global_scope($user)) {
                        $mutation->delete();
                    }
                }
            }

            return redirect()->route('employees.index', ['view' => $viewMode === 'mutasi' ? 'mutasi' : $viewMode]);
        }

        $q = trim((string) $request->query('q', ''));
        $filterStatus = trim((string) $request->query('status', ''));
        $filterType = trim((string) $request->query('type', ''));
        if (!in_array($viewMode, $allowedViewModes, true)) {
            $viewMode = 'active_tetap';
        }
        $archiveStatuses = Employee::archiveActiveStatuses();
        $mutasiTableReady = Schema::hasTable((new EmployeeMutation())->getTable());
        $companies = Company::orderBy('id')->get();
        $mutationsByEmployee = collect();
        if ($viewMode === 'mutasi') {
            if ($mutasiTableReady) {
                $latestIds = EmployeeMutation::query()
                    ->selectRaw('MAX(id) as id')
                    ->where('from_company_id', $companyId)
                    ->groupBy('employee_id')
                    ->pluck('id')
                    ->filter()
                    ->values()
                    ->all();

                $latestMutations = EmployeeMutation::query()
                    ->whereIn('id', $latestIds)
                    ->with(['toCompany'])
                    ->orderByDesc('id')
                    ->get();

                $mutationsByEmployee = $latestMutations->keyBy('employee_id');
                $employeeIds = $latestMutations->pluck('employee_id')->values()->all();
                $employeesQuery = Employee::query()->whereIn('id', $employeeIds);
            } else {
                $employeesQuery = Employee::query()->whereRaw('1=0');
            }
        } else {
            $employeesQuery = Employee::where('company_id', $companyId);
            if ($viewMode === 'archive') {
                $employeesQuery->whereIn('active_status', $archiveStatuses);
            } else {
                $employeesQuery->where(function ($query) use ($archiveStatuses) {
                    $query->whereNull('active_status')
                        ->orWhere('active_status', '')
                        ->orWhereNotIn('active_status', $archiveStatuses);
                });

                if ($viewMode === 'active_tetap') {
                    $employeesQuery->whereRaw('LOWER(COALESCE(employment_status, "")) LIKE ?', ['%tetap%']);
                } elseif ($viewMode === 'active_kontrak') {
                    $employeesQuery->whereRaw('LOWER(COALESCE(employment_status, "")) LIKE ?', ['%kontrak%']);
                } elseif ($viewMode === 'active_percobaan') {
                    $employeesQuery->where(function ($q) {
                        $q->whereRaw('LOWER(COALESCE(employment_status, "")) LIKE ?', ['%percobaan%'])
                            ->orWhereRaw('LOWER(COALESCE(employment_status, "")) LIKE ?', ['%magang%']);
                    });
                } elseif ($viewMode === 'active_harian') {
                    $employeesQuery->whereRaw('LOWER(COALESCE(employment_status, "")) LIKE ?', ['%harian%']);
                } elseif ($viewMode === 'active_komisaris') {
                    $employeesQuery->whereRaw('LOWER(TRIM(COALESCE(employment_status, ""))) = ?', ['komisaris']);
                } elseif ($viewMode === 'active_all') {
                    // Semua status karyawan aktif dalam company terpilih (tetap dipisah per entitas).
                }
            }
        }
        if ($q !== '') {
            $like = '%' . $q . '%';
            $employeesQuery->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('nik', 'like', $like)
                    ->orWhere('position', 'like', $like);
            });
        }
        if ($filterStatus !== '') {
            $employeesQuery->where('employment_status', $filterStatus);
        }
        if ($filterType !== '') {
            $employeesQuery->where('employee_type', $filterType);
        }
        $employees = $employeesQuery->with(['company', 'placementCompany'])->orderBy('name')->get();

        return view('modules.employees.index', compact('user', 'companyId', 'companies', 'employees', 'q', 'filterStatus', 'filterType', 'viewMode', 'mutationsByEmployee', 'mutasiTableReady'));
    }

    public function form(Request $request)
    {
        $user = current_user();
        $role = (string) ($user['role'] ?? '');
        $canProfile = rbac_key_allowed($role, 'employees_form_profile');
        $canPayroll = rbac_key_allowed($role, 'employees_form_payroll');
        $companyId = current_company_id();
        $companies = Company::orderBy('id')->get();
        $statuses = $this->listGlobalOrCompany(EmployeeStatus::query(), $companyId, 'id');
        $types = EmployeeType::orderBy('id')->get();
        $positions = $this->listGlobalOrCompany(EmployeePosition::query(), $companyId, 'id');
        $grades = $this->listGlobalOrCompany(EmployeeGrade::query(), $companyId, 'id');
        $departments = $this->listGlobalOrCompany(EmployeeDepartment::query(), $companyId, 'id');
        $activeStatusOptions = Employee::activeStatusOptions();

        $edit = null;
        $payroll = null;
        $absencePreview = ['amount' => 0, 'absence_days' => 0, 'workdays' => 0, 'excused_days' => 0, 'period_label' => '-'];
        if ($request->has('edit')) {
            $edit = Employee::find((int) $request->query('edit'));
            if ($edit) {
                if (!current_user_has_global_scope($user) && (int)$edit->company_id !== (int)$companyId) {
                    abort(403, 'Access denied.');
                }
                $payroll = PayrollSettingService::getByEmployee($edit->id);
                $companyId = (int) $edit->company_id;
                $statuses = $this->listGlobalOrCompany(EmployeeStatus::query(), $companyId, 'id');
                $types = EmployeeType::orderBy('id')->get();
                $positions = $this->listGlobalOrCompany(EmployeePosition::query(), $companyId, 'id');
                $grades = $this->listGlobalOrCompany(EmployeeGrade::query(), $companyId, 'id');
                $departments = $this->listGlobalOrCompany(EmployeeDepartment::query(), $companyId, 'id');
                $activeStatusOptions = Employee::activeStatusOptions();
                $absencePreview = PayrollService::absencePreviewForEmployee((int) $edit->id, (float) ($payroll->basic_salary ?? 0));
            }
        }

        if (!$canProfile && !$canPayroll) {
            abort(403, 'Access denied.');
        }

        if ($request->isMethod('post')) {
            $companyId = (int) $request->input('company_id', $companyId);
            $validated = [];
            if ($canProfile) {
                $validated = $request->validate([
                    'nik_ktp' => ['nullable','string','max:32'],
                    'address_ktp' => ['nullable','string','max:2000'],
                    'domicile_address' => ['nullable','string','max:2000'],
                    'name' => ['required','string','max:255'],
                    'active_status' => ['nullable','string','max:100'],
                    'place_of_birth' => ['nullable','string','max:120'],
                    'date_of_birth' => ['nullable','date'],
                    'phone' => ['nullable','string','max:30'],
                    'emergency_contact_number' => ['nullable','string','max:30'],
                    'npwp' => ['nullable','string','max:50'],
                    'bank_name' => ['nullable','string','max:120'],
                    'bank_account_no' => ['nullable','string','max:50'],
                    'ptkp_status' => ['required','string','max:10'],
                    'employment_status' => ['nullable','string','max:100'],
                    'new_employment_status' => ['nullable','string','max:100'],
                    'employee_type' => ['nullable','string','max:50'],
                    'new_employee_type' => ['nullable','string','max:50'],
                    'department' => ['nullable','string','max:120'],
                    'new_department' => ['nullable','string','max:120'],
                    'position' => ['nullable','string','max:120'],
                    'new_position' => ['nullable','string','max:120'],
                    'grade' => ['nullable','string','max:120'],
                    'new_grade' => ['nullable','string','max:120'],
                    'join_date' => ['nullable','date'],
                    'contract_end' => ['nullable','date'],
                    'placement_company_id' => ['nullable', 'integer', 'exists:companies,id'],
                ]);
                $validated['active_status'] = trim((string) ($validated['active_status'] ?? Employee::ACTIVE_STATUS_ACTIVE));
                if (!in_array($validated['active_status'], $activeStatusOptions, true)) {
                    $validated['active_status'] = Employee::ACTIVE_STATUS_ACTIVE;
                }

                $newEmploymentStatus = trim((string) ($validated['new_employment_status'] ?? ''));
                if ($newEmploymentStatus !== '') {
                    $existingEmploymentStatus = $this->firstByNormalizedValue(EmployeeStatus::class, 'status_name', $newEmploymentStatus);
                    if (!$existingEmploymentStatus) {
                        EmployeeStatus::create([
                            'company_id' => 0,
                            'status_name' => $newEmploymentStatus,
                            'note' => 'Ditambahkan dari form employee',
                        ]);
                    }
                    $validated['employment_status'] = $newEmploymentStatus;
                }

                $newEmployeeType = trim((string) ($validated['new_employee_type'] ?? ''));
                if ($newEmployeeType !== '') {
                    $existingEmployeeType = $this->firstByNormalizedValue(EmployeeType::class, 'type_name', $newEmployeeType);
                    if (!$existingEmployeeType) {
                        EmployeeType::create([
                            'type_name' => $newEmployeeType,
                        ]);
                    }
                    $validated['employee_type'] = $newEmployeeType;
                }

                $newDepartment = trim((string) ($validated['new_department'] ?? ''));
                if ($newDepartment !== '') {
                    $existingDepartment = $this->firstByNormalizedValue(EmployeeDepartment::class, 'department_name', $newDepartment);
                    if (!$existingDepartment) {
                        EmployeeDepartment::create([
                            'company_id' => 0,
                            'department_name' => $newDepartment,
                            'note' => 'Ditambahkan dari form employee',
                        ]);
                    }
                    $validated['department'] = $newDepartment;
                }

                $newPosition = trim((string) ($validated['new_position'] ?? ''));
                if ($newPosition !== '') {
                    $existingPosition = $this->firstByNormalizedValue(EmployeePosition::class, 'position_name', $newPosition);
                    if (!$existingPosition) {
                        EmployeePosition::create([
                            'company_id' => 0,
                            'position_name' => $newPosition,
                            'note' => 'Ditambahkan dari form employee',
                        ]);
                    }
                    $validated['position'] = $newPosition;
                }

                $newGrade = trim((string) ($validated['new_grade'] ?? ''));
                if ($newGrade !== '') {
                    $existingGrade = $this->firstByNormalizedValue(EmployeeGrade::class, 'grade_name', $newGrade);
                    if (!$existingGrade) {
                        EmployeeGrade::create([
                            'company_id' => 0,
                            'grade_name' => $newGrade,
                            'note' => 'Ditambahkan dari form employee',
                        ]);
                    }
                    $validated['grade'] = $newGrade;
                }

                if (($validated['employment_status'] ?? '') === '__new__') {
                    $validated['employment_status'] = '';
                }
                if (($validated['employee_type'] ?? '') === '__new__') {
                    $validated['employee_type'] = '';
                }
                if (($validated['department'] ?? '') === '__new__') {
                    $validated['department'] = '';
                }
                if (($validated['position'] ?? '') === '__new__') {
                    $validated['position'] = '';
                }
                if (($validated['grade'] ?? '') === '__new__') {
                    $validated['grade'] = '';
                }
            }

            $photoPath = null;
            $docPaths = [
                'ktp_path' => null,
                'ijazah_path' => null,
                'surat_lamaran_path' => null,
                'cv_file_path' => null,
                'mcu_file_path' => null,
                'kk_path' => null,
                'npwp_path' => null,
                'skck_path' => null,
            ];
            $existing = null;
            if ($request->filled('id')) {
                $existing = Employee::find((int) $request->input('id'));
                if (!$existing) {
                    abort(404, 'Employee not found.');
                }
                if (!current_user_has_global_scope($user) && (int)$existing->company_id !== (int)$companyId) {
                    abort(403, 'Access denied.');
                }
                $photoPath = $existing->photo_path ?? null;
                $docPaths = [
                    'ktp_path' => $existing->ktp_path ?? null,
                    'ijazah_path' => $existing->ijazah_path ?? null,
                    'surat_lamaran_path' => $existing->surat_lamaran_path ?? null,
                    'cv_file_path' => $existing->cv_file_path ?? null,
                    'mcu_file_path' => $existing->mcu_file_path ?? null,
                    'kk_path' => $existing->kk_path ?? null,
                    'npwp_path' => $existing->npwp_path ?? null,
                    'skck_path' => $existing->skck_path ?? null,
                ];
            }

            $employeeId = 0;
            if (!$canProfile) {
                $employeeId = (int) $request->input('id', 0);
                if ($employeeId <= 0) {
                    abort(400, 'Employee wajib dipilih.');
                }
                $existing = Employee::find($employeeId);
                if (!$existing) {
                    abort(404, 'Employee not found.');
                }
                if (!current_user_has_global_scope($user) && (int)$existing->company_id !== (int)$companyId) {
                    abort(403, 'Access denied.');
                }
            } else {
            $draftPhoto = trim((string) $request->input('draft_photo_path', ''));
            if ($draftPhoto !== '' && str_starts_with($draftPhoto, 'uploads/employees/drafts/')) {
                $photoPath = $draftPhoto;
            }
            $currentPhoto = trim((string) $request->input('current_photo_path', ''));
            if (empty($photoPath) && $currentPhoto !== '' && str_starts_with($currentPhoto, 'uploads/employees/')) {
                $photoPath = $currentPhoto;
            }
            $draftDocMap = [
                'ktp_path' => trim((string) $request->input('draft_ktp_path', '')),
                'ijazah_path' => trim((string) $request->input('draft_ijazah_path', '')),
                'surat_lamaran_path' => trim((string) $request->input('draft_surat_lamaran_path', '')),
                'cv_file_path' => trim((string) $request->input('draft_cv_file_path', '')),
                'mcu_file_path' => trim((string) $request->input('draft_mcu_file_path', '')),
                'kk_path' => trim((string) $request->input('draft_kk_path', '')),
                'npwp_path' => trim((string) $request->input('draft_npwp_path', '')),
                'skck_path' => trim((string) $request->input('draft_skck_path', '')),
            ];
            foreach ($draftDocMap as $key => $val) {
                if ($val !== '' && str_starts_with($val, 'uploads/employees/drafts/')) {
                    $docPaths[$key] = $val;
                }
            }
            $currentDocMap = [
                'ktp_path' => trim((string) $request->input('current_ktp_path', '')),
                'ijazah_path' => trim((string) $request->input('current_ijazah_path', '')),
                'surat_lamaran_path' => trim((string) $request->input('current_surat_lamaran_path', '')),
                'cv_file_path' => trim((string) $request->input('current_cv_file_path', '')),
                'mcu_file_path' => trim((string) $request->input('current_mcu_file_path', '')),
                'kk_path' => trim((string) $request->input('current_kk_path', '')),
                'npwp_path' => trim((string) $request->input('current_npwp_path', '')),
                'skck_path' => trim((string) $request->input('current_skck_path', '')),
            ];
            foreach ($currentDocMap as $key => $val) {
                if (empty($docPaths[$key]) && $val !== '' && str_starts_with($val, 'uploads/employees/')) {
                    $docPaths[$key] = $val;
                }
            }

            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                if (!$file->isValid()) {
                    abort(400, 'Upload foto gagal.');
                }
                if ($file->getSize() > 5 * 1024 * 1024) {
                    abort(400, 'Foto terlalu besar (maks 5MB).');
                }
                $ext = strtolower($file->getClientOriginalExtension());
                $allowed = ['jpg','jpeg','png'];
                $mime = $file->getMimeType();
                $allowedMime = ['image/jpeg','image/png'];
                if (!in_array($ext, $allowed, true) || !in_array($mime, $allowedMime, true)) {
                    abort(400, 'Format foto harus JPG/PNG.');
                }
                $dir = public_path('uploads/employees');
                if (!File::exists($dir)) {
                    File::makeDirectory($dir, 0755, true);
                }
                $filename = 'emp_' . uniqid() . '.' . $ext;
                $file->move($dir, $filename);
                $photoPath = 'uploads/employees/' . $filename;
            }

            $docDefs = [
                ['input' => 'ktp_file', 'column' => 'ktp_path', 'label' => 'KTP', 'required' => false],
                ['input' => 'ijazah_file', 'column' => 'ijazah_path', 'label' => 'Ijazah', 'required' => false],
                ['input' => 'surat_lamaran_file', 'column' => 'surat_lamaran_path', 'label' => 'Surat Lamaran Kerja', 'required' => false],
                ['input' => 'cv_file', 'column' => 'cv_file_path', 'label' => 'CV', 'required' => false],
                ['input' => 'mcu_file', 'column' => 'mcu_file_path', 'label' => 'MCU/Surat Sehat', 'required' => false],
                ['input' => 'kk_file', 'column' => 'kk_path', 'label' => 'KK', 'required' => false],
                ['input' => 'npwp_file', 'column' => 'npwp_path', 'label' => 'NPWP', 'required' => false],
                ['input' => 'skck_file', 'column' => 'skck_path', 'label' => 'SKCK', 'required' => false],
            ];
            $docDir = public_path('uploads/employees/docs');
            if (!File::exists($docDir)) {
                File::makeDirectory($docDir, 0755, true);
            }
            foreach ($docDefs as $def) {
                $input = $def['input'];
                $column = $def['column'];
                $label = $def['label'];
                if ($request->hasFile($input)) {
                    $file = $request->file($input);
                    if (!$file->isValid()) {
                        abort(400, "Upload {$label} gagal.");
                    }
                    if ($file->getSize() > 5 * 1024 * 1024) {
                        abort(400, "{$label} terlalu besar (maks 5MB).");
                    }
                    $ext = strtolower($file->getClientOriginalExtension());
                    $allowed = ['jpg','jpeg','png','pdf'];
                    $mime = $file->getMimeType();
                    $allowedMime = ['image/jpeg','image/png','application/pdf'];
                    if (!in_array($ext, $allowed, true) || !in_array($mime, $allowedMime, true)) {
                        abort(400, "Format {$label} harus JPG/PNG/PDF.");
                    }
                    $filename = 'doc_' . $column . '_' . uniqid() . '.' . $ext;
                    $file->move($docDir, $filename);
                    $docPaths[$column] = 'uploads/employees/docs/' . $filename;
                } elseif ($def['required'] && empty($docPaths[$column])) {
                    abort(400, "Upload {$label} wajib.");
                }
            }

            if (!empty($photoPath) && str_starts_with($photoPath, 'uploads/employees/drafts/')) {
                $src = public_path($photoPath);
                if (File::exists($src)) {
                    $ext = pathinfo($photoPath, PATHINFO_EXTENSION) ?: 'jpg';
                    $finalName = 'emp_' . uniqid() . '.' . $ext;
                    $finalPath = 'uploads/employees/' . $finalName;
                    File::move($src, public_path($finalPath));
                    $photoPath = $finalPath;
                }
            }
            foreach ($docPaths as $key => $val) {
                if (!empty($val) && str_starts_with($val, 'uploads/employees/drafts/')) {
                    $src = public_path($val);
                    if (File::exists($src)) {
                        $ext = pathinfo($val, PATHINFO_EXTENSION) ?: 'pdf';
                        $finalName = 'doc_' . $key . '_' . uniqid() . '.' . $ext;
                        $finalPath = 'uploads/employees/docs/' . $finalName;
                        File::move($src, public_path($finalPath));
                        $docPaths[$key] = $finalPath;
                    }
                }
            }

            $nikValue = $existing->nik ?? '';
            if ($nikValue === '') {
                $nikValue = Employee::generateNik($companyId, $request->input('join_date'), $request->filled('id') ? (int) $request->input('id') : null);
            }

            $resourceCompanyId = (int) (Company::query()
                ->whereRaw('LOWER(TRIM(company_name)) = ?', ['pt. resource mitra bersama'])
                ->value('id') ?? 0);
            $placementCompanyId = (int) ($validated['placement_company_id'] ?? 0);
            if ($resourceCompanyId <= 0 || $companyId !== $resourceCompanyId) {
                $placementCompanyId = 0;
            }
            $placementCompanyId = $placementCompanyId > 0 ? $placementCompanyId : null;

            $data = [
                'company_id' => $companyId,
                'placement_company_id' => $placementCompanyId,
                'nik' => $nikValue,
                'nik_ktp' => $validated['nik_ktp'] ?? '',
                'address_ktp' => $validated['address_ktp'] ?? '',
                'domicile_address' => $validated['domicile_address'] ?? '',
                    'name' => $validated['name'],
                    'active_status' => $validated['active_status'] ?? 'Active',
                'place_of_birth' => $validated['place_of_birth'] ?? '',
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'phone' => $validated['phone'] ?? '',
                'emergency_contact_number' => $validated['emergency_contact_number'] ?? '',
                'npwp' => $validated['npwp'] ?? '',
                'bank_name' => $validated['bank_name'] ?? '',
                'bank_account_no' => $validated['bank_account_no'] ?? '',
                'ptkp_status' => $validated['ptkp_status'],
                'employment_status' => $validated['employment_status'] ?? '',
                'employee_type' => $validated['employee_type'] ?? '',
                'department' => $validated['department'] ?? '',
                'position' => $validated['position'] ?? '',
                'grade' => $validated['grade'] ?? '',
                'join_date' => $validated['join_date'] ?? null,
                'contract_end' => $validated['contract_end'] ?? null,
                'photo_path' => $photoPath,
                'ktp_path' => $docPaths['ktp_path'],
                'ijazah_path' => $docPaths['ijazah_path'],
                'surat_lamaran_path' => $docPaths['surat_lamaran_path'],
                'cv_file_path' => $docPaths['cv_file_path'],
                'mcu_file_path' => $docPaths['mcu_file_path'],
                'kk_path' => $docPaths['kk_path'],
                'npwp_path' => $docPaths['npwp_path'],
                'skck_path' => $docPaths['skck_path'],
            ];

            if (!empty($request->input('id'))) {
                $existing->fill($data);
                $existing->save();
                $employeeId = (int) $request->input('id');
                app(EmployeeContractSyncService::class)->syncEmployee($existing->fresh());
            } else {
                $createdEmployee = Employee::create($data);
                $employeeId = (int) $createdEmployee->id;
                app(EmployeeContractSyncService::class)->syncEmployee($createdEmployee);
            }

            if ($canProfile && ($validated['active_status'] ?? '') === Employee::ACTIVE_STATUS_MUTASI) {
                if (!$request->filled('id')) {
                    abort(400, 'Mutasi hanya bisa dilakukan untuk karyawan yang sudah tersimpan.');
                }
                $toCompanyId = (int) $request->input('mutasi_to_company_id', 0);
                if ($toCompanyId <= 0 || $toCompanyId === (int) $companyId) {
                    abort(400, 'Pilih perusahaan tujuan mutasi.');
                }
                $note = trim((string) $request->input('mutasi_note', ''));
                $this->mutateEmployee($employeeId, (int) $companyId, $toCompanyId, $user, $note);
            }

            // Handle multiple HRD document uploads
            if ($request->filled('delete_hrd_docs')) {
                $deleteIds = $request->input('delete_hrd_docs', []);
                if (is_array($deleteIds) && count($deleteIds) > 0) {
                    \App\Models\EmployeeDocument::where('employee_id', $employeeId)
                        ->whereIn('id', $deleteIds)
                        ->delete(); // Note: This doesn't delete the files from storage
                }
            }
            if ($request->has('hrd_docs')) {
                $hrdDocs = $request->input('hrd_docs', []);
                $hrdDocFiles = $request->file('hrd_docs', []);
                $hrdDocDir = public_path('uploads/employees/hrd_docs');
                if (!File::exists($hrdDocDir)) {
                    File::makeDirectory($hrdDocDir, 0755, true);
                }

                foreach ($hrdDocs as $index => $docInfo) {
                    if (empty($docInfo['name']) || empty($hrdDocFiles[$index]['file'])) {
                        continue;
                    }

                    $file = $hrdDocFiles[$index]['file'];
                    if (!$file->isValid()) {
                        continue;
                    }

                    if ($file->getSize() > 5 * 1024 * 1024) {
                        abort(400, "Dokumen HRD '{$docInfo['name']}' terlalu besar (maks 5MB).");
                    }
                    $ext = strtolower($file->getClientOriginalExtension());
                    $allowed = ['jpg','jpeg','png','pdf','doc','docx','xls','xlsx'];
                    if (!in_array($ext, $allowed, true)) {
                        abort(400, "Format Dokumen HRD '{$docInfo['name']}' tidak valid.");
                    }

                    $filename = 'hrd_doc_' . $employeeId . '_' . uniqid() . '.' . $ext;
                    $file->move($hrdDocDir, $filename);
                    $hrdDocPath = 'uploads/employees/hrd_docs/' . $filename;

                    \App\Models\EmployeeDocument::create([
                        'employee_id' => $employeeId,
                        'document_name' => $docInfo['name'],
                        'file_path' => $hrdDocPath,
                    ]);
                }
            }
            }

            if ($canPayroll) {
            $employmentStatusForBpjs = strtoupper(trim((string) (
                $request->input('employment_status')
                ?? ($validated['employment_status'] ?? null)
                ?? ($existing->employment_status ?? '')
            )));
            $companyForPayroll = Company::find($companyId);
            $bpjsHealthPct = (float) ($companyForPayroll->bpjs_health_pct ?? 1);
            $jhtPct = (float) ($companyForPayroll->bpjs_jht_pct ?? 2);
            $jpPct = (float) ($companyForPayroll->bpjs_jp_pct ?? 1);
            $basicSalary = (float) $request->input('basic_salary', 0);
            $bpjsHealth = $employmentStatusForBpjs === 'HARIAN'
                ? 0
                : round($basicSalary * $bpjsHealthPct / 100, 2);
            $jht = $employmentStatusForBpjs === 'HARIAN'
                ? 0
                : round($basicSalary * $jhtPct / 100, 2);
            $jp = $employmentStatusForBpjs === 'HARIAN'
                ? 0
                : round($basicSalary * $jpPct / 100, 2);
            $allowTaxAndBpjsAllowance = in_array($employmentStatusForBpjs, ['TETAP ALL-IN', 'KOMISARIS'], true);
            $taxAllowanceAuto = $allowTaxAndBpjsAllowance ? (float) $request->input('b7_pph21', 0) : 0.0;
            $bpjsAllowanceAuto = $allowTaxAndBpjsAllowance ? (float) ($bpjsHealth + $jht + $jp) : 0.0;

            $payrollData = [
                'basic_salary' => $basicSalary,
                'a2_overtime' => $request->input('a2_overtime', 0),
                'a2_overtime_flat' => $request->input('a2_overtime_flat', 0),
                'overtime_mode' => $request->input('overtime_mode', 'auto'),
                'overtime_manual_hours' => $request->input('overtime_manual_hours', 0),
                'overtime_manual_hour_1' => $request->input('overtime_manual_hour_1', 0),
                'overtime_manual_hour_2' => $request->input('overtime_manual_hour_2', 0),
                'overtime_manual_holiday_8' => $request->input('overtime_manual_holiday_8', 0),
                'overtime_manual_holiday_9' => $request->input('overtime_manual_holiday_9', 0),
                'a3_meal' => $request->input('a3_meal', 0),
                'a4_transport' => $request->input('a4_transport', 0),
                'a5_performance' => $request->input('a5_performance', 0),
                'a6_position' => $request->input('a6_position', 0),
                'a7_family' => $request->input('a7_family', 0),
                'a8_communication' => $request->input('a8_communication', 0),
                'a9_other' => $request->input('a9_other', 0),
                'a10_thr' => $request->input('a10_thr', 0),
                'a11_bonus' => $request->input('a11_bonus', 0),
                'a12_rapel_gaji' => $request->input('a12_rapel_gaji', 0),
                'a12_tax_allowance' => $taxAllowanceAuto,
                'a13_bpjs_allowance' => $bpjsAllowanceAuto,
                'b1_loan' => $request->input('b1_loan', 0),
                'b2_absence' => 0,
                'b3_subsidy' => $request->input('b3_subsidy', 0),
                'b4_bpjs_health' => $bpjsHealth,
                'b5_jht' => $jht,
                'b6_jp' => $jp,
                'b7_pph21' => $request->input('b7_pph21', 0),
                'b8_other' => $request->input('b8_other', 0),
            ];
            $autoAbsence = PayrollService::absencePreviewForEmployee($employeeId, (float) $payrollData['basic_salary']);
            $payrollData['b2_absence'] = $autoAbsence['amount'];
            PayrollSettingService::upsert($employeeId, $payrollData);
            }

            $allowedViewModes = ['active_all', 'active_tetap', 'active_kontrak', 'active_percobaan', 'active_harian', 'active_komisaris', 'archive', 'mutasi'];
            $redirectView = trim((string) $request->query('view', $request->input('view', '')));
            if (!in_array($redirectView, $allowedViewModes, true)) {
                $sessionView = trim((string) session('employees_view_mode', 'active_tetap'));
                $redirectView = in_array($sessionView, $allowedViewModes, true) ? $sessionView : 'active_tetap';
            }

            session(['employees_view_mode' => $redirectView]);
            return redirect()->route('employees.index', ['view' => $redirectView]);
        }

        $documents = $edit ? EmployeeDocument::where('employee_id', $edit->id)->orderBy('id')->get() : collect();

        return view('modules.employees.form', compact('user', 'companyId', 'companies', 'statuses', 'types', 'positions', 'grades', 'departments', 'edit', 'payroll', 'absencePreview', 'documents', 'canProfile', 'canPayroll', 'activeStatusOptions'));
    }

    public function detail(Request $request, int $id)
    {
        $employee = Employee::with('company')->find($id);
        if (!$employee) {
            abort(404, 'Employee not found');
        }
        $user = current_user();
        if (!current_user_has_global_scope($user) && (int)$employee->company_id !== (int)current_company_id()) {
            $allowMutasiView = false;
            if (Schema::hasTable((new EmployeeMutation())->getTable())) {
                $allowMutasiView = EmployeeMutation::query()
                    ->where('employee_id', $employee->id)
                    ->where('from_company_id', current_company_id())
                    ->exists();
            }
            if (!$allowMutasiView) {
            abort(403, 'Access denied.');
            }
        }
        $contracts = collect();
        if (Schema::hasTable((new Contract())->getTable())) {
            $contracts = Contract::where('employee_id', $id)->orderByDesc('id')->get();
        }
        $documents = EmployeeDocument::where('employee_id', $id)->orderBy('id')->get();

        return view('modules.employees.detail', compact('employee', 'contracts', 'documents'));
    }

    private function mutateEmployee(int $employeeId, int $fromCompanyId, int $toCompanyId, array $user, string $note = ''): void
    {
        if (!Schema::hasTable((new EmployeeMutation())->getTable())) {
            abort(500, 'Table employee_mutations belum ada. Jalankan migration terlebih dahulu.');
        }

        $employee = Employee::find($employeeId);
        if (!$employee) {
            abort(404, 'Employee not found');
        }
        if ((int) $employee->company_id !== $fromCompanyId) {
            abort(400, 'Company asal tidak sesuai.');
        }

        if (!Company::where('id', $toCompanyId)->exists()) {
            abort(400, 'Company tujuan tidak valid.');
        }

        $fromNik = (string) ($employee->nik ?? '');
        $toNik = $fromNik;
        if ($toNik !== '' && Employee::query()->where('company_id', $toCompanyId)->where('nik', $toNik)->exists()) {
            $toNik = Employee::generateNik($toCompanyId, (string) ($employee->join_date ?? ''), null);
        }

        DB::transaction(function () use ($employee, $fromCompanyId, $toCompanyId, $fromNik, $toNik, $user, $note) {
            $employee->company_id = $toCompanyId;
            $employee->nik = $toNik;
            $employee->active_status = Employee::ACTIVE_STATUS_ACTIVE;
            $employee->save();

            EmployeeMutation::create([
                'employee_id' => $employee->id,
                'from_company_id' => $fromCompanyId,
                'to_company_id' => $toCompanyId,
                'from_nik' => $fromNik !== '' ? $fromNik : null,
                'to_nik' => $toNik !== '' ? $toNik : null,
                'mutated_at' => now(),
                'actor_user_id' => isset($user['id']) ? (int) $user['id'] : null,
                'note' => $note !== '' ? $note : null,
            ]);
        });
    }

    public function status(Request $request)
    {
        $companyId = current_company_id();
        $globalCompanyId = 0;

        if ($request->isMethod('post')) {
            $action = $request->input('action', 'save');
            if ($action === 'delete') {
                $deleteId = (int) $request->input('id');
                $status = $deleteId ? EmployeeStatus::find($deleteId) : null;
                if ($status && (int)$status->company_id === $globalCompanyId) {
                    $status->delete();
                }
                return redirect()->route('employees.status');
            }

            $data = $request->validate([
                'status_name' => ['required','string','max:100'],
                'status_note' => ['nullable','string','max:255'],
            ]);

            if ($action === 'update') {
                $editId = (int) $request->input('id');
                $status = $editId ? EmployeeStatus::find($editId) : null;
                if (!$status || (int)$status->company_id !== $globalCompanyId) {
                    abort(404, 'Status not found');
                }
                $status->status_name = $data['status_name'];
                $status->note = $data['status_note'] ?? null;
                $status->save();
                return redirect()->route('employees.status');
            }

            EmployeeStatus::create([
                'company_id' => $globalCompanyId,
                'status_name' => $data['status_name'],
                'note' => $data['status_note'] ?? null,
            ]);
            return redirect()->route('employees.status');
        }

        $editId = (int) $request->query('edit', 0);
        $edit = $editId ? EmployeeStatus::find($editId) : null;
        if ($edit && (int)$edit->company_id !== $globalCompanyId) {
            $edit = null;
        }
        $statuses = $this->listGlobalOrCompany(EmployeeStatus::query(), $companyId, 'status_name');
        return view('modules.employees.status', compact('statuses', 'edit'));
    }

    public function activeStatus(Request $request)
    {
        $tableReady = Schema::hasTable((new EmployeeActiveStatus())->getTable());
        $globalCompanyId = 0;

        if ($request->isMethod('post')) {
            if (!$tableReady) {
                abort(500, 'Table employee_active_statuses belum ada. Jalankan migration terlebih dahulu.');
            }
            $action = $request->input('action', 'save');
            if ($action === 'delete') {
                $deleteId = (int) $request->input('id');
                $status = $deleteId ? EmployeeActiveStatus::find($deleteId) : null;
                if ($status && (int) $status->company_id === $globalCompanyId) {
                    $status->delete();
                }
                return redirect()->route('employees.active_status');
            }

            $data = $request->validate([
                'status_name' => ['required','string','max:100'],
                'is_archive' => ['nullable'],
                'sort_order' => ['nullable','integer','min:0','max:1000000'],
                'status_note' => ['nullable','string','max:255'],
            ]);

            $isArchive = $request->boolean('is_archive');
            $sortOrder = (int) ($data['sort_order'] ?? 0);

            if ($action === 'update') {
                $editId = (int) $request->input('id');
                $status = $editId ? EmployeeActiveStatus::find($editId) : null;
                if (!$status || (int) $status->company_id !== $globalCompanyId) {
                    abort(404, 'Status not found');
                }
                $status->status_name = $data['status_name'];
                $status->is_archive = $isArchive ? 1 : 0;
                $status->sort_order = $sortOrder;
                $status->note = $data['status_note'] ?? null;
                $status->save();
                return redirect()->route('employees.active_status');
            }

            EmployeeActiveStatus::create([
                'company_id' => $globalCompanyId,
                'status_name' => $data['status_name'],
                'is_archive' => $isArchive ? 1 : 0,
                'sort_order' => $sortOrder,
                'note' => $data['status_note'] ?? null,
            ]);
            return redirect()->route('employees.active_status');
        }

        $editId = (int) $request->query('edit', 0);
        $edit = null;
        if ($tableReady && $editId) {
            $edit = EmployeeActiveStatus::find($editId);
        }
        if ($edit && (int) $edit->company_id !== $globalCompanyId) {
            $edit = null;
        }

        if ($tableReady) {
            $activeStatusOptions = EmployeeActiveStatus::query()
                ->where('company_id', $globalCompanyId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        } else {
            $activeStatusOptions = collect(Employee::activeStatusOptions())
                ->values()
                ->map(function ($name, $idx) {
                    return (object) [
                        'id' => null,
                        'status_name' => $name,
                        'sort_order' => ($idx + 1) * 10,
                        'is_archive' => in_array($name, Employee::archiveActiveStatuses(), true) ? 1 : 0,
                        'note' => null,
                    ];
                });
        }

        return view('modules.employees.active_status', compact('activeStatusOptions', 'edit', 'tableReady'));
    }

    public function type(Request $request)
    {
        if ($request->isMethod('post')) {
            $action = $request->input('action', 'save');
            if ($action === 'delete') {
                $deleteId = (int) $request->input('id');
                $type = $deleteId ? EmployeeType::find($deleteId) : null;
                if ($type) {
                    $type->delete();
                }
                return redirect()->route('employees.type');
            }

            $data = $request->validate([
                'type_name' => ['required','string','max:50'],
            ]);

            if ($action === 'update') {
                $editId = (int) $request->input('id');
                $type = $editId ? EmployeeType::find($editId) : null;
                if (!$type) {
                    abort(404, 'Type not found');
                }
                $type->type_name = $data['type_name'];
                $type->save();
                return redirect()->route('employees.type');
            }

            EmployeeType::create([
                'type_name' => $data['type_name'],
            ]);
            return redirect()->route('employees.type');
        }

        $editId = (int) $request->query('edit', 0);
        $edit = $editId ? EmployeeType::find($editId) : null;
        $types = EmployeeType::orderBy('type_name')->get();
        return view('modules.employees.type', compact('types', 'edit'));
    }

    public function draftUpload(Request $request, KtpOcrService $ktpOcrService)
    {
        $allowed = [
            'photo' => ['label' => 'Pas Foto', 'ext' => ['jpg','jpeg','png'], 'mime' => ['image/jpeg','image/png']],
            'ktp_file' => ['label' => 'KTP', 'ext' => ['jpg','jpeg','png','pdf'], 'mime' => ['image/jpeg','image/png','application/pdf']],
            'ijazah_file' => ['label' => 'Ijazah', 'ext' => ['jpg','jpeg','png','pdf'], 'mime' => ['image/jpeg','image/png','application/pdf']],
            'surat_lamaran_file' => ['label' => 'Surat Lamaran Kerja', 'ext' => ['jpg','jpeg','png','pdf'], 'mime' => ['image/jpeg','image/png','application/pdf']],
            'cv_file' => ['label' => 'CV', 'ext' => ['jpg','jpeg','png','pdf'], 'mime' => ['image/jpeg','image/png','application/pdf']],
            'mcu_file' => ['label' => 'MCU/Surat Sehat', 'ext' => ['jpg','jpeg','png','pdf'], 'mime' => ['image/jpeg','image/png','application/pdf']],
            'kk_file' => ['label' => 'KK', 'ext' => ['jpg','jpeg','png','pdf'], 'mime' => ['image/jpeg','image/png','application/pdf']],
            'npwp_file' => ['label' => 'NPWP', 'ext' => ['jpg','jpeg','png','pdf'], 'mime' => ['image/jpeg','image/png','application/pdf']],
            'skck_file' => ['label' => 'SKCK', 'ext' => ['jpg','jpeg','png','pdf'], 'mime' => ['image/jpeg','image/png','application/pdf']],
        ];
        $dir = public_path('uploads/employees/drafts');
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        $paths = [];
        $ocr = [];

        $existingKtpPath = trim((string) $request->input('existing_ktp_path', ''));
        if ($existingKtpPath !== '' && str_starts_with($existingKtpPath, 'uploads/employees/')) {
            $ocrResult = $ktpOcrService->extractAddressWithReason($existingKtpPath);
            $ocr['ktp_address'] = $ocrResult['address'] ?? null;
            $ocr['ktp_reason'] = $ocrResult['reason'] ?? null;
        }

        foreach ($allowed as $input => $meta) {
            if (!$request->hasFile($input)) {
                continue;
            }
            $file = $request->file($input);
            if (!$file->isValid()) {
                abort(400, "Upload {$meta['label']} gagal.");
            }
            if ($file->getSize() > 5 * 1024 * 1024) {
                abort(400, "{$meta['label']} terlalu besar (maks 5MB).");
            }
            $ext = strtolower($file->getClientOriginalExtension());
            $mime = $file->getMimeType();
            if (!in_array($ext, $meta['ext'], true) || !in_array($mime, $meta['mime'], true)) {
                abort(400, "Format {$meta['label']} tidak valid.");
            }
            $filename = 'draft_' . $input . '_' . uniqid() . '.' . $ext;
            $file->move($dir, $filename);
            $paths[$input] = 'uploads/employees/drafts/' . $filename;
            if ($input === 'ktp_file') {
                $ocrResult = $ktpOcrService->extractAddressWithReason($paths[$input]);
                $ocr['ktp_address'] = $ocrResult['address'] ?? null;
                $ocr['ktp_reason'] = $ocrResult['reason'] ?? null;
            }
        }

        return response()->json(['ok' => true, 'paths' => $paths, 'ocr' => $ocr]);
    }

    public function position(Request $request)
    {
        $companyId = current_company_id();
        $globalCompanyId = 0;

        if ($request->isMethod('post')) {
            $action = $request->input('action', 'save');
            if ($action === 'delete') {
                $deleteId = (int) $request->input('id');
                $position = $deleteId ? EmployeePosition::find($deleteId) : null;
                if ($position && (int)$position->company_id === $globalCompanyId) {
                    $position->delete();
                }
                return redirect()->route('employees.position');
            }

            $data = $request->validate([
                'position_name' => ['required','string','max:120'],
                'position_note' => ['nullable','string','max:255'],
            ]);
            if ($action === 'update') {
                $editId = (int) $request->input('id');
                $position = $editId ? EmployeePosition::find($editId) : null;
                if (!$position || (int)$position->company_id !== $globalCompanyId) {
                    abort(404, 'Position not found');
                }
                $position->position_name = $data['position_name'];
                $position->note = $data['position_note'] ?? null;
                $position->save();
                return redirect()->route('employees.position');
            }

            EmployeePosition::create([
                'company_id' => $globalCompanyId,
                'position_name' => $data['position_name'],
                'note' => $data['position_note'] ?? null,
            ]);
            return redirect()->route('employees.position');
        }

        $editId = (int) $request->query('edit', 0);
        $edit = $editId ? EmployeePosition::find($editId) : null;
        if ($edit && (int)$edit->company_id !== $globalCompanyId) {
            $edit = null;
        }
        $positions = $this->listGlobalOrCompany(EmployeePosition::query(), $companyId, 'position_name');
        return view('modules.employees.position', compact('positions', 'edit'));
    }

    public function grade(Request $request)
    {
        $companyId = current_company_id();
        $globalCompanyId = 0;

        if ($request->isMethod('post')) {
            $action = $request->input('action', 'save');
            if ($action === 'delete') {
                $deleteId = (int) $request->input('id');
                $grade = $deleteId ? EmployeeGrade::find($deleteId) : null;
                if ($grade && (int)$grade->company_id === $globalCompanyId) {
                    $grade->delete();
                }
                return redirect()->route('employees.grade');
            }

            $data = $request->validate([
                'grade_name' => ['required','string','max:120'],
                'grade_note' => ['nullable','string','max:255'],
            ]);
            if ($action === 'update') {
                $editId = (int) $request->input('id');
                $grade = $editId ? EmployeeGrade::find($editId) : null;
                if (!$grade || (int)$grade->company_id !== $globalCompanyId) {
                    abort(404, 'Grade not found');
                }
                $grade->grade_name = $data['grade_name'];
                $grade->note = $data['grade_note'] ?? null;
                $grade->save();
                return redirect()->route('employees.grade');
            }

            EmployeeGrade::create([
                'company_id' => $globalCompanyId,
                'grade_name' => $data['grade_name'],
                'note' => $data['grade_note'] ?? null,
            ]);
            return redirect()->route('employees.grade');
        }

        $editId = (int) $request->query('edit', 0);
        $edit = $editId ? EmployeeGrade::find($editId) : null;
        if ($edit && (int)$edit->company_id !== $globalCompanyId) {
            $edit = null;
        }
        $grades = $this->listGlobalOrCompany(EmployeeGrade::query(), $companyId, 'grade_name');
        return view('modules.employees.grade', compact('grades', 'edit'));
    }

    public function department(Request $request)
    {
        $user = current_user();
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            return redirect()->route('employees.department');
        }
        $companyId = current_company_id();
        $globalCompanyId = 0;

        if ($request->isMethod('post') && $request->input('action') === 'delete') {
            $deleteId = (int) $request->input('id', 0);
            $dept = $deleteId ? EmployeeDepartment::find($deleteId) : null;
            if ($dept && (int) $dept->company_id === $globalCompanyId) {
                $dept->delete();
            }
            return redirect()->route('employees.department');
        }

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'id' => ['nullable','integer','min:1'],
                'department_name' => ['required','string','max:120'],
                'department_note' => ['nullable','string','max:255'],
            ]);
            $editId = (int) ($data['id'] ?? 0);
            if ($editId > 0) {
                $dept = EmployeeDepartment::find($editId);
                if (!$dept || (int) $dept->company_id !== $globalCompanyId) {
                    return redirect()->route('employees.department');
                }
                $dept->department_name = $data['department_name'];
                $dept->note = $data['department_note'] ?? null;
                $dept->save();
                return redirect()->route('employees.department');
            }
            EmployeeDepartment::create([
                'company_id' => $globalCompanyId,
                'department_name' => $data['department_name'],
                'note' => $data['department_note'] ?? null,
            ]);
            return redirect()->route('employees.department');
        }

        $editId = (int) $request->query('edit', 0);
        $edit = $editId ? EmployeeDepartment::find($editId) : null;
        if ($edit && (int) $edit->company_id !== $globalCompanyId) {
            $edit = null;
        }
        $departments = $this->listGlobalOrCompany(EmployeeDepartment::query(), $companyId, 'department_name');
        return view('modules.employees.department', compact('departments', 'edit'));
    }

    public function import(Request $request)
    {
        $user = current_user();
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            return redirect()->route('employees.import');
        }
        $companyId = current_company_id();
        $companies = Company::orderBy('id')->get();

        $messages = [];
        $normalizeHeader = static function ($value): string {
            $key = strtolower(trim((string) $value));
            $key = str_replace("\xC2\xA0", ' ', $key);
            $key = preg_replace('/\s+/', ' ', $key);
            return $key;
        };
        $requiredHeaders = array_map($normalizeHeader, [
            'nik', 'nama karyawan', 'status karyawan',
            'staff / non staff', 'jabatan', 'golongan', 'tanggal join', 'habis kontrak'
        ]);
        $allowedPtkp = ['TK/0','TK/1','TK/2','TK/3','K/0','K/1','K/2','K/3'];
        $employeeColumns = Schema::hasTable('employees') ? Schema::getColumnListing('employees') : [];
        $employeeColumnMap = array_fill_keys($employeeColumns, true);

        if ($request->isMethod('post')) {
            if (!$request->hasFile('file')) {
                $messages[] = 'File upload failed.';
            } else {
                $file = $request->file('file');
                if (!$file->isValid()) {
                    $messages[] = 'File upload failed.';
                } elseif ($file->getSize() > 5 * 1024 * 1024) {
                    $messages[] = 'File terlalu besar (maks 5MB).';
                } else {
                    $ext = strtolower($file->getClientOriginalExtension());
                    if (!in_array($ext, ['csv','xlsx','xls'], true)) {
                        $messages[] = 'Format harus CSV atau Excel (XLSX/XLS).';
                    } else {
                        $rows = [];
                        if ($ext === 'csv') {
                            $handle = fopen($file->getPathname(), 'r');
                            if ($handle) {
                                while (($row = fgetcsv($handle)) !== false) {
                                    $rows[] = $row;
                                }
                                fclose($handle);
                            }
                        } else {
                            if (!class_exists(\ZipArchive::class)) {
                                $messages[] = 'Ekstensi PHP ZIP belum aktif. Aktifkan extension=zip di php.ini atau gunakan file CSV.';
                            } else {
                                $spreadsheet = IOFactory::load($file->getPathname());
                                $sheets = $spreadsheet->getAllSheets();
                                foreach ($sheets as $sheet) {
                                    $candidate = $sheet->toArray(null, true, true, false);
                                    if (count($candidate) <= 1) {
                                        continue;
                                    }
                                    $hasData = false;
                                    for ($i = 1; $i < count($candidate); $i++) {
                                        foreach ($candidate[$i] as $cell) {
                                            if (trim((string) $cell) !== '') {
                                                $hasData = true;
                                                break 2;
                                            }
                                        }
                                    }
                                    if ($hasData) {
                                        $rows = $candidate;
                                        break;
                                    }
                                }
                                if ($rows === []) {
                                    $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
                                }
                            }
                        }

                        if (count($rows) === 0) {
                            $messages[] = 'File kosong.';
                        } else {
                            $header = $rows[0];
                            $map = [];
                            foreach ($header as $i => $h) {
                                $key = $normalizeHeader($h);
                                if ($key !== '') {
                                    $map[$key] = $i;
                                }
                            }
                            foreach ($requiredHeaders as $req) {
                                if (!isset($map[$req])) {
                                    $messages[] = 'Header tidak sesuai. Pastikan format kolom sesuai template.';
                                    break;
                                }
                            }
                            $ptkpKey = null;
                            if (isset($map['ptkp status'])) {
                                $ptkpKey = 'ptkp status';
                            } elseif (isset($map['ptkp'])) {
                                $ptkpKey = 'ptkp';
                            }
                            $nikKtpKey = null;
                            if (isset($map['nik ktp'])) {
                                $nikKtpKey = 'nik ktp';
                            } elseif (isset($map['nik (ktp)'])) {
                                $nikKtpKey = 'nik (ktp)';
                            } elseif (isset($map['nik kependudukan'])) {
                                $nikKtpKey = 'nik kependudukan';
                            }
                            $addressKtpKey = null;
                            if (isset($map['alamat ktp'])) {
                                $addressKtpKey = 'alamat ktp';
                            } elseif (isset($map['address ktp'])) {
                                $addressKtpKey = 'address ktp';
                            }
                            $domicileKey = null;
                            if (isset($map['alamat domisili'])) {
                                $domicileKey = 'alamat domisili';
                            } elseif (isset($map['domicile address'])) {
                                $domicileKey = 'domicile address';
                            }
                            $placeBirthKey = null;
                            if (isset($map['tempat lahir'])) {
                                $placeBirthKey = 'tempat lahir';
                            } elseif (isset($map['place of birth'])) {
                                $placeBirthKey = 'place of birth';
                            }
                            $dobKey = null;
                            if (isset($map['tanggal lahir'])) {
                                $dobKey = 'tanggal lahir';
                            } elseif (isset($map['date of birth'])) {
                                $dobKey = 'date of birth';
                            }
                            $emergencyKey = null;
                            if (isset($map['nomor tlp saudara/famili'])) {
                                $emergencyKey = 'nomor tlp saudara/famili';
                            } elseif (isset($map['nomor tlp saudara'])) {
                                $emergencyKey = 'nomor tlp saudara';
                            } elseif (isset($map['emergency contact number'])) {
                                $emergencyKey = 'emergency contact number';
                            }
                            $activeKey = null;
                            if (isset($map['status aktif'])) {
                                $activeKey = 'status aktif';
                            } elseif (isset($map['active status'])) {
                                $activeKey = 'active status';
                            } elseif (isset($map['status active'])) {
                                $activeKey = 'status active';
                            }
                            $npwpKey = null;
                            if (isset($map['npwp'])) {
                                $npwpKey = 'npwp';
                            }
                            $deptKey = null;
                            if (isset($map['departement'])) {
                                $deptKey = 'departement';
                            } elseif (isset($map['department'])) {
                                $deptKey = 'department';
                            } elseif (isset($map['departemen'])) {
                                $deptKey = 'departemen';
                            }
                            $bankNameKey = null;
                            if (isset($map['nama bank'])) {
                                $bankNameKey = 'nama bank';
                            } elseif (isset($map['bank name'])) {
                                $bankNameKey = 'bank name';
                            }
                            $bankAccountKey = null;
                            if (isset($map['nomor rekening'])) {
                                $bankAccountKey = 'nomor rekening';
                            } elseif (isset($map['no rekening'])) {
                                $bankAccountKey = 'no rekening';
                            } elseif (isset($map['no. rekening'])) {
                                $bankAccountKey = 'no. rekening';
                            } elseif (isset($map['rekening'])) {
                                $bankAccountKey = 'rekening';
                            } elseif (isset($map['bank account'])) {
                                $bankAccountKey = 'bank account';
                            }
                            $phoneKey = null;
                            if (isset($map['nomor hp'])) {
                                $phoneKey = 'nomor hp';
                            } elseif (isset($map['no hp'])) {
                                $phoneKey = 'no hp';
                            } elseif (isset($map['no. hp'])) {
                                $phoneKey = 'no. hp';
                            } elseif (isset($map['hp'])) {
                                $phoneKey = 'hp';
                            } elseif (isset($map['phone'])) {
                                $phoneKey = 'phone';
                            }

                            if (empty($messages)) {
                                $count = 0;
                                for ($r = 1; $r < count($rows); $r++) {
                                    $row = $rows[$r];
                                    $joinDate = date_input_to_db($row[$map['tanggal join']] ?? '');
                                    $contractEnd = date_input_to_db($row[$map['habis kontrak']] ?? '');
                                    $dob = $dobKey ? date_input_to_db($row[$map[$dobKey]] ?? '') : '';
                                    $nikVal = trim((string) ($row[$map['nik']] ?? ''));
                                    if ($nikVal === '') {
                                        $nikVal = Employee::generateNik($companyId, $joinDate ?: null, null);
                                    }

                                    $data = [
                                        'company_id' => $companyId,
                                        'nik' => $nikVal,
                                        'name' => $row[$map['nama karyawan']] ?? '',
                                        'active_status' => $activeKey ? ($row[$map[$activeKey]] ?? '') : 'Active',
                                        'phone' => $phoneKey ? ($row[$map[$phoneKey]] ?? '') : '',
                                        'nik_ktp' => $nikKtpKey ? ($row[$map[$nikKtpKey]] ?? '') : '',
                                        'address_ktp' => $addressKtpKey ? ($row[$map[$addressKtpKey]] ?? '') : '',
                                        'domicile_address' => $domicileKey ? ($row[$map[$domicileKey]] ?? '') : '',
                                        'place_of_birth' => $placeBirthKey ? ($row[$map[$placeBirthKey]] ?? '') : '',
                                        'date_of_birth' => $dob ?: null,
                                        'emergency_contact_number' => $emergencyKey ? ($row[$map[$emergencyKey]] ?? '') : '',
                                        'npwp' => $npwpKey ? ($row[$map[$npwpKey]] ?? '') : '',
                                        'ptkp_status' => 'TK/0',
                                        'employment_status' => $row[$map['status karyawan']] ?? '',
                                        'employee_type' => $row[$map['staff / non staff']] ?? '',
                                        'department' => $deptKey ? ($row[$map[$deptKey]] ?? '') : '',
                                        'position' => $row[$map['jabatan']] ?? '',
                                        'grade' => $row[$map['golongan']] ?? '',
                                        'bank_name' => $bankNameKey ? ($row[$map[$bankNameKey]] ?? '') : '',
                                        'bank_account_no' => $bankAccountKey ? ($row[$map[$bankAccountKey]] ?? '') : '',
                                        'join_date' => $joinDate ?: null,
                                        'contract_end' => $contractEnd ?: null,
                                        'photo_path' => null,
                                    ];
                                    if ($ptkpKey !== null) {
                                        $val = strtoupper(trim((string)($row[$map[$ptkpKey]] ?? '')));
                                        if (in_array($val, $allowedPtkp, true)) {
                                            $data['ptkp_status'] = $val;
                                        }
                                    }
                                    if ($data['nik'] && $data['name']) {
                                        if ($employeeColumnMap) {
                                            $data = array_intersect_key($data, $employeeColumnMap);
                                        }
                                        $employee = Employee::create($data);
                                        app(EmployeeContractSyncService::class)->syncEmployee($employee);
                                        $count++;
                                    }
                                }
                                $messages[] = "Import sukses: {$count} data.";
                            }
                        }
                    }
                }
            }
        }

        return view('modules.employees.import', compact('user', 'companyId', 'companies', 'messages'));
    }

    public function export()
    {
        $companyId = current_company_id();
        $employees = Employee::where('company_id', $companyId)->orderBy('name')->get();

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [
            'NIK',
            'NIK KTP',
            'Nama Karyawan',
            'Status Aktif',
            'Alamat KTP',
            'Alamat Domisili',
            'Tempat Lahir',
            'Tanggal Lahir',
            'Nomor HP',
            'Nomor Tlp Saudara/Famili',
            'NPWP',
            'Nama Bank',
            'Nomor Rekening',
            'PTKP Status',
            'Status Karyawan',
            'Staff / Non Staff',
            'Departement',
            'Jabatan',
            'Golongan',
            'Tanggal Join',
            'Habis Kontrak'
        ]);
        foreach ($employees as $e) {
            fputcsv($handle, [
                $e->nik,
                $e->nik_ktp,
                $e->name,
                $e->active_status,
                $e->address_ktp,
                $e->domicile_address,
                $e->place_of_birth,
                format_date_id($e->date_of_birth),
                $e->phone,
                $e->emergency_contact_number,
                $e->npwp,
                $e->bank_name,
                $e->bank_account_no,
                $e->ptkp_status,
                $e->employment_status,
                $e->employee_type,
                $e->department,
                $e->position,
                $e->grade,
                format_date_id($e->join_date),
                format_date_id($e->contract_end)
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="employees_export.csv"');
    }

    public function template()
    {
        $path = base_path('database/employee_template.xlsx');
        if (!file_exists($path)) {
            abort(404, 'Template not found');
        }
        return response()->download($path, 'employee_template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}

