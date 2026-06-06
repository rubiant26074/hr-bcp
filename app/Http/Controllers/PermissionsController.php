<?php

namespace App\Http\Controllers;

use App\Models\AbsenceRequest;
use App\Models\ApprovalSetting;
use App\Models\ApprovalStep;
use App\Models\ApprovalRequestStep;
use App\Models\Company;
use App\Models\Holiday;
use App\Models\Employee;
use App\Models\OutOfficeRequest;
use App\Models\OvertimeRequest;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Dompdf\Dompdf;

class PermissionsController extends Controller
{
    private function annualCutiQuota(): int
    {
        return 12;
    }

    private function cutiYearWindow(): array
    {
        $year = (int) date('Y');
        $start = \Carbon\Carbon::create($year, 1, 1)->startOfDay();
        $end = \Carbon\Carbon::create($year, 12, 31)->endOfDay();
        return [$year, $start, $end];
    }

    private function cutiUsageMap(int $companyId): array
    {
        [$year, $start, $end] = $this->cutiYearWindow();
        $holidaySet = Holiday::where('company_id', 0)
            ->whereBetween('holiday_date', [$start->toDateString(), $end->toDateString()])
            ->pluck('holiday_date')
            ->map(static fn ($d) => (string) $d)
            ->flip()
            ->all();
        $rows = AbsenceRequest::where('company_id', $companyId)
            ->where('request_type', 'Cuti')
            ->where('status', '!=', 'Rejected')
            ->whereDate('date_end', '>=', $start->toDateString())
            ->whereDate('date_start', '<=', $end->toDateString())
            ->get(['employee_id', 'date_start', 'date_end', 'reason']);

        $map = [];
        foreach ($rows as $row) {
            $employeeId = (int) ($row->employee_id ?? 0);
            if ($employeeId <= 0) {
                continue;
            }
            $reasonText = strtolower(trim((string) ($row->reason ?? '')));
            $isCutiBersama = str_contains($reasonText, 'cuti bersama') || str_contains($reasonText, 'cuti lebaran');
            $rangeStart = \Carbon\Carbon::parse($row->date_start)->startOfDay();
            $rangeEnd = \Carbon\Carbon::parse($row->date_end)->endOfDay();
            if ($rangeEnd->lt($start) || $rangeStart->gt($end)) {
                continue;
            }
            if ($rangeStart->lt($start)) {
                $rangeStart = $start->copy();
            }
            if ($rangeEnd->gt($end)) {
                $rangeEnd = $end->copy();
            }
            $days = 0;
            $cursor = $rangeStart->copy();
            while ($cursor->lte($rangeEnd)) {
                $key = $cursor->toDateString();
                // Cuti bersama tetap mengurangi hak cuti tahunan walaupun tanggal libur.
                if ($isCutiBersama || !isset($holidaySet[$key])) {
                    $days++;
                }
                $cursor->addDay();
            }
            $map[$employeeId] = ($map[$employeeId] ?? 0) + $days;
        }
        return $map;
    }

    private function eligibleForCuti(?string $joinDate): bool
    {
        if (!$joinDate) {
            return false;
        }
        $joined = \Carbon\Carbon::parse($joinDate)->startOfDay();
        return $joined->addYear()->lte(\Carbon\Carbon::now());
    }
    private function resolveImageDataUri(?string $path): string
    {
        $rawPath = trim((string) $path);
        if ($rawPath === '') {
            return '';
        }

        $rawPath = str_replace('\\', '/', $rawPath);
        $candidates = [];
        if (preg_match('#^([a-zA-Z]:/|/)#', $rawPath) === 1) {
            $candidates[] = $rawPath;
        } else {
            $candidates[] = public_path($rawPath);
            $candidates[] = base_path($rawPath);
        }

        foreach ($candidates as $candidate) {
            if (!is_file($candidate)) {
                continue;
            }
            $realPath = realpath($candidate);
            if ($realPath === false) {
                continue;
            }
            $mime = 'image/png';
            if (class_exists('finfo')) {
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $detected = $finfo->file($realPath);
                if (is_string($detected) && strpos($detected, 'image/') === 0) {
                    $mime = $detected;
                }
            }
            $content = file_get_contents($realPath);
            if ($content === false) {
                continue;
            }
            return 'data:' . $mime . ';base64,' . base64_encode($content);
        }

        return '';
    }

    private function pushNotification(int $companyId, int $userId, string $title, string $message, string $link = ''): void
    {
        if ($userId <= 0 || !Schema::hasTable('notifications')) {
            return;
        }
        Notification::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'link' => $link !== '' ? $link : null,
            'is_read' => 0,
        ]);
    }
    private function approvalDefaults(): array
    {
        return [
            'steps' => [],
        ];
    }

    private function resolveRequesterUserId(?int $requesterUserId, ?int $employeeId): int
    {
        $requesterUserId = (int) ($requesterUserId ?? 0);
        if ($requesterUserId > 0) {
            return $requesterUserId;
        }
        $employeeId = (int) ($employeeId ?? 0);
        if ($employeeId <= 0) {
            return 0;
        }
        return (int) (User::where('employee_id', $employeeId)->value('id') ?? 0);
    }

    private function approvalFlow(int $companyId, string $moduleKey, int $requesterUserId): array
    {
        if (!Schema::hasTable('approval_settings')) {
            return $this->approvalDefaults();
        }

        $steps = [];
        if (Schema::hasTable('approval_steps')) {
            $steps = ApprovalStep::where('company_id', $companyId)
                ->where('module_key', $moduleKey)
                ->where('requester_user_id', $requesterUserId)
                ->orderBy('step_no')
                ->pluck('approver_user_id')
                ->filter()
                ->map(static fn ($v) => (int) $v)
                ->values()
                ->all();
        }

        if (empty($steps)) {
            $setting = ApprovalSetting::where('company_id', $companyId)
                ->where('module_key', $moduleKey)
                ->where('requester_user_id', $requesterUserId)
                ->first();
            if ($setting) {
                if (!empty($setting->approver1_user_id)) {
                    $steps[] = (int) $setting->approver1_user_id;
                }
                if (!empty($setting->approver2_user_id)) {
                    $steps[] = (int) $setting->approver2_user_id;
                }
            }
        }

        return [
            'steps' => $steps,
        ];
    }

    private function defaultFallbackSteps(): array
    {
        return [null, null];
    }

    private function buildRequestSteps(string $moduleKey, int $requestId, int $companyId, int $requesterUserId): array
    {
        $flow = $this->approvalFlow($companyId, $moduleKey, $requesterUserId);
        $steps = $flow['steps'] ?? [];
        if (empty($steps)) {
            $steps = $this->defaultFallbackSteps();
        }
        ApprovalRequestStep::where('module_key', $moduleKey)->where('request_id', $requestId)->delete();
        foreach (array_values($steps) as $i => $approverId) {
            ApprovalRequestStep::create([
                'module_key' => $moduleKey,
                'request_id' => $requestId,
                'step_no' => $i + 1,
                'approver_user_id' => $approverId,
                'status' => 'Pending',
            ]);
        }
        return $steps;
    }

    private function getRequestSteps(string $moduleKey, int $requestId): array
    {
        if (!Schema::hasTable('approval_request_steps')) {
            return [];
        }
        return ApprovalRequestStep::where('module_key', $moduleKey)
            ->where('request_id', $requestId)
            ->orderBy('step_no')
            ->get()
            ->all();
    }

    private function ensureRequestSteps(string $moduleKey, $item, int $companyId, int $requesterUserId): array
    {
        $steps = $this->getRequestSteps($moduleKey, (int) $item->id);
        if (empty($steps) && Schema::hasTable('approval_request_steps')) {
            $this->buildRequestSteps($moduleKey, (int) $item->id, $companyId, $requesterUserId);
            $steps = $this->getRequestSteps($moduleKey, (int) $item->id);
        }
        return $steps;
    }

    private function pendingStep(array $steps): ?ApprovalRequestStep
    {
        foreach ($steps as $s) {
            if (($s->status ?? '') === 'Pending') {
                return $s;
            }
        }
        return null;
    }

    private function canApproveStep(?ApprovalRequestStep $step, array $user): bool
    {
        if (!$step) {
            return false;
        }
        $approverId = (int) ($step->approver_user_id ?? 0);
        if ($approverId > 0) {
            return (int) ($user['id'] ?? 0) === $approverId;
        }
        $role = (string) ($user['role'] ?? '');
        if ((int) ($step->step_no ?? 0) <= 1) {
            return $role !== 'Employee';
        }
        return in_array($role, ['Super Admin', 'HR'], true);
    }

    public function absence(Request $request)
    {
        $user = current_user();
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            return redirect()->route('permissions.absence');
        }
        $companyId = current_company_id();

        $cutiQuota = $this->annualCutiQuota();
        $cutiUsedMap = $this->cutiUsageMap($companyId);
        [$cutiYear] = $this->cutiYearWindow();
        $holidayDates = Holiday::where('company_id', 0)
            ->whereYear('holiday_date', $cutiYear)
            ->pluck('holiday_date')
            ->map(static fn ($d) => (string) $d)
            ->values()
            ->all();

        if ($request->isMethod('post')) {
            $moduleKey = 'absence';
            $action = $request->input('action', 'create');

            if ($action === 'approve_step') {
                $data = $request->validate([
                    'id' => ['required','integer'],
                ]);
                $item = AbsenceRequest::find((int) $data['id']);
                if (!$item || (int)$item->company_id !== (int)$companyId) {
                    return redirect()->route('permissions.absence');
                }

                $resolvedRequesterId = $this->resolveRequesterUserId((int) ($item->requester_user_id ?? 0), (int) ($item->employee_id ?? 0));
                $steps = $this->ensureRequestSteps($moduleKey, $item, $companyId, $resolvedRequesterId);
                $pending = $this->pendingStep($steps);
                if (!$this->canApproveStep($pending, $user)) {
                    abort(403, 'Access denied.');
                }
                if ($pending) {
                    ApprovalRequestStep::where('id', (int) $pending->id)->update([
                        'status' => 'Approved',
                        'approved_by' => (int) ($user['id'] ?? 0),
                        'approved_at' => now(),
                        'signature' => 'Approved',
                    ]);

                    $steps = $this->getRequestSteps($moduleKey, (int) $item->id);
                    $next = $this->pendingStep($steps);
                    $lastStepNo = !empty($steps) ? (int) end($steps)->step_no : 0;
                    if (!$next) {
                        $item->status = 'Approved';
                        $item->hrd_approved_by = (int) ($user['id'] ?? 0);
                        $item->hrd_approved_at = now();
                        $item->hrd_signature = 'Approved';
                    } else {
                        $item->status = 'Pending Approval ' . (int) ($next->step_no ?? 1);
                    }
                    if ((int) ($pending->step_no ?? 0) === 1) {
                        $item->atasan_approved_by = (int) ($user['id'] ?? 0);
                        $item->atasan_approved_at = now();
                        $item->atasan_signature = 'Approved';
                    }
                    if ($lastStepNo > 0 && (int) ($pending->step_no ?? 0) === $lastStepNo) {
                        $item->hrd_approved_by = (int) ($user['id'] ?? 0);
                        $item->hrd_approved_at = now();
                        $item->hrd_signature = 'Approved';
                    }
                    $item->save();

                    if ($next && (int) ($next->approver_user_id ?? 0) > 0) {
                        $this->pushNotification(
                            $companyId,
                            (int) $next->approver_user_id,
                            'Approval Izin (Step ' . (int) ($next->step_no ?? 1) . ')',
                            'Pengajuan izin menunggu approval Anda.',
                            route('permissions.absence')
                        );
                    } elseif (!$next) {
                        $this->pushNotification(
                            $companyId,
                            (int) $resolvedRequesterId,
                            'Pengajuan Izin Disetujui',
                            'Pengajuan izin Anda telah disetujui.',
                            route('permissions.absence')
                        );
                    }
                }
                return redirect()->route('permissions.absence');
            }

            if ($action === 'reject') {
                $data = $request->validate([
                    'id' => ['required','integer'],
                    'note' => ['nullable','string','max:255'],
                ]);
                $item = AbsenceRequest::find((int) $data['id']);
                if (!$item || (int)$item->company_id !== (int)$companyId) {
                    return redirect()->route('permissions.absence');
                }
                $resolvedRequesterId = $this->resolveRequesterUserId((int) ($item->requester_user_id ?? 0), (int) ($item->employee_id ?? 0));
                $steps = $this->ensureRequestSteps($moduleKey, $item, $companyId, $resolvedRequesterId);
                $pending = $this->pendingStep($steps);
                $canReject = $this->canApproveStep($pending, $user);
                if (!$canReject) {
                    abort(403, 'Access denied.');
                }
                if (!in_array($item->status, ['Approved','Rejected'], true)) {
                    $item->status = 'Rejected';
                    $item->rejected_by = (int) ($user['id'] ?? 0);
                    $item->rejected_at = now();
                    $item->rejected_note = $data['note'] ?? null;
                    $item->save();
                    if ($pending) {
                        ApprovalRequestStep::where('id', (int) $pending->id)->update([
                            'status' => 'Rejected',
                        ]);
                    }
                    $this->pushNotification(
                        $companyId,
                        (int) $resolvedRequesterId,
                        'Pengajuan Izin Ditolak',
                        'Pengajuan izin Anda ditolak. ' . trim((string) ($data['note'] ?? '')),
                        route('permissions.absence')
                    );
                }
                return redirect()->route('permissions.absence');
            }

            $data = $request->validate([
                'request_type' => ['required','string','max:20'],
                'date_start' => ['required','date'],
                'date_end' => ['required','date'],
                'reason' => ['nullable','string','max:255'],
                'employee_id' => ['nullable','integer'],
                'force_izin' => ['nullable','string'],
            ]);

            $employeeId = (int) ($data['employee_id'] ?? 0);
            if (($user['role'] ?? '') === 'Employee') {
                $employeeId = (int) ($user['employee_id'] ?? 0);
            }
            if ($employeeId <= 0) {
                return back()->withErrors(['employee_id' => 'Employee wajib dipilih.'])->withInput();
            }

            $dateStart = \Carbon\Carbon::parse($data['date_start'])->startOfDay();
            $dateEnd = \Carbon\Carbon::parse($data['date_end'])->startOfDay();
            $holidaySet = Holiday::where('company_id', 0)
                ->whereBetween('holiday_date', [$dateStart->toDateString(), $dateEnd->toDateString()])
                ->pluck('holiday_date')
                ->map(static fn ($d) => (string) $d)
                ->flip()
                ->all();
            $requestedDays = 0;
            $cursor = $dateStart->copy();
            while ($cursor->lte($dateEnd)) {
                $key = $cursor->toDateString();
                if (!isset($holidaySet[$key])) {
                    $requestedDays++;
                }
                $cursor->addDay();
            }
            if (strtoupper($data['request_type']) === 'CUTI') {
                $employee = Employee::find($employeeId);
                $used = (int) ($cutiUsedMap[$employeeId] ?? 0);
                $remaining = max(0, $cutiQuota - $used);
                if ($remaining < $requestedDays) {
                    if ((string) ($data['force_izin'] ?? '') === '1') {
                        $data['request_type'] = 'Izin';
                    } else {
                        return back()->withErrors(['request_type' => 'Jatah Cuti sudah Habis, lanjut izin.'])->withInput();
                    }
                }
            }
            $requesterUserId = (int) ($user['id'] ?? 0);
            if (($user['role'] ?? '') !== 'Employee') {
                $mappedUserId = (int) (User::where('employee_id', $employeeId)->value('id') ?? 0);
                if ($mappedUserId > 0) {
                    $requesterUserId = $mappedUserId;
                }
            }
            $flowForCreate = $this->approvalFlow($companyId, $moduleKey, $requesterUserId);

            $doctorNotePath = null;
            $attachmentPath = null;
            $dir = public_path('uploads/permissions');
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
            if (strtoupper($data['request_type']) === 'SAKIT') {
                if (!$request->hasFile('doctor_note_file')) {
                    return back()->withErrors(['doctor_note_file' => 'Surat dokter wajib untuk izin sakit.'])->withInput();
                }
                $file = $request->file('doctor_note_file');
                if (!$file->isValid()) {
                    return back()->withErrors(['doctor_note_file' => 'Upload surat dokter gagal.'])->withInput();
                }
                if ($file->getSize() > 5 * 1024 * 1024) {
                    return back()->withErrors(['doctor_note_file' => 'Surat dokter terlalu besar (maks 5MB).'])->withInput();
                }
                $ext = strtolower($file->getClientOriginalExtension());
                $allowed = ['jpg','jpeg','png','pdf'];
                $mime = $file->getMimeType();
                $allowedMime = ['image/jpeg','image/png','application/pdf'];
                if (!in_array($ext, $allowed, true) || !in_array($mime, $allowedMime, true)) {
                    return back()->withErrors(['doctor_note_file' => 'Format surat dokter harus JPG/PNG/PDF.'])->withInput();
                }
                $filename = 'permit_doctor_' . uniqid() . '.' . $ext;
                $file->move($dir, $filename);
                $doctorNotePath = 'uploads/permissions/' . $filename;
            } elseif (strtoupper($data['request_type']) === 'CUTI KHUSUS') {
                if (!$request->hasFile('special_attachment_file')) {
                    return back()->withErrors(['special_attachment_file' => 'Lampiran dokumen wajib untuk cuti khusus.'])->withInput();
                }
                $file = $request->file('special_attachment_file');
                if (!$file->isValid()) {
                    return back()->withErrors(['special_attachment_file' => 'Upload lampiran dokumen gagal.'])->withInput();
                }
                if ($file->getSize() > 5 * 1024 * 1024) {
                    return back()->withErrors(['special_attachment_file' => 'Lampiran dokumen terlalu besar (maks 5MB).'])->withInput();
                }
                $ext = strtolower($file->getClientOriginalExtension());
                $allowed = ['jpg','jpeg','png','pdf'];
                $mime = $file->getMimeType();
                $allowedMime = ['image/jpeg','image/png','application/pdf'];
                if (!in_array($ext, $allowed, true) || !in_array($mime, $allowedMime, true)) {
                    return back()->withErrors(['special_attachment_file' => 'Format lampiran harus JPG/PNG/PDF.'])->withInput();
                }
                $filename = 'permit_attachment_' . uniqid() . '.' . $ext;
                $file->move($dir, $filename);
                $attachmentPath = 'uploads/permissions/' . $filename;
            } elseif ($request->hasFile('doctor_note_file')) {
                $file = $request->file('doctor_note_file');
                if (!$file->isValid()) {
                    return back()->withErrors(['doctor_note_file' => 'Upload surat dokter gagal.'])->withInput();
                }
                if ($file->getSize() > 5 * 1024 * 1024) {
                    return back()->withErrors(['doctor_note_file' => 'Surat dokter terlalu besar (maks 5MB).'])->withInput();
                }
                $ext = strtolower($file->getClientOriginalExtension());
                $allowed = ['jpg','jpeg','png','pdf'];
                $mime = $file->getMimeType();
                $allowedMime = ['image/jpeg','image/png','application/pdf'];
                if (!in_array($ext, $allowed, true) || !in_array($mime, $allowedMime, true)) {
                    return back()->withErrors(['doctor_note_file' => 'Format surat dokter harus JPG/PNG/PDF.'])->withInput();
                }
                $filename = 'permit_doctor_' . uniqid() . '.' . $ext;
                $file->move($dir, $filename);
                $doctorNotePath = 'uploads/permissions/' . $filename;
            } elseif ($request->hasFile('special_attachment_file')) {
                $file = $request->file('special_attachment_file');
                if (!$file->isValid()) {
                    return back()->withErrors(['special_attachment_file' => 'Upload lampiran dokumen gagal.'])->withInput();
                }
                if ($file->getSize() > 5 * 1024 * 1024) {
                    return back()->withErrors(['special_attachment_file' => 'Lampiran dokumen terlalu besar (maks 5MB).'])->withInput();
                }
                $ext = strtolower($file->getClientOriginalExtension());
                $allowed = ['jpg','jpeg','png','pdf'];
                $mime = $file->getMimeType();
                $allowedMime = ['image/jpeg','image/png','application/pdf'];
                if (!in_array($ext, $allowed, true) || !in_array($mime, $allowedMime, true)) {
                    return back()->withErrors(['special_attachment_file' => 'Format lampiran harus JPG/PNG/PDF.'])->withInput();
                }
                $filename = 'permit_attachment_' . uniqid() . '.' . $ext;
                $file->move($dir, $filename);
                $attachmentPath = 'uploads/permissions/' . $filename;
            }

            $item = AbsenceRequest::create([
                'company_id' => $companyId,
                'employee_id' => $employeeId,
                'requester_user_id' => $requesterUserId,
                'request_type' => $data['request_type'],
                'date_start' => $data['date_start'],
                'date_end' => $data['date_end'],
                'reason' => $data['reason'] ?? null,
                'attachment_path' => $attachmentPath,
                'doctor_note_path' => $doctorNotePath,
                'status' => 'Pending Approval 1',
            ]);
            $this->buildRequestSteps($moduleKey, (int) $item->id, $companyId, $requesterUserId);
            $steps = $this->getRequestSteps($moduleKey, (int) $item->id);
            $firstPending = $this->pendingStep($steps);
            if ($firstPending && (int) ($firstPending->approver_user_id ?? 0) > 0) {
                $this->pushNotification(
                    $companyId,
                    (int) $firstPending->approver_user_id,
                    'Approval Izin (Step 1)',
                    'Pengajuan izin menunggu approval Anda.',
                    route('permissions.absence')
                );
            }

            return redirect()->route('permissions.absence');
        }

        $employees = Employee::where('company_id', $companyId)->orderBy('id')->get();
        $employeesById = $employees->keyBy('id');
        $itemsQuery = AbsenceRequest::where('company_id', $companyId)->orderByDesc('id');

        $filterEmployeeId = (int) $request->query('employee_id', 0);
        $filterStatus = trim((string) $request->query('status', ''));
        $filterFrom = trim((string) $request->query('from', ''));
        $filterTo = trim((string) $request->query('to', ''));
        $selfEmployeeIdResolved = 0;
        $selfCutiEligible = null;
        $selfCutiBalance = null;
        $selfCutiReason = null;
        if (($user['role'] ?? '') === 'Employee') {
            $selfEmployeeId = (int) ($user['employee_id'] ?? 0);
            if ($selfEmployeeId <= 0 && !empty($user['id'])) {
                $selfEmployeeId = (int) (User::where('id', (int) $user['id'])->value('employee_id') ?? 0);
            }
            if ($selfEmployeeId <= 0 && !empty($user['email'])) {
                $selfEmployeeId = (int) (User::where('email', (string) $user['email'])->value('employee_id') ?? 0);
            }
            if ($selfEmployeeId <= 0 && !empty($user['name'])) {
                $selfEmployeeId = (int) (Employee::where('company_id', $companyId)
                    ->where('name', (string) $user['name'])
                    ->value('id') ?? 0);
            }
            $selfEmployeeIdResolved = $selfEmployeeId;
            if ($selfEmployeeIdResolved > 0) {
                $selfEmployee = Employee::find($selfEmployeeIdResolved);
                if (!$selfEmployee && !empty($user['name'])) {
                    $selfEmployee = Employee::where('company_id', $companyId)
                        ->where('name', (string) $user['name'])
                        ->first();
                    if (!$selfEmployee) {
                        $selfEmployee = Employee::where('name', (string) $user['name'])->first();
                    }
                }
                if ($selfEmployee) {
                    $selfCutiEligible = $this->eligibleForCuti($selfEmployee->join_date ?? null);
                    $used = (int) ($cutiUsedMap[$selfEmployeeIdResolved] ?? 0);
                    $selfCutiBalance = max(0, $cutiQuota - $used);
                    if (!$selfCutiEligible) {
                        if (empty($selfEmployee->join_date)) {
                            $selfCutiReason = 'Tanggal join belum diisi.';
                        } else {
                            $selfCutiReason = 'Masa kerja belum 1 tahun (Join: ' . format_date_id($selfEmployee->join_date) . ').';
                        }
                    }
                } else {
                    $selfCutiReason = 'Data karyawan tidak ditemukan.';
                }
            } else {
                $selfCutiReason = 'Akun belum terhubung ke karyawan.';
            }
            $approverId = (int) ($user['id'] ?? 0);
            $requesterUserIds = ApprovalSetting::where('company_id', $companyId)
                ->where('module_key', 'absence')
                ->where(function ($q) use ($approverId) {
                    $q->where('approver1_user_id', $approverId)
                        ->orWhere('approver2_user_id', $approverId);
                })
                ->pluck('requester_user_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $requesterEmployeeIds = [];
            if (!empty($requesterUserIds)) {
                $requesterEmployeeIds = User::whereIn('id', $requesterUserIds)
                    ->whereNotNull('employee_id')
                    ->pluck('employee_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }

            $itemsQuery->where(function ($q) use ($selfEmployeeId, $requesterUserIds, $requesterEmployeeIds) {
                if ($selfEmployeeId > 0) {
                    $q->where('employee_id', $selfEmployeeId);
                }
                if (!empty($requesterUserIds)) {
                    $q->orWhereIn('requester_user_id', $requesterUserIds);
                }
                if (!empty($requesterEmployeeIds)) {
                    $q->orWhereIn('employee_id', $requesterEmployeeIds);
                }
            });
        }
        if ($filterEmployeeId > 0) {
            $itemsQuery->where('employee_id', $filterEmployeeId);
        }
        if ($filterStatus !== '') {
            $itemsQuery->where('status', $filterStatus);
        }
        if ($filterFrom !== '') {
            $itemsQuery->whereDate('date_start', '>=', $filterFrom);
        }
        if ($filterTo !== '') {
            $itemsQuery->whereDate('date_end', '<=', $filterTo);
        }

        $items = $itemsQuery->get();

        if ($items->isNotEmpty() && Schema::hasTable('approval_request_steps')) {
            foreach ($items as $it) {
                if (in_array($it->status, ['Approved','Rejected'], true)) {
                    continue;
                }
                $existing = ApprovalRequestStep::where('module_key', 'out_office')
                    ->where('request_id', (int) $it->id)
                    ->exists();
                if (!$existing) {
                    $resolvedRequesterId = $this->resolveRequesterUserId((int) ($it->requester_user_id ?? 0), (int) ($it->employee_id ?? 0));
                    $this->buildRequestSteps('out_office', (int) $it->id, $companyId, $resolvedRequesterId);
                }
            }
        }

        if ($items->isNotEmpty() && Schema::hasTable('approval_request_steps')) {
            foreach ($items as $it) {
                if (in_array($it->status, ['Approved','Rejected'], true)) {
                    continue;
                }
                $existing = ApprovalRequestStep::where('module_key', 'absence')
                    ->where('request_id', (int) $it->id)
                    ->exists();
                if (!$existing) {
                    $resolvedRequesterId = $this->resolveRequesterUserId((int) ($it->requester_user_id ?? 0), (int) ($it->employee_id ?? 0));
                    $this->buildRequestSteps('absence', (int) $it->id, $companyId, $resolvedRequesterId);
                }
            }
        }

        $stepMap = [];
        $firstStepStatus = [];
        $lastStepStatus = [];
        $pendingStepNo = [];
        $pendingApproverId = [];
        if ($items->isNotEmpty() && Schema::hasTable('approval_request_steps')) {
            $rows = ApprovalRequestStep::where('module_key', 'out_office')
                ->whereIn('request_id', $items->pluck('id')->all())
                ->orderBy('step_no')
                ->get();
            foreach ($rows as $r) {
                $stepMap[$r->request_id][] = $r;
            }
            foreach ($stepMap as $reqId => $steps) {
                $firstStepStatus[$reqId] = $steps[0]->status ?? null;
                $last = end($steps);
                $lastStepStatus[$reqId] = $last->status ?? null;
                foreach ($steps as $s) {
                    if (($s->status ?? '') === 'Pending') {
                        $pendingStepNo[$reqId] = (int) $s->step_no;
                        $pendingApproverId[$reqId] = (int) ($s->approver_user_id ?? 0);
                        break;
                    }
                }
            }
        }

        $stepMap = [];
        $firstStepStatus = [];
        $lastStepStatus = [];
        $pendingStepNo = [];
        $pendingApproverId = [];
        if ($items->isNotEmpty() && Schema::hasTable('approval_request_steps')) {
            $rows = ApprovalRequestStep::where('module_key', 'absence')
                ->whereIn('request_id', $items->pluck('id')->all())
                ->orderBy('step_no')
                ->get();
            foreach ($rows as $r) {
                $stepMap[$r->request_id][] = $r;
            }
            foreach ($stepMap as $reqId => $steps) {
                $firstStepStatus[$reqId] = $steps[0]->status ?? null;
                $last = end($steps);
                $lastStepStatus[$reqId] = $last->status ?? null;
                foreach ($steps as $s) {
                    if (($s->status ?? '') === 'Pending') {
                        $pendingStepNo[$reqId] = (int) $s->step_no;
                        $pendingApproverId[$reqId] = (int) ($s->approver_user_id ?? 0);
                        break;
                    }
                }
            }
        }

        $approvalMap = collect();
        $userIdByEmployeeId = collect();
        if (Schema::hasTable('approval_settings')) {
            $approvalMap = ApprovalSetting::where('company_id', $companyId)
                ->where('module_key', 'absence')
                ->get()
                ->keyBy('requester_user_id');
        }
        $userIdByEmployeeId = User::whereNotNull('employee_id')->pluck('id', 'employee_id');

        $cutiBalances = [];
        $cutiEligibility = [];
        foreach ($employees as $e) {
            $used = (int) ($cutiUsedMap[$e->id] ?? 0);
            $cutiBalances[$e->id] = max(0, $cutiQuota - $used);
            $cutiEligibility[$e->id] = $this->eligibleForCuti($e->join_date ?? null);
        }

        return view('modules.permissions.absence', compact(
            'user',
            'companyId',
            'employees',
            'employeesById',
            'items',
            'approvalMap',
            'userIdByEmployeeId',
            'filterEmployeeId',
            'filterStatus',
            'filterFrom',
            'filterTo',
            'cutiBalances',
            'cutiEligibility',
            'cutiQuota',
            'holidayDates',
            'firstStepStatus',
            'lastStepStatus',
            'pendingStepNo',
            'pendingApproverId',
            'selfEmployeeIdResolved',
            'selfCutiEligible',
            'selfCutiBalance',
            'selfCutiReason'
        ));
    }

    public function absencePdf(Request $request, int $id)
    {
        $user = current_user();
        $companyId = current_company_id();
        $item = AbsenceRequest::find((int) $id);
        if (!$item || (int) $item->company_id !== (int) $companyId) {
            abort(404, 'Data tidak ditemukan.');
        }

        if (($user['role'] ?? '') === 'Employee') {
            $selfEmployeeId = (int) ($user['employee_id'] ?? 0);
            $approverId = (int) ($user['id'] ?? 0);
            $allowed = false;

            if ($selfEmployeeId > 0 && (int) $item->employee_id === $selfEmployeeId) {
                $allowed = true;
            } else {
                $resolvedRequesterId = $this->resolveRequesterUserId((int) ($item->requester_user_id ?? 0), (int) ($item->employee_id ?? 0));
                if ($resolvedRequesterId > 0 && Schema::hasTable('approval_settings')) {
                    $approval = ApprovalSetting::where('company_id', $companyId)
                        ->where('module_key', 'absence')
                        ->where('requester_user_id', $resolvedRequesterId)
                        ->first();
                    if ($approval) {
                        $allowed = (int) ($approval->approver1_user_id ?? 0) === $approverId
                            || (int) ($approval->approver2_user_id ?? 0) === $approverId;
                    }
                }
            }

            if (!$allowed) {
                abort(403, 'Access denied.');
            }
        }

        $employee = Employee::find((int) $item->employee_id);
        $company = Company::find((int) $companyId);

        $requesterUserId = $this->resolveRequesterUserId((int) ($item->requester_user_id ?? 0), (int) ($item->employee_id ?? 0));
        $requesterUser = $requesterUserId > 0 ? User::find($requesterUserId) : null;
        $approver1User = $item->atasan_approved_by ? User::find((int) $item->atasan_approved_by) : null;
        $approver2User = $item->hrd_approved_by ? User::find((int) $item->hrd_approved_by) : null;

        $logoDataUri = $this->resolveImageDataUri($company->logo_path ?? null);
        $requesterSignature = $this->resolveImageDataUri($requesterUser->signature_path ?? null);
        $approver1Signature = $this->resolveImageDataUri($approver1User->signature_path ?? null);
        $approver2Signature = $this->resolveImageDataUri($approver2User->signature_path ?? null);

        $html = view('modules.permissions.absence_pdf', compact(
            'item',
            'employee',
            'company',
            'requesterUser',
            'approver1User',
            'approver2User',
            'logoDataUri',
            'requesterSignature',
            'approver1Signature',
            'approver2Signature'
        ))->render();

        $dompdf = new Dompdf([
            'defaultFont' => 'DejaVu Sans',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'chroot' => base_path(),
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A5', 'landscape');
        $dompdf->render();

        return response($dompdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="surat_izin_' . (int) $item->id . '.pdf"');
    }

    public function absencePreview(Request $request, int $id)
    {
        $user = current_user();
        $companyId = current_company_id();
        $item = AbsenceRequest::find((int) $id);
        if (!$item || (int) $item->company_id !== (int) $companyId) {
            abort(404, 'Data tidak ditemukan.');
        }

        if (($user['role'] ?? '') === 'Employee') {
            $selfEmployeeId = (int) ($user['employee_id'] ?? 0);
            $approverId = (int) ($user['id'] ?? 0);
            $allowed = false;

            if ($selfEmployeeId > 0 && (int) $item->employee_id === $selfEmployeeId) {
                $allowed = true;
            } else {
                $resolvedRequesterId = $this->resolveRequesterUserId((int) ($item->requester_user_id ?? 0), (int) ($item->employee_id ?? 0));
                if ($resolvedRequesterId > 0 && Schema::hasTable('approval_settings')) {
                    $approval = ApprovalSetting::where('company_id', $companyId)
                        ->where('module_key', 'absence')
                        ->where('requester_user_id', $resolvedRequesterId)
                        ->first();
                    if ($approval) {
                        $allowed = (int) ($approval->approver1_user_id ?? 0) === $approverId
                            || (int) ($approval->approver2_user_id ?? 0) === $approverId;
                    }
                }
            }

            if (!$allowed) {
                abort(403, 'Access denied.');
            }
        }

        $employee = Employee::find((int) $item->employee_id);
        $company = Company::find((int) $companyId);

        $requesterUserId = $this->resolveRequesterUserId((int) ($item->requester_user_id ?? 0), (int) ($item->employee_id ?? 0));
        $requesterUser = $requesterUserId > 0 ? User::find($requesterUserId) : null;
        $approver1User = $item->atasan_approved_by ? User::find((int) $item->atasan_approved_by) : null;
        $approver2User = $item->hrd_approved_by ? User::find((int) $item->hrd_approved_by) : null;

        $logoDataUri = $this->resolveImageDataUri($company->logo_path ?? null);
        $requesterSignature = $this->resolveImageDataUri($requesterUser->signature_path ?? null);
        $approver1Signature = $this->resolveImageDataUri($approver1User->signature_path ?? null);
        $approver2Signature = $this->resolveImageDataUri($approver2User->signature_path ?? null);

        return view('modules.permissions.absence_pdf', compact(
            'item',
            'employee',
            'company',
            'requesterUser',
            'approver1User',
            'approver2User',
            'logoDataUri',
            'requesterSignature',
            'approver1Signature',
            'approver2Signature'
        ));
    }

    public function outOffice(Request $request)
    {
        $user = current_user();
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            return redirect()->route('permissions.out_office');
        }
        $companyId = current_company_id();

        if ($request->isMethod('post')) {
            $moduleKey = 'out_office';
            $action = $request->input('action', 'create');

            if ($action === 'approve_step') {
                $data = $request->validate([
                    'id' => ['required','integer'],
                ]);
                $item = OutOfficeRequest::find((int) $data['id']);
                if (!$item || (int)$item->company_id !== (int)$companyId) {
                    return redirect()->route('permissions.out_office');
                }
                $resolvedRequesterId = $this->resolveRequesterUserId((int) ($item->requester_user_id ?? 0), (int) ($item->employee_id ?? 0));
                $steps = $this->ensureRequestSteps($moduleKey, $item, $companyId, $resolvedRequesterId);
                $pending = $this->pendingStep($steps);
                if (!$this->canApproveStep($pending, $user)) {
                    abort(403, 'Access denied.');
                }
                if ($pending) {
                    ApprovalRequestStep::where('id', (int) $pending->id)->update([
                        'status' => 'Approved',
                        'approved_by' => (int) ($user['id'] ?? 0),
                        'approved_at' => now(),
                        'signature' => 'Approved',
                    ]);

                    $steps = $this->getRequestSteps($moduleKey, (int) $item->id);
                    $next = $this->pendingStep($steps);
                    $lastStepNo = !empty($steps) ? (int) end($steps)->step_no : 0;
                    if (!$next) {
                        $item->status = 'Approved';
                        $item->hrd_approved_by = (int) ($user['id'] ?? 0);
                        $item->hrd_approved_at = now();
                        $item->hrd_signature = 'Approved';
                    } else {
                        $item->status = 'Pending Approval ' . (int) ($next->step_no ?? 1);
                    }
                    if ((int) ($pending->step_no ?? 0) === 1) {
                        $item->atasan_approved_by = (int) ($user['id'] ?? 0);
                        $item->atasan_approved_at = now();
                        $item->atasan_signature = 'Approved';
                    }
                    if ($lastStepNo > 0 && (int) ($pending->step_no ?? 0) === $lastStepNo) {
                        $item->hrd_approved_by = (int) ($user['id'] ?? 0);
                        $item->hrd_approved_at = now();
                        $item->hrd_signature = 'Approved';
                    }
                    $item->save();

                    if ($next && (int) ($next->approver_user_id ?? 0) > 0) {
                        $this->pushNotification(
                            $companyId,
                            (int) $next->approver_user_id,
                            'Approval Izin Keluar (Step ' . (int) ($next->step_no ?? 1) . ')',
                            'Pengajuan izin keluar menunggu approval Anda.',
                            route('permissions.out_office')
                        );
                    } elseif (!$next) {
                        $this->pushNotification(
                            $companyId,
                            (int) $resolvedRequesterId,
                            'Pengajuan Izin Keluar Disetujui',
                            'Pengajuan izin keluar Anda telah disetujui.',
                            route('permissions.out_office')
                        );
                    }
                }
                return redirect()->route('permissions.out_office');
            }

            if ($action === 'reject') {
                $data = $request->validate([
                    'id' => ['required','integer'],
                    'note' => ['nullable','string','max:255'],
                ]);
                $item = OutOfficeRequest::find((int) $data['id']);
                if (!$item || (int)$item->company_id !== (int)$companyId) {
                    return redirect()->route('permissions.out_office');
                }
                $resolvedRequesterId = $this->resolveRequesterUserId((int) ($item->requester_user_id ?? 0), (int) ($item->employee_id ?? 0));
                $steps = $this->ensureRequestSteps($moduleKey, $item, $companyId, $resolvedRequesterId);
                $pending = $this->pendingStep($steps);
                $canReject = $this->canApproveStep($pending, $user);
                if (!$canReject) {
                    abort(403, 'Access denied.');
                }
                if (!in_array($item->status, ['Approved','Rejected'], true)) {
                    $item->status = 'Rejected';
                    $item->rejected_by = (int) ($user['id'] ?? 0);
                    $item->rejected_at = now();
                    $item->rejected_note = $data['note'] ?? null;
                    $item->save();
                    if ($pending) {
                        ApprovalRequestStep::where('id', (int) $pending->id)->update([
                            'status' => 'Rejected',
                        ]);
                    }
                    $this->pushNotification(
                        $companyId,
                        (int) $resolvedRequesterId,
                        'Pengajuan Izin Keluar Ditolak',
                        'Pengajuan izin keluar Anda ditolak. ' . trim((string) ($data['note'] ?? '')),
                        route('permissions.out_office')
                    );
                }
                return redirect()->route('permissions.out_office');
            }

            $data = $request->validate([
                'date' => ['required','date'],
                'time_start' => ['required','date_format:H:i'],
                'time_end' => ['required','date_format:H:i'],
                'destination' => ['nullable','string','max:150'],
                'reason' => ['nullable','string','max:255'],
                'employee_id' => ['nullable','integer'],
            ]);

            $employeeId = (int) ($data['employee_id'] ?? 0);
            if (($user['role'] ?? '') === 'Employee') {
                $employeeId = (int) ($user['employee_id'] ?? 0);
            }
            if ($employeeId <= 0) {
                return back()->withErrors(['employee_id' => 'Employee wajib dipilih.'])->withInput();
            }
            $requesterUserId = (int) ($user['id'] ?? 0);
            if (($user['role'] ?? '') !== 'Employee') {
                $mappedUserId = (int) (User::where('employee_id', $employeeId)->value('id') ?? 0);
                if ($mappedUserId > 0) {
                    $requesterUserId = $mappedUserId;
                }
            }
            $flowForCreate = $this->approvalFlow($companyId, $moduleKey, $requesterUserId);

            $item = OutOfficeRequest::create([
                'company_id' => $companyId,
                'employee_id' => $employeeId,
                'requester_user_id' => $requesterUserId,
                'date' => $data['date'],
                'time_start' => $data['time_start'],
                'time_end' => $data['time_end'],
                'destination' => $data['destination'] ?? null,
                'reason' => $data['reason'] ?? null,
                'status' => 'Pending Approval 1',
            ]);
            $this->buildRequestSteps($moduleKey, (int) $item->id, $companyId, $requesterUserId);
            $steps = $this->getRequestSteps($moduleKey, (int) $item->id);
            $firstPending = $this->pendingStep($steps);
            if ($firstPending && (int) ($firstPending->approver_user_id ?? 0) > 0) {
                $this->pushNotification(
                    $companyId,
                    (int) $firstPending->approver_user_id,
                    'Approval Izin Keluar (Step 1)',
                    'Pengajuan izin keluar menunggu approval Anda.',
                    route('permissions.out_office')
                );
            }

            return redirect()->route('permissions.out_office');
        }

        $employees = Employee::where('company_id', $companyId)->orderBy('id')->get();
        $employeesById = $employees->keyBy('id');
        $itemsQuery = OutOfficeRequest::where('company_id', $companyId)->orderByDesc('id');
        $filterEmployeeId = (int) $request->query('employee_id', 0);
        $filterStatus = trim((string) $request->query('status', ''));
        $filterFrom = trim((string) $request->query('from', ''));
        $filterTo = trim((string) $request->query('to', ''));
        if (($user['role'] ?? '') === 'Employee') {
            $selfEmployeeId = (int) ($user['employee_id'] ?? 0);
            $approverId = (int) ($user['id'] ?? 0);
            $requesterUserIds = ApprovalSetting::where('company_id', $companyId)
                ->where('module_key', 'out_office')
                ->where(function ($q) use ($approverId) {
                    $q->where('approver1_user_id', $approverId)
                        ->orWhere('approver2_user_id', $approverId);
                })
                ->pluck('requester_user_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $requesterEmployeeIds = [];
            if (!empty($requesterUserIds)) {
                $requesterEmployeeIds = User::whereIn('id', $requesterUserIds)
                    ->whereNotNull('employee_id')
                    ->pluck('employee_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }

            $itemsQuery->where(function ($q) use ($selfEmployeeId, $requesterUserIds, $requesterEmployeeIds) {
                if ($selfEmployeeId > 0) {
                    $q->where('employee_id', $selfEmployeeId);
                }
                if (!empty($requesterUserIds)) {
                    $q->orWhereIn('requester_user_id', $requesterUserIds);
                }
                if (!empty($requesterEmployeeIds)) {
                    $q->orWhereIn('employee_id', $requesterEmployeeIds);
                }
            });
        }
        if ($filterEmployeeId > 0) {
            $itemsQuery->where('employee_id', $filterEmployeeId);
        }
        if ($filterStatus !== '') {
            $itemsQuery->where('status', $filterStatus);
        }
        if ($filterFrom !== '') {
            $itemsQuery->whereDate('date', '>=', $filterFrom);
        }
        if ($filterTo !== '') {
            $itemsQuery->whereDate('date', '<=', $filterTo);
        }

        $items = $itemsQuery->get();

        if ($items->isNotEmpty() && Schema::hasTable('approval_request_steps')) {
            foreach ($items as $it) {
                if (in_array($it->status, ['Approved','Rejected'], true)) {
                    continue;
                }
                $existing = ApprovalRequestStep::where('module_key', 'out_office')
                    ->where('request_id', (int) $it->id)
                    ->exists();
                if (!$existing) {
                    $resolvedRequesterId = $this->resolveRequesterUserId((int) ($it->requester_user_id ?? 0), (int) ($it->employee_id ?? 0));
                    $this->buildRequestSteps('out_office', (int) $it->id, $companyId, $resolvedRequesterId);
                }
            }
        }

        $stepMap = [];
        $firstStepStatus = [];
        $lastStepStatus = [];
        $pendingStepNo = [];
        $pendingApproverId = [];
        if ($items->isNotEmpty() && Schema::hasTable('approval_request_steps')) {
            $rows = ApprovalRequestStep::where('module_key', 'out_office')
                ->whereIn('request_id', $items->pluck('id')->all())
                ->orderBy('step_no')
                ->get();
            foreach ($rows as $r) {
                $stepMap[$r->request_id][] = $r;
            }
            foreach ($stepMap as $reqId => $steps) {
                $firstStepStatus[$reqId] = $steps[0]->status ?? null;
                $last = end($steps);
                $lastStepStatus[$reqId] = $last->status ?? null;
                foreach ($steps as $s) {
                    if (($s->status ?? '') === 'Pending') {
                        $pendingStepNo[$reqId] = (int) $s->step_no;
                        $pendingApproverId[$reqId] = (int) ($s->approver_user_id ?? 0);
                        break;
                    }
                }
            }
        }

        $approvalMap = collect();
        $userIdByEmployeeId = collect();
        if (Schema::hasTable('approval_settings')) {
            $approvalMap = ApprovalSetting::where('company_id', $companyId)
                ->where('module_key', 'out_office')
                ->get()
                ->keyBy('requester_user_id');
        }
        $userIdByEmployeeId = User::whereNotNull('employee_id')->pluck('id', 'employee_id');

        return view('modules.permissions.out_office', compact(
            'user',
            'companyId',
            'employees',
            'employeesById',
            'items',
            'approvalMap',
            'userIdByEmployeeId',
            'filterEmployeeId',
            'filterStatus',
            'filterFrom',
            'filterTo',
            'firstStepStatus',
            'lastStepStatus',
            'pendingStepNo',
            'pendingApproverId'
        ));
    }

    public function outOfficePdf(Request $request, int $id)
    {
        $user = current_user();
        $companyId = current_company_id();
        $item = OutOfficeRequest::find((int) $id);
        if (!$item || (int) $item->company_id !== (int) $companyId) {
            abort(404, 'Data tidak ditemukan.');
        }

        if (($user['role'] ?? '') === 'Employee') {
            $selfEmployeeId = (int) ($user['employee_id'] ?? 0);
            $approverId = (int) ($user['id'] ?? 0);
            $allowed = false;

            if ($selfEmployeeId > 0 && (int) $item->employee_id === $selfEmployeeId) {
                $allowed = true;
            } else {
                $resolvedRequesterId = $this->resolveRequesterUserId((int) ($item->requester_user_id ?? 0), (int) ($item->employee_id ?? 0));
                if ($resolvedRequesterId > 0 && Schema::hasTable('approval_settings')) {
                    $approval = ApprovalSetting::where('company_id', $companyId)
                        ->where('module_key', 'out_office')
                        ->where('requester_user_id', $resolvedRequesterId)
                        ->first();
                    if ($approval) {
                        $allowed = (int) ($approval->approver1_user_id ?? 0) === $approverId
                            || (int) ($approval->approver2_user_id ?? 0) === $approverId;
                    }
                }
            }

            if (!$allowed) {
                abort(403, 'Access denied.');
            }
        }

        $employee = Employee::find((int) $item->employee_id);
        $company = Company::find((int) $companyId);

        $requesterUserId = $this->resolveRequesterUserId((int) ($item->requester_user_id ?? 0), (int) ($item->employee_id ?? 0));
        $requesterUser = $requesterUserId > 0 ? User::find($requesterUserId) : null;
        $approver1User = $item->atasan_approved_by ? User::find((int) $item->atasan_approved_by) : null;
        $approver2User = $item->hrd_approved_by ? User::find((int) $item->hrd_approved_by) : null;

        $logoDataUri = $this->resolveImageDataUri($company->logo_path ?? null);
        $requesterSignature = $this->resolveImageDataUri($requesterUser->signature_path ?? null);
        $approver1Signature = $this->resolveImageDataUri($approver1User->signature_path ?? null);
        $approver2Signature = $this->resolveImageDataUri($approver2User->signature_path ?? null);

        $html = view('modules.permissions.out_office_pdf', compact(
            'item',
            'employee',
            'company',
            'requesterUser',
            'approver1User',
            'approver2User',
            'logoDataUri',
            'requesterSignature',
            'approver1Signature',
            'approver2Signature'
        ))->render();

        $dompdf = new \Dompdf\Dompdf([
            'defaultFont' => 'DejaVu Sans',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'chroot' => base_path(),
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A5', 'landscape');
        $dompdf->render();

        return response($dompdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="surat_izin_keluar_' . (int) $item->id . '.pdf"');
    }

    public function outOfficePreview(Request $request, int $id)
    {
        $user = current_user();
        $companyId = current_company_id();
        $item = OutOfficeRequest::find((int) $id);
        if (!$item || (int) $item->company_id !== (int) $companyId) {
            abort(404, 'Data tidak ditemukan.');
        }

        if (($user['role'] ?? '') === 'Employee') {
            $selfEmployeeId = (int) ($user['employee_id'] ?? 0);
            $approverId = (int) ($user['id'] ?? 0);
            $allowed = false;

            if ($selfEmployeeId > 0 && (int) $item->employee_id === $selfEmployeeId) {
                $allowed = true;
            } else {
                $resolvedRequesterId = $this->resolveRequesterUserId((int) ($item->requester_user_id ?? 0), (int) ($item->employee_id ?? 0));
                if ($resolvedRequesterId > 0 && Schema::hasTable('approval_settings')) {
                    $approval = ApprovalSetting::where('company_id', $companyId)
                        ->where('module_key', 'out_office')
                        ->where('requester_user_id', $resolvedRequesterId)
                        ->first();
                    if ($approval) {
                        $allowed = (int) ($approval->approver1_user_id ?? 0) === $approverId
                            || (int) ($approval->approver2_user_id ?? 0) === $approverId;
                    }
                }
            }

            if (!$allowed) {
                abort(403, 'Access denied.');
            }
        }

        $employee = Employee::find((int) $item->employee_id);
        $company = Company::find((int) $companyId);

        $requesterUserId = $this->resolveRequesterUserId((int) ($item->requester_user_id ?? 0), (int) ($item->employee_id ?? 0));
        $requesterUser = $requesterUserId > 0 ? User::find($requesterUserId) : null;
        $approver1User = $item->atasan_approved_by ? User::find((int) $item->atasan_approved_by) : null;
        $approver2User = $item->hrd_approved_by ? User::find((int) $item->hrd_approved_by) : null;

        $logoDataUri = $this->resolveImageDataUri($company->logo_path ?? null);
        $requesterSignature = $this->resolveImageDataUri($requesterUser->signature_path ?? null);
        $approver1Signature = $this->resolveImageDataUri($approver1User->signature_path ?? null);
        $approver2Signature = $this->resolveImageDataUri($approver2User->signature_path ?? null);

        return view('modules.permissions.out_office_pdf', compact(
            'item',
            'employee',
            'company',
            'requesterUser',
            'approver1User',
            'approver2User',
            'logoDataUri',
            'requesterSignature',
            'approver1Signature',
            'approver2Signature'
        ));
    }

    public function overtime(Request $request)
    {
        $user = current_user();
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            return redirect()->route('permissions.overtime');
        }
        $companyId = current_company_id();

        if ($request->isMethod('post')) {
            $moduleKey = 'overtime';
            $action = $request->input('action', 'create');

            if ($action === 'approve_step') {
                $data = $request->validate([
                    'id' => ['required','integer'],
                ]);
                $item = OvertimeRequest::find((int) $data['id']);
                if (!$item || (int)$item->company_id !== (int)$companyId) {
                    return redirect()->route('permissions.overtime');
                }
                $resolvedRequesterId = $this->resolveRequesterUserId((int) ($item->requester_user_id ?? 0), (int) ($item->employee_id ?? 0));
                $steps = $this->ensureRequestSteps($moduleKey, $item, $companyId, $resolvedRequesterId);
                $pending = $this->pendingStep($steps);
                if (!$this->canApproveStep($pending, $user)) {
                    abort(403, 'Access denied.');
                }
                if ($pending) {
                    ApprovalRequestStep::where('id', (int) $pending->id)->update([
                        'status' => 'Approved',
                        'approved_by' => (int) ($user['id'] ?? 0),
                        'approved_at' => now(),
                        'signature' => 'Approved',
                    ]);

                    $steps = $this->getRequestSteps($moduleKey, (int) $item->id);
                    $next = $this->pendingStep($steps);
                    $lastStepNo = !empty($steps) ? (int) end($steps)->step_no : 0;
                    if (!$next) {
                        $item->status = 'Approved';
                        $item->hrd_approved_by = (int) ($user['id'] ?? 0);
                        $item->hrd_approved_at = now();
                        $item->hrd_signature = 'Approved';
                    } else {
                        $item->status = 'Pending Approval ' . (int) ($next->step_no ?? 1);
                    }
                    if ((int) ($pending->step_no ?? 0) === 1) {
                        $item->atasan_approved_by = (int) ($user['id'] ?? 0);
                        $item->atasan_approved_at = now();
                        $item->atasan_signature = 'Approved';
                    }
                    if ($lastStepNo > 0 && (int) ($pending->step_no ?? 0) === $lastStepNo) {
                        $item->hrd_approved_by = (int) ($user['id'] ?? 0);
                        $item->hrd_approved_at = now();
                        $item->hrd_signature = 'Approved';
                    }
                    $item->save();

                    if ($next && (int) ($next->approver_user_id ?? 0) > 0) {
                        $this->pushNotification(
                            $companyId,
                            (int) $next->approver_user_id,
                            'Approval Lembur (Step ' . (int) ($next->step_no ?? 1) . ')',
                            'Pengajuan lembur menunggu approval Anda.',
                            route('permissions.overtime')
                        );
                    } elseif (!$next) {
                        $this->pushNotification(
                            $companyId,
                            (int) $resolvedRequesterId,
                            'Pengajuan Lembur Disetujui',
                            'Pengajuan lembur Anda telah disetujui.',
                            route('permissions.overtime')
                        );
                    }
                }
                return redirect()->route('permissions.overtime');
            }

            if ($action === 'reject') {
                $data = $request->validate([
                    'id' => ['required','integer'],
                    'note' => ['nullable','string','max:255'],
                ]);
                $item = OvertimeRequest::find((int) $data['id']);
                if (!$item || (int)$item->company_id !== (int)$companyId) {
                    return redirect()->route('permissions.overtime');
                }
                $resolvedRequesterId = $this->resolveRequesterUserId((int) ($item->requester_user_id ?? 0), (int) ($item->employee_id ?? 0));
                $steps = $this->ensureRequestSteps($moduleKey, $item, $companyId, $resolvedRequesterId);
                $pending = $this->pendingStep($steps);
                $canReject = $this->canApproveStep($pending, $user);
                if (!$canReject) {
                    abort(403, 'Access denied.');
                }
                if (!in_array($item->status, ['Approved','Rejected'], true)) {
                    $item->status = 'Rejected';
                    $item->rejected_by = (int) ($user['id'] ?? 0);
                    $item->rejected_at = now();
                    $item->rejected_note = $data['note'] ?? null;
                    $item->save();
                    if ($pending) {
                        ApprovalRequestStep::where('id', (int) $pending->id)->update([
                            'status' => 'Rejected',
                        ]);
                    }
                    $this->pushNotification(
                        $companyId,
                        (int) $resolvedRequesterId,
                        'Pengajuan Lembur Ditolak',
                        'Pengajuan lembur Anda ditolak. ' . trim((string) ($data['note'] ?? '')),
                        route('permissions.overtime')
                    );
                }
                return redirect()->route('permissions.overtime');
            }

            $data = $request->validate([
                'date' => ['required','date'],
                'time_start' => ['required','date_format:H:i'],
                'time_end' => ['required','date_format:H:i'],
                'reason' => ['nullable','string','max:255'],
                'employee_id' => ['nullable','integer'],
            ]);

            $employeeId = (int) ($data['employee_id'] ?? 0);
            if (($user['role'] ?? '') === 'Employee') {
                $employeeId = (int) ($user['employee_id'] ?? 0);
            }
            if ($employeeId <= 0) {
                return back()->withErrors(['employee_id' => 'Employee wajib dipilih.'])->withInput();
            }
            $requesterUserId = (int) ($user['id'] ?? 0);
            if (($user['role'] ?? '') !== 'Employee') {
                $mappedUserId = (int) (User::where('employee_id', $employeeId)->value('id') ?? 0);
                if ($mappedUserId > 0) {
                    $requesterUserId = $mappedUserId;
                }
            }

            $item = OvertimeRequest::create([
                'company_id' => $companyId,
                'employee_id' => $employeeId,
                'requester_user_id' => $requesterUserId,
                'date' => $data['date'],
                'time_start' => $data['time_start'],
                'time_end' => $data['time_end'],
                'reason' => $data['reason'] ?? null,
                'status' => 'Pending Approval 1',
            ]);
            $this->buildRequestSteps($moduleKey, (int) $item->id, $companyId, $requesterUserId);
            $steps = $this->getRequestSteps($moduleKey, (int) $item->id);
            $firstPending = $this->pendingStep($steps);
            if ($firstPending && (int) ($firstPending->approver_user_id ?? 0) > 0) {
                $this->pushNotification(
                    $companyId,
                    (int) $firstPending->approver_user_id,
                    'Approval Lembur (Step 1)',
                    'Pengajuan lembur menunggu approval Anda.',
                    route('permissions.overtime')
                );
            }

            return redirect()->route('permissions.overtime');
        }

        $employees = Employee::where('company_id', $companyId)->orderBy('id')->get();
        $employeesById = $employees->keyBy('id');
        $itemsQuery = OvertimeRequest::where('company_id', $companyId)->orderByDesc('id');
        $filterEmployeeId = (int) $request->query('employee_id', 0);
        $filterStatus = trim((string) $request->query('status', ''));
        $filterFrom = trim((string) $request->query('from', ''));
        $filterTo = trim((string) $request->query('to', ''));
        if (($user['role'] ?? '') === 'Employee') {
            $selfEmployeeId = (int) ($user['employee_id'] ?? 0);
            $approverId = (int) ($user['id'] ?? 0);
            $requesterUserIds = ApprovalSetting::where('company_id', $companyId)
                ->where('module_key', 'overtime')
                ->where(function ($q) use ($approverId) {
                    $q->where('approver1_user_id', $approverId)
                        ->orWhere('approver2_user_id', $approverId);
                })
                ->pluck('requester_user_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $requesterEmployeeIds = [];
            if (!empty($requesterUserIds)) {
                $requesterEmployeeIds = User::whereIn('id', $requesterUserIds)
                    ->whereNotNull('employee_id')
                    ->pluck('employee_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }

            $itemsQuery->where(function ($q) use ($selfEmployeeId, $requesterUserIds, $requesterEmployeeIds) {
                if ($selfEmployeeId > 0) {
                    $q->where('employee_id', $selfEmployeeId);
                }
                if (!empty($requesterUserIds)) {
                    $q->orWhereIn('requester_user_id', $requesterUserIds);
                }
                if (!empty($requesterEmployeeIds)) {
                    $q->orWhereIn('employee_id', $requesterEmployeeIds);
                }
            });
        }
        if ($filterEmployeeId > 0) {
            $itemsQuery->where('employee_id', $filterEmployeeId);
        }
        if ($filterStatus !== '') {
            $itemsQuery->where('status', $filterStatus);
        }
        if ($filterFrom !== '') {
            $itemsQuery->whereDate('date', '>=', $filterFrom);
        }
        if ($filterTo !== '') {
            $itemsQuery->whereDate('date', '<=', $filterTo);
        }

        $items = $itemsQuery->get();

        if ($items->isNotEmpty() && Schema::hasTable('approval_request_steps')) {
            foreach ($items as $it) {
                if (in_array($it->status, ['Approved','Rejected'], true)) {
                    continue;
                }
                $existing = ApprovalRequestStep::where('module_key', 'overtime')
                    ->where('request_id', (int) $it->id)
                    ->exists();
                if (!$existing) {
                    $resolvedRequesterId = $this->resolveRequesterUserId((int) ($it->requester_user_id ?? 0), (int) ($it->employee_id ?? 0));
                    $this->buildRequestSteps('overtime', (int) $it->id, $companyId, $resolvedRequesterId);
                }
            }
        }

        $stepMap = [];
        $firstStepStatus = [];
        $lastStepStatus = [];
        $pendingStepNo = [];
        $pendingApproverId = [];
        if ($items->isNotEmpty() && Schema::hasTable('approval_request_steps')) {
            $rows = ApprovalRequestStep::where('module_key', 'overtime')
                ->whereIn('request_id', $items->pluck('id')->all())
                ->orderBy('step_no')
                ->get();
            foreach ($rows as $r) {
                $stepMap[$r->request_id][] = $r;
            }
            foreach ($stepMap as $reqId => $steps) {
                $firstStepStatus[$reqId] = $steps[0]->status ?? null;
                $last = end($steps);
                $lastStepStatus[$reqId] = $last->status ?? null;
                foreach ($steps as $s) {
                    if (($s->status ?? '') === 'Pending') {
                        $pendingStepNo[$reqId] = (int) $s->step_no;
                        $pendingApproverId[$reqId] = (int) ($s->approver_user_id ?? 0);
                        break;
                    }
                }
            }
        }

        $approvalMap = collect();
        $userIdByEmployeeId = collect();
        if (Schema::hasTable('approval_settings')) {
            $approvalMap = ApprovalSetting::where('company_id', $companyId)
                ->where('module_key', 'overtime')
                ->get()
                ->keyBy('requester_user_id');
        }
        $userIdByEmployeeId = User::whereNotNull('employee_id')->pluck('id', 'employee_id');

        return view('modules.permissions.overtime', compact(
            'user',
            'companyId',
            'employees',
            'employeesById',
            'items',
            'approvalMap',
            'userIdByEmployeeId',
            'filterEmployeeId',
            'filterStatus',
            'filterFrom',
            'filterTo',
            'firstStepStatus',
            'lastStepStatus',
            'pendingStepNo',
            'pendingApproverId'
        ));
    }

    public function overtimePdf(Request $request, int $id)
    {
        $user = current_user();
        $companyId = current_company_id();
        $item = OvertimeRequest::find((int) $id);
        if (!$item || (int) $item->company_id !== (int) $companyId) {
            abort(404, 'Data tidak ditemukan.');
        }

        if (($user['role'] ?? '') === 'Employee') {
            $selfEmployeeId = (int) ($user['employee_id'] ?? 0);
            $approverId = (int) ($user['id'] ?? 0);
            $allowed = false;

            if ($selfEmployeeId > 0 && (int) $item->employee_id === $selfEmployeeId) {
                $allowed = true;
            } else {
                $resolvedRequesterId = $this->resolveRequesterUserId((int) ($item->requester_user_id ?? 0), (int) ($item->employee_id ?? 0));
                if ($resolvedRequesterId > 0 && Schema::hasTable('approval_settings')) {
                    $approval = ApprovalSetting::where('company_id', $companyId)
                        ->where('module_key', 'overtime')
                        ->where('requester_user_id', $resolvedRequesterId)
                        ->first();
                    if ($approval) {
                        $allowed = (int) ($approval->approver1_user_id ?? 0) === $approverId
                            || (int) ($approval->approver2_user_id ?? 0) === $approverId;
                    }
                }
            }

            if (!$allowed) {
                abort(403, 'Access denied.');
            }
        }

        $employee = Employee::find((int) $item->employee_id);
        $company = Company::find((int) $companyId);

        $requesterUserId = $this->resolveRequesterUserId((int) ($item->requester_user_id ?? 0), (int) ($item->employee_id ?? 0));
        $requesterUser = $requesterUserId > 0 ? User::find($requesterUserId) : null;
        $approver1User = $item->atasan_approved_by ? User::find((int) $item->atasan_approved_by) : null;
        $approver2User = $item->hrd_approved_by ? User::find((int) $item->hrd_approved_by) : null;

        $logoDataUri = $this->resolveImageDataUri($company->logo_path ?? null);
        $requesterSignature = $this->resolveImageDataUri($requesterUser->signature_path ?? null);
        $approver1Signature = $this->resolveImageDataUri($approver1User->signature_path ?? null);
        $approver2Signature = $this->resolveImageDataUri($approver2User->signature_path ?? null);

        $html = view('modules.permissions.overtime_pdf', compact(
            'item',
            'employee',
            'company',
            'requesterUser',
            'approver1User',
            'approver2User',
            'logoDataUri',
            'requesterSignature',
            'approver1Signature',
            'approver2Signature'
        ))->render();

        $dompdf = new \Dompdf\Dompdf([
            'defaultFont' => 'DejaVu Sans',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'chroot' => base_path(),
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A5', 'landscape');
        $dompdf->render();

        return response($dompdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="surat_lembur_' . (int) $item->id . '.pdf"');
    }

    public function overtimePreview(Request $request, int $id)
    {
        $user = current_user();
        $companyId = current_company_id();
        $item = OvertimeRequest::find((int) $id);
        if (!$item || (int) $item->company_id !== (int) $companyId) {
            abort(404, 'Data tidak ditemukan.');
        }

        if (($user['role'] ?? '') === 'Employee') {
            $selfEmployeeId = (int) ($user['employee_id'] ?? 0);
            $approverId = (int) ($user['id'] ?? 0);
            $allowed = false;

            if ($selfEmployeeId > 0 && (int) $item->employee_id === $selfEmployeeId) {
                $allowed = true;
            } else {
                $resolvedRequesterId = $this->resolveRequesterUserId((int) ($item->requester_user_id ?? 0), (int) ($item->employee_id ?? 0));
                if ($resolvedRequesterId > 0 && Schema::hasTable('approval_settings')) {
                    $approval = ApprovalSetting::where('company_id', $companyId)
                        ->where('module_key', 'overtime')
                        ->where('requester_user_id', $resolvedRequesterId)
                        ->first();
                    if ($approval) {
                        $allowed = (int) ($approval->approver1_user_id ?? 0) === $approverId
                            || (int) ($approval->approver2_user_id ?? 0) === $approverId;
                    }
                }
            }

            if (!$allowed) {
                abort(403, 'Access denied.');
            }
        }

        $employee = Employee::find((int) $item->employee_id);
        $company = Company::find((int) $companyId);

        $requesterUserId = $this->resolveRequesterUserId((int) ($item->requester_user_id ?? 0), (int) ($item->employee_id ?? 0));
        $requesterUser = $requesterUserId > 0 ? User::find($requesterUserId) : null;
        $approver1User = $item->atasan_approved_by ? User::find((int) $item->atasan_approved_by) : null;
        $approver2User = $item->hrd_approved_by ? User::find((int) $item->hrd_approved_by) : null;

        $logoDataUri = $this->resolveImageDataUri($company->logo_path ?? null);
        $requesterSignature = $this->resolveImageDataUri($requesterUser->signature_path ?? null);
        $approver1Signature = $this->resolveImageDataUri($approver1User->signature_path ?? null);
        $approver2Signature = $this->resolveImageDataUri($approver2User->signature_path ?? null);

        return view('modules.permissions.overtime_pdf', compact(
            'item',
            'employee',
            'company',
            'requesterUser',
            'approver1User',
            'approver2User',
            'logoDataUri',
            'requesterSignature',
            'approver1Signature',
            'approver2Signature'
        ));
    }
}

