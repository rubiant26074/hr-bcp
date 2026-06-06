<?php

namespace App\Http\Controllers;

use App\Models\ApprovalSetting;
use App\Models\ApprovalStep;
use App\Models\Company;
use App\Models\Employee;
use App\Models\OrgStructure;
use App\Models\RoleDefinition;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ApprovalSettingsController extends Controller
{
    private function autoApproversFromOrg(int $companyId, int $requesterUserId, int $maxSteps = 5): array
    {
        $requester = User::find($requesterUserId);
        if (!$requester || empty($requester->employee_id)) {
            return [];
        }
        $employee = Employee::where('company_id', $companyId)->where('id', (int) $requester->employee_id)->first();
        if (!$employee) {
            return [];
        }

        $units = OrgStructure::where('company_id', $companyId)->orderBy('id')->get();
        if ($units->isEmpty()) {
            return [];
        }
        $unitById = $units->keyBy('id');

        $dept = trim((string) ($employee->department ?? ''));
        $pos = trim((string) ($employee->position ?? ''));
        $matched = null;
        if ($dept !== '') {
            foreach ($units as $u) {
                if (strcasecmp($u->name, $dept) === 0) {
                    $matched = $u;
                    break;
                }
            }
        }
        if (!$matched && $pos !== '') {
            foreach ($units as $u) {
                if (strcasecmp($u->name, $pos) === 0) {
                    $matched = $u;
                    break;
                }
            }
        }
        if (!$matched) {
            return [];
        }

        $approvers = [];

        $parent = null;
        if (!empty($matched->parent_id)) {
            $parent = $unitById[(int) $matched->parent_id] ?? null;
        }

        if ($parent) {
            $candidate = User::query()
                ->leftJoin('employees as e', 'e.id', '=', 'users.employee_id')
                ->where('users.company_id', $companyId)
                ->where(function ($q) use ($parent) {
                    $q->where('e.department', $parent->name)
                      ->orWhere('e.position', $parent->name);
                })
                ->orderBy('users.id')
                ->select('users.id')
                ->first();
            if ($candidate) {
                $candidateId = (int) $candidate->id;
                if ($candidateId !== $requesterUserId) {
                    $approvers[] = $candidateId;
                }
            }
        }

        $hrUser = User::query()
            ->where('company_id', $companyId)
            ->where('role', 'HR')
            ->orderBy('id')
            ->select('id')
            ->first();
        if ($hrUser) {
            $hrId = (int) $hrUser->id;
            if ($hrId !== $requesterUserId && !in_array($hrId, $approvers, true)) {
                $approvers[] = $hrId;
            }
        }

        return array_slice($approvers, 0, $maxSteps);
    }

    public function index(Request $request)
    {
        $user = current_user();
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            return redirect()->route('settings.approval');
        }

        $companyId = current_company_id();
        $companies = Company::orderBy('id')->get();
        $roles = Schema::hasTable('role_definitions')
            ? RoleDefinition::orderBy('id')->pluck('name')->all()
            : ['Super Admin', 'CEO', 'CFA', 'HR', 'HR1', 'HR2', 'Finance', 'Employee'];

        $users = User::where('company_id', $companyId)
            ->orWhereIn('role', ['CEO', 'CFA', 'HR1', 'HR2'])
            ->orderBy('id')
            ->get();
        $defaultRequesterId = (int) ($users->first()->id ?? 0);
        $moduleKey = (string) $request->query('module_key', 'absence');
        $requesterUserId = (int) $request->query('requester_user_id', $defaultRequesterId);
        $editId = (int) $request->query('edit', 0);
        $edit = null;
        if ($editId > 0) {
            $edit = ApprovalSetting::where('company_id', $companyId)
                ->where('id', $editId)
                ->first();
            if ($edit) {
                $moduleKey = (string) $edit->module_key;
                $requesterUserId = (int) $edit->requester_user_id;
            }
        }

        $setting = ApprovalSetting::where('company_id', $companyId)
            ->where('module_key', $moduleKey)
            ->where('requester_user_id', $requesterUserId)
            ->first();

        $steps = ApprovalStep::where('company_id', $companyId)
            ->where('module_key', $moduleKey)
            ->where('requester_user_id', $requesterUserId)
            ->orderBy('step_no')
            ->get();
        if ($steps->isEmpty() && $setting) {
            $legacySteps = [];
            if (!empty($setting->approver1_user_id)) {
                $legacySteps[] = (int) $setting->approver1_user_id;
            }
            if (!empty($setting->approver2_user_id)) {
                $legacySteps[] = (int) $setting->approver2_user_id;
            }
            foreach ($legacySteps as $idx => $uid) {
                $steps->push((object) ['step_no' => $idx + 1, 'approver_user_id' => $uid]);
            }
        }

        $filterModuleKey = (string) $request->query('filter_module', 'all');
        $settingsQuery = ApprovalSetting::where('company_id', $companyId);
        if ($filterModuleKey === 'absence' || $filterModuleKey === 'out_office' || $filterModuleKey === 'overtime' || $filterModuleKey === 'payroll_report' || $filterModuleKey === 'payroll_pph21' || $filterModuleKey === 'dinas_luar') {
            $settingsQuery->where('module_key', $filterModuleKey);
        }
        $settingsList = $settingsQuery
            ->orderBy('module_key')
            ->orderBy('requester_user_id')
            ->get();
        $userMap = $users->keyBy('id');
        $stepsListMap = [];
        if (Schema::hasTable('approval_steps')) {
            $stepRows = ApprovalStep::where('company_id', $companyId)
                ->orderBy('module_key')
                ->orderBy('requester_user_id')
                ->orderBy('step_no')
                ->get();
            foreach ($stepRows as $r) {
                $key = $r->module_key . '|' . $r->requester_user_id;
                $stepsListMap[$key][] = (int) $r->approver_user_id;
            }
        }

        if ($request->isMethod('post')) {
            $action = (string) $request->input('action', 'save');

            if ($action === 'delete') {
                $data = $request->validate([
                    'id' => ['required','integer','min:1'],
                ]);
                $row = ApprovalSetting::where('company_id', $companyId)
                    ->where('id', (int) $data['id'])
                    ->first();
                if ($row) {
                    $row->delete();
                }
                return redirect()->route('settings.approval', [
                    'module_key' => $moduleKey,
                    'requester_user_id' => $requesterUserId,
                ]);
            }

            $autoFromOrg = $request->boolean('auto_from_org');
            $data = $request->validate([
                'id' => ['nullable','integer','min:1'],
                'module_key' => ['required','string','max:50'],
                'requester_user_id' => ['required','integer','min:1'],
                'approvers' => [$autoFromOrg ? 'nullable' : 'required','array','min:1','max:5'],
                'approvers.*' => [$autoFromOrg ? 'nullable' : 'required','integer','min:1'],
            ]);

            $moduleKey = (string) $data['module_key'];
            $requesterUserId = (int) $data['requester_user_id'];
            if ($autoFromOrg) {
                $autoApprovers = $this->autoApproversFromOrg($companyId, $requesterUserId, 5);
                if (empty($autoApprovers)) {
                    return back()->withErrors([
                        'auto_from_org' => 'Approval otomatis gagal. Pastikan user memiliki karyawan dengan Departemen/Jabatan yang terhubung ke Struktur Organisasi dan parent memiliki user.',
                    ])->withInput();
                }
                $data['approvers'] = $autoApprovers;
            }

            if ($action === 'update' && !empty($data['id'])) {
                $setting = ApprovalSetting::where('company_id', $companyId)
                    ->where('id', (int) $data['id'])
                    ->first();
                if (!$setting) {
                    return redirect()->route('settings.approval', ['module_key' => $moduleKey]);
                }
                $setting->module_key = $moduleKey;
                $setting->requester_user_id = $requesterUserId;
            } else {
                $setting = ApprovalSetting::firstOrNew([
                    'company_id' => $companyId,
                    'module_key' => $moduleKey,
                    'requester_user_id' => $requesterUserId,
                ]);
            }
            $setting->approver1_user_id = (int) ($data['approvers'][0] ?? 0);
            $setting->approver2_user_id = (int) ($data['approvers'][1] ?? 0);
            $setting->save();

            ApprovalStep::where('company_id', $companyId)
                ->where('module_key', $moduleKey)
                ->where('requester_user_id', $requesterUserId)
                ->delete();
            foreach (array_values($data['approvers']) as $i => $approverId) {
                ApprovalStep::create([
                    'company_id' => $companyId,
                    'module_key' => $moduleKey,
                    'requester_user_id' => $requesterUserId,
                    'step_no' => $i + 1,
                    'approver_user_id' => (int) $approverId,
                ]);
            }

            return redirect()->route('settings.approval', [
                'saved' => 1,
                'module_key' => $moduleKey,
                'requester_user_id' => $requesterUserId,
            ]);
        }

        return view('modules.settings.approval', compact('user', 'companyId', 'companies', 'roles', 'users', 'setting', 'steps', 'moduleKey', 'requesterUserId', 'settingsList', 'userMap', 'edit', 'filterModuleKey', 'stepsListMap'));
    }
}

