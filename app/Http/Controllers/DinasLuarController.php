<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequestStep;
use App\Models\ApprovalSetting;
use App\Models\ApprovalStep;
use App\Models\Company;
use App\Models\DinasLuarRequest;
use App\Models\DinasLuarLumpsum;
use App\Models\DinasLuarFacility;
use App\Models\DinasLuarOther;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Dompdf\Dompdf;

class DinasLuarController extends Controller
{
    private string $moduleKey = 'dinas_luar';

    private function canManageAmounts(array $user): bool
    {
        $role = (string) ($user['role'] ?? '');
        return in_array($role, ['HR', 'Super Admin', 'CEO', 'CFA'], true);
    }

    private function generateDocNo(int $companyId, string $requestType, ?string $requestDate, ?int $ignoreId = null): string
    {
        $type = strtoupper(trim($requestType));
        if (!in_array($type, ['DLK', 'DLN'], true)) {
            $type = 'DLK';
        }

        $date = $requestDate;
        if (!$date) {
            $date = date('Y-m-d');
        }

        $ts = strtotime($date);
        if ($ts === false) {
            $ts = time();
        }

        $yy = date('y', $ts);
        $mm = date('m', $ts);
        $prefix = $type . '-' . $yy . ' ' . $mm;

        $query = DinasLuarRequest::where('company_id', $companyId)
            ->where('request_type', $type)
            ->where('doc_no', 'like', $prefix . '%');

        if ($ignoreId) {
            $query->where('id', '<>', $ignoreId);
        }

        $lastDocNo = (string) $query->orderByDesc('id')->value('doc_no');
        $nextNumber = 1;
        if ($lastDocNo !== '' && preg_match('/^' . preg_quote($prefix, '/') . '(\d{5})$/', $lastDocNo, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
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

    private function approvalStepsFor(int $companyId, int $requesterUserId): array
    {
        if (!Schema::hasTable('approval_settings')) {
            return [];
        }

        $steps = [];
        if (Schema::hasTable('approval_steps')) {
            $steps = ApprovalStep::where('company_id', $companyId)
                ->where('module_key', $this->moduleKey)
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
                ->where('module_key', $this->moduleKey)
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

        return $steps;
    }

    private function buildApprovalRequestSteps(int $requestId, int $companyId, int $requesterUserId): array
    {
        if (!Schema::hasTable('approval_request_steps')) {
            return [];
        }

        $steps = $this->approvalStepsFor($companyId, $requesterUserId);
        if (empty($steps)) {
            return [];
        }

        ApprovalRequestStep::where('module_key', $this->moduleKey)
            ->where('request_id', $requestId)
            ->delete();

        foreach (array_values($steps) as $i => $approverId) {
            ApprovalRequestStep::create([
                'module_key' => $this->moduleKey,
                'request_id' => $requestId,
                'step_no' => $i + 1,
                'approver_user_id' => (int) $approverId,
                'status' => 'Pending',
            ]);
        }

        return $steps;
    }

    private function getApprovalRequestSteps(int $requestId): array
    {
        if (!Schema::hasTable('approval_request_steps')) {
            return [];
        }
        return ApprovalRequestStep::where('module_key', $this->moduleKey)
            ->where('request_id', $requestId)
            ->orderBy('step_no')
            ->get()
            ->all();
    }

    private function ensureApprovalRequestSteps(int $requestId, int $companyId, int $requesterUserId): array
    {
        $steps = $this->getApprovalRequestSteps($requestId);
        if (empty($steps)) {
            $this->buildApprovalRequestSteps($requestId, $companyId, $requesterUserId);
            $steps = $this->getApprovalRequestSteps($requestId);
        }
        return $steps;
    }

    private function pendingApprovalStep(array $steps): ?ApprovalRequestStep
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
        if ($approverId <= 0) {
            return false;
        }
        return (int) ($user['id'] ?? 0) === $approverId;
    }

    private function buildTotals(int $requestId): array
    {
        $lumpsums = DinasLuarLumpsum::where('request_id', $requestId)->get();
        $facilities = DinasLuarFacility::where('request_id', $requestId)->get();
        $others = DinasLuarOther::where('request_id', $requestId)->get();

        $totalA = 0.0;
        foreach ($lumpsums as $row) {
            $totalA += (float) $row->total;
        }
        $totalB = 0.0;
        foreach ($facilities as $row) {
            $totalB += (float) $row->amount;
        }
        $totalC = 0.0;
        foreach ($others as $row) {
            $totalC += (float) $row->amount;
        }
        return [$lumpsums, $facilities, $others, $totalA, $totalB, $totalC];
    }

    public function index(Request $request)
    {
        $user = current_user();
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            return redirect()->route('dinas_luar.index');
        }
        $companyId = current_company_id();
        $companies = Company::orderBy('id')->get();
        $employees = Employee::where('company_id', $companyId)->orderBy('name')->get();

        $messages = [];
        $errors = [];
        if ($request->isMethod('post')) {
            $action = (string) $request->input('action', '');
            $requestId = (int) $request->input('id', 0);
            $row = $requestId > 0 ? DinasLuarRequest::where('company_id', $companyId)->where('id', $requestId)->first() : null;

            if ($action === 'delete') {
                if ($row) {
                    DinasLuarLumpsum::where('request_id', $row->id)->delete();
                    DinasLuarFacility::where('request_id', $row->id)->delete();
                    DinasLuarOther::where('request_id', $row->id)->delete();
                    $row->delete();
                    $messages[] = 'Pengajuan dinas luar berhasil dihapus.';
                }
                return redirect()->route('dinas_luar.index')->with('dinas_messages', $messages);
            }

            if ($action === 'submit') {
                if (!$row) {
                    return redirect()->route('dinas_luar.index');
                }
                $steps = $this->approvalStepsFor($companyId, (int) ($row->requester_user_id ?? 0));
                if (empty($steps)) {
                    $errors[] = 'Approval settings untuk Dinas Luar belum diatur.';
                    return back()->withErrors(['approval' => $errors[0]]);
                }
                $this->buildApprovalRequestSteps((int) $row->id, $companyId, (int) ($row->requester_user_id ?? 0));
                $row->status = 'Pending Approval 1';
                $row->save();
                $firstApprover = (int) ($steps[0] ?? 0);
                if ($firstApprover > 0) {
                    $this->pushNotification(
                        $companyId,
                        $firstApprover,
                        'Approval Dinas Luar (Step 1)',
                        'Pengajuan dinas luar menunggu approval Anda.',
                        route('dinas_luar.detail', ['id' => (int) $row->id])
                    );
                }
                $messages[] = 'Pengajuan approval dinas luar berhasil dikirim.';
                return redirect()->route('dinas_luar.index')->with('dinas_messages', $messages);
            }

            if ($action === 'approve_step' || $action === 'reject') {
                if (!$row) {
                    return redirect()->route('dinas_luar.index');
                }
                $data = $request->validate([
                    'note' => ['nullable','string','max:255'],
                ]);
                $stepsRows = $this->ensureApprovalRequestSteps((int) $row->id, $companyId, (int) ($row->requester_user_id ?? 0));
                $pending = $this->pendingApprovalStep($stepsRows);
                if (!$this->canApproveStep($pending, $user)) {
                    abort(403, 'Access denied.');
                }
                if ($action === 'approve_step') {
                    if ($pending) {
                        ApprovalRequestStep::where('id', (int) $pending->id)->update([
                            'status' => 'Approved',
                            'approved_by' => (int) ($user['id'] ?? 0),
                            'approved_at' => now(),
                            'signature' => 'Approved',
                        ]);
                    }
                    $stepsRows = $this->getApprovalRequestSteps((int) $row->id);
                    $next = $this->pendingApprovalStep($stepsRows);
                    if (!$next) {
                        $row->status = 'Approved';
                        $row->approved_by = (int) ($user['id'] ?? 0);
                        $row->approved_at = now();
                    } else {
                        $row->status = 'Pending Approval ' . (int) ($next->step_no ?? 1);
                    }
                    $row->save();

                    if ($next && (int) ($next->approver_user_id ?? 0) > 0) {
                        $this->pushNotification(
                            $companyId,
                            (int) $next->approver_user_id,
                            'Approval Dinas Luar (Step ' . (int) ($next->step_no ?? 1) . ')',
                            'Pengajuan dinas luar menunggu approval Anda.',
                            route('dinas_luar.detail', ['id' => (int) $row->id])
                        );
                    } else {
                        $requesterId = (int) ($row->requester_user_id ?? 0);
                        if ($requesterId > 0) {
                            $this->pushNotification(
                                $companyId,
                                $requesterId,
                                'Dinas Luar Disetujui',
                                'Pengajuan dinas luar Anda telah disetujui.',
                                route('dinas_luar.detail', ['id' => (int) $row->id])
                            );
                        }
                    }
                    $messages[] = 'Approval berhasil disetujui.';
                } else {
                    if ($pending) {
                        ApprovalRequestStep::where('id', (int) $pending->id)->update([
                            'status' => 'Rejected',
                        ]);
                    }
                    $row->status = 'Rejected';
                    $row->rejected_by = (int) ($user['id'] ?? 0);
                    $row->rejected_at = now();
                    $row->rejected_note = $data['note'] ?? null;
                    $row->save();
                    $requesterId = (int) ($row->requester_user_id ?? 0);
                    if ($requesterId > 0) {
                        $this->pushNotification(
                            $companyId,
                            $requesterId,
                            'Dinas Luar Ditolak',
                            'Pengajuan dinas luar Anda ditolak. ' . trim((string) ($data['note'] ?? '')),
                            route('dinas_luar.detail', ['id' => (int) $row->id])
                        );
                    }
                    $messages[] = 'Approval ditolak.';
                }

                return redirect()->route('dinas_luar.index')->with('dinas_messages', $messages);
            }
        }

        if (session()->has('dinas_messages')) {
            $messages = (array) session('dinas_messages');
        }

        $statusFilter = (string) $request->query('status', '');
        $typeFilter = (string) $request->query('type', '');
        $query = DinasLuarRequest::where('company_id', $companyId);
        if ($statusFilter !== '') {
            if ($statusFilter === 'pending') {
                $query->where('status', 'like', 'Pending%');
            } else {
                $query->where('status', $statusFilter);
            }
        }
        if ($typeFilter !== '') {
            $query->where('request_type', $typeFilter);
        }
        $rows = $query->orderByDesc('id')->get();

        $stepsMap = [];
        $pendingApproverId = [];
        $pendingStepNo = [];
        if ($rows->isNotEmpty() && Schema::hasTable('approval_request_steps')) {
            $stepRows = ApprovalRequestStep::where('module_key', $this->moduleKey)
                ->whereIn('request_id', $rows->pluck('id')->all())
                ->orderBy('step_no')
                ->get();
            foreach ($stepRows as $r) {
                $stepsMap[$r->request_id][] = $r;
                if (($r->status ?? '') === 'Pending' && !isset($pendingStepNo[$r->request_id])) {
                    $pendingStepNo[$r->request_id] = (int) $r->step_no;
                    $pendingApproverId[$r->request_id] = (int) ($r->approver_user_id ?? 0);
                }
            }
        }

        $userMap = User::where('company_id', $companyId)
            ->orWhereIn('role', ['CEO', 'CFA', 'HR1', 'HR2'])
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        return view('modules.dinas_luar.index', compact(
            'user',
            'companyId',
            'companies',
            'employees',
            'rows',
            'messages',
            'statusFilter',
            'typeFilter',
            'stepsMap',
            'pendingStepNo',
            'pendingApproverId',
            'userMap'
        ));
    }

    public function form(Request $request)
    {
        $user = current_user();
        $companyId = current_company_id();
        $companies = Company::orderBy('id')->get();
        $employees = Employee::where('company_id', $companyId)->orderBy('name')->get();

        $edit = null;
        $lumpsums = collect();
        $facilities = collect();
        $others = collect();
        $editId = (int) $request->query('id', 0);
        if ($editId > 0) {
            $edit = DinasLuarRequest::where('company_id', $companyId)->where('id', $editId)->first();
            if ($edit) {
                $lumpsums = DinasLuarLumpsum::where('request_id', $edit->id)->get();
                $facilities = DinasLuarFacility::where('request_id', $edit->id)->get();
                $others = DinasLuarOther::where('request_id', $edit->id)->get();
            }
        }

        if ($request->isMethod('post')) {
            $canManageAmounts = $this->canManageAmounts($user);
            $data = $request->validate([
                'id' => ['nullable','integer','min:1'],
                'request_type' => ['required','string','max:10'],
                'request_date' => ['nullable','date'],
                'work_start' => ['nullable','date'],
                'work_end' => ['nullable','date'],
                'extension_no' => ['nullable','integer','min:0'],
                'customer' => ['nullable','string','max:150'],
                'work_order_no' => ['nullable','string','max:80'],
                'project' => ['nullable','string','max:150'],
                'pekerjaan' => ['nullable','string','max:150'],
                'lokasi' => ['nullable','string','max:150'],
                'country' => ['nullable','string','max:100'],
                'city' => ['nullable','string','max:100'],
                'passport_no' => ['nullable','string','max:50'],
                'passport_expiry' => ['nullable','date'],
                'currency' => ['nullable','string','max:10'],
                'notes' => ['nullable','string'],
            ]);

            $payload = [
                'company_id' => $companyId,
                'requester_user_id' => (int) ($user['id'] ?? 0),
                'employee_id' => (int) ($user['employee_id'] ?? 0),
                'request_type' => strtoupper((string) $data['request_type']),
                'request_date' => $data['request_date'] ?? null,
                'work_start' => $data['work_start'] ?? null,
                'work_end' => $data['work_end'] ?? null,
                'extension_no' => (int) ($data['extension_no'] ?? 0),
                'customer' => $data['customer'] ?? null,
                'work_order_no' => $data['work_order_no'] ?? null,
                'project' => $data['project'] ?? null,
                'pekerjaan' => $data['pekerjaan'] ?? null,
                'lokasi' => $data['lokasi'] ?? null,
                'country' => $data['country'] ?? null,
                'city' => $data['city'] ?? null,
                'passport_no' => $data['passport_no'] ?? null,
                'passport_expiry' => $data['passport_expiry'] ?? null,
                'currency' => $data['currency'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];

            if (!empty($data['id'])) {
                $edit = DinasLuarRequest::where('company_id', $companyId)->where('id', (int) $data['id'])->first();
                if (!$edit) {
                    abort(404, 'Data tidak ditemukan.');
                }
                if (in_array($edit->status, ['Approved'], true) || str_starts_with((string) $edit->status, 'Pending')) {
                    return back()->withErrors(['status' => 'Data sudah diajukan/ disetujui dan tidak dapat diubah.'])->withInput();
                }
                $payload['doc_no'] = (string) ($edit->doc_no ?: $this->generateDocNo(
                    $companyId,
                    (string) $payload['request_type'],
                    $payload['request_date'],
                    (int) $edit->id
                ));
                $edit->fill($payload);
                $edit->save();
                $requestId = (int) $edit->id;
            } else {
                $payload['doc_no'] = $this->generateDocNo(
                    $companyId,
                    (string) $payload['request_type'],
                    $payload['request_date']
                );
                $payload['status'] = 'Draft';
                $edit = DinasLuarRequest::create($payload);
                $requestId = (int) $edit->id;
            }

            $existingLumpsums = DinasLuarLumpsum::where('request_id', $requestId)->get()->keyBy('id');
            $existingFacilities = DinasLuarFacility::where('request_id', $requestId)->get()->keyBy('id');
            $existingOthers = DinasLuarOther::where('request_id', $requestId)->get()->keyBy('id');

            DinasLuarLumpsum::where('request_id', $requestId)->delete();
            DinasLuarFacility::where('request_id', $requestId)->delete();
            DinasLuarOther::where('request_id', $requestId)->delete();

            $lumpsumIds = (array) $request->input('lumpsum_id', []);
            $names = (array) $request->input('lumpsum_name', []);
            $days = (array) $request->input('lumpsum_days', []);
            $amounts = (array) $request->input('lumpsum_amount', []);
            foreach ($names as $i => $name) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }
                $dayVal = (int) ($days[$i] ?? 0);
                $existingId = (int) ($lumpsumIds[$i] ?? 0);
                $existingRow = $existingId > 0 ? $existingLumpsums->get($existingId) : null;
                $amt = $canManageAmounts
                    ? (float) ($amounts[$i] ?? 0)
                    : (float) ($existingRow->amount ?? 0);
                $total = $dayVal * $amt;
                DinasLuarLumpsum::create([
                    'request_id' => $requestId,
                    'name' => $name,
                    'days' => $dayVal,
                    'amount' => $amt,
                    'total' => $total,
                ]);
            }

            $facilityIds = (array) $request->input('facility_id', []);
            $fNames = (array) $request->input('facility_name', []);
            $fFunded = (array) $request->input('facility_funded', []);
            $fAmount = (array) $request->input('facility_amount', []);
            foreach ($fNames as $i => $name) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }
                $existingId = (int) ($facilityIds[$i] ?? 0);
                $existingRow = $existingId > 0 ? $existingFacilities->get($existingId) : null;
                DinasLuarFacility::create([
                    'request_id' => $requestId,
                    'name' => $name,
                    'funded_by' => trim((string) ($fFunded[$i] ?? '')),
                    'amount' => $canManageAmounts
                        ? (float) ($fAmount[$i] ?? 0)
                        : (float) ($existingRow->amount ?? 0),
                ]);
            }

            $otherIds = (array) $request->input('other_id', []);
            $oNames = (array) $request->input('other_name', []);
            $oAmount = (array) $request->input('other_amount', []);
            foreach ($oNames as $i => $name) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }
                $existingId = (int) ($otherIds[$i] ?? 0);
                $existingRow = $existingId > 0 ? $existingOthers->get($existingId) : null;
                DinasLuarOther::create([
                    'request_id' => $requestId,
                    'name' => $name,
                    'amount' => $canManageAmounts
                        ? (float) ($oAmount[$i] ?? 0)
                        : (float) ($existingRow->amount ?? 0),
                ]);
            }

            return redirect()->route('dinas_luar.detail', ['id' => $requestId]);
        }

        $canManageAmounts = $this->canManageAmounts($user);

        return view('modules.dinas_luar.form', compact('user', 'companyId', 'companies', 'employees', 'edit', 'lumpsums', 'facilities', 'others', 'canManageAmounts'));
    }

    public function detail(int $id)
    {
        $user = current_user();
        $companyId = current_company_id();
        $row = DinasLuarRequest::where('company_id', $companyId)->where('id', $id)->first();
        if (!$row) {
            abort(404, 'Data tidak ditemukan.');
        }

        [$lumpsums, $facilities, $others, $totalA, $totalB, $totalC] = $this->buildTotals((int) $row->id);
        $grandTotal = $totalA + $totalB + $totalC;

        $steps = $this->getApprovalRequestSteps((int) $row->id);
        if (empty($steps) && !in_array($row->status, ['Approved', 'Rejected'], true)) {
            $steps = $this->ensureApprovalRequestSteps((int) $row->id, $companyId, (int) ($row->requester_user_id ?? 0));
        }
        $pendingStep = $this->pendingApprovalStep($steps);
        $canApprove = $this->canApproveStep($pendingStep, $user);
        $pendingStepNo = $pendingStep ? (int) ($pendingStep->step_no ?? 0) : null;

        $userMap = User::where('company_id', $companyId)
            ->orWhereIn('role', ['CEO', 'CFA', 'HR1', 'HR2'])
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        return view('modules.dinas_luar.detail', compact(
            'user',
            'row',
            'lumpsums',
            'facilities',
            'others',
            'totalA',
            'totalB',
            'totalC',
            'grandTotal',
            'steps',
            'pendingStepNo',
            'canApprove',
            'userMap'
        ));
    }

    public function pdf(int $id)
    {
        $companyId = current_company_id();
        $row = DinasLuarRequest::where('company_id', $companyId)->where('id', $id)->first();
        if (!$row) {
            abort(404, 'Data tidak ditemukan.');
        }
        $company = Company::find($companyId);
        [$lumpsums, $facilities, $others, $totalA, $totalB, $totalC] = $this->buildTotals((int) $row->id);
        $grandTotal = $totalA + $totalB + $totalC;

        $steps = $this->getApprovalRequestSteps((int) $row->id);
        $userMap = User::where('company_id', $companyId)
            ->orWhereIn('role', ['CEO', 'CFA', 'HR1', 'HR2'])
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $view = $row->request_type === 'DLN'
            ? resource_path('views/modules/dinas_luar/dln_pdf.blade.php')
            : resource_path('views/modules/dinas_luar/dlk_pdf.blade.php');

        $logoDataUri = '';
        if (!empty($company?->logo_path)) {
            $rawLogoPath = str_replace('\\', '/', trim((string) $company->logo_path));
            $logoCandidates = [];
            if (preg_match('#^([a-zA-Z]:/|/)#', $rawLogoPath) === 1) {
                $logoCandidates[] = $rawLogoPath;
            } else {
                $logoCandidates[] = public_path($rawLogoPath);
                $logoCandidates[] = base_path($rawLogoPath);
            }
            foreach ($logoCandidates as $candidate) {
                if (!is_file($candidate)) {
                    continue;
                }
                $logoContent = file_get_contents($candidate);
                if ($logoContent !== false) {
                    $mime = 'image/png';
                    $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode($logoContent);
                }
                break;
            }
        }

        ob_start();
        include $view;
        $html = ob_get_clean();

        $dompdf = new Dompdf([
            'defaultFont' => 'DejaVu Sans',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'chroot' => base_path(),
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return response($dompdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="dinas_luar.pdf"');
    }
}
