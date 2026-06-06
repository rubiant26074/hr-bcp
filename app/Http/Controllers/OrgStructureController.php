<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\OrgStructure;
use App\Models\EmployeeDepartment;
use App\Models\EmployeePosition;
use Illuminate\Http\Request;

class OrgStructureController extends Controller
{
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
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            return redirect()->route('org_structure.index');
        }

        $companyId = current_company_id();
        $companies = Company::orderBy('id')->get();
        $departments = $this->listGlobalOrCompany(EmployeeDepartment::query(), $companyId, 'id');
        $positions = $this->listGlobalOrCompany(EmployeePosition::query(), $companyId, 'id');
        $edit = null;

        if ($request->has('edit')) {
            $editId = (int) $request->query('edit');
            if ($editId > 0) {
                $edit = OrgStructure::where('company_id', $companyId)->where('id', $editId)->first();
            }
        }

        if ($request->isMethod('post')) {
            $action = (string) $request->input('action', 'save');
            if ($action === 'delete') {
                $id = (int) $request->input('id', 0);
                if ($id > 0) {
                    OrgStructure::where('company_id', $companyId)->where('id', $id)->delete();
                }
                return redirect()->route('org_structure.index');
            }

            $data = $request->validate([
                'id' => ['nullable','integer','min:1'],
                'name' => ['required','string','max:120'],
                'parent_id' => ['nullable','integer','min:1'],
                'note' => ['nullable','string','max:255'],
                'sort_order' => ['nullable','integer','min:0','max:9999'],
            ]);

            $parentId = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;
            if (!empty($data['id']) && $parentId && (int) $data['id'] === $parentId) {
                return back()->withErrors(['parent_id' => 'Parent tidak boleh sama dengan item.'])->withInput();
            }

            if (!empty($data['id'])) {
                OrgStructure::where('company_id', $companyId)->where('id', (int) $data['id'])->update([
                    'name' => $data['name'],
                    'parent_id' => $parentId,
                    'note' => $data['note'] ?? null,
                    'sort_order' => (int) ($data['sort_order'] ?? 0),
                ]);
            } else {
                OrgStructure::create([
                    'company_id' => $companyId,
                    'name' => $data['name'],
                    'parent_id' => $parentId,
                    'note' => $data['note'] ?? null,
                    'sort_order' => (int) ($data['sort_order'] ?? 0),
                ]);
            }
            return redirect()->route('org_structure.index');
        }

        $rows = OrgStructure::where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $parentMap = $rows->keyBy('id');

        return view('modules.org_structure.index', compact('user', 'companyId', 'companies', 'departments', 'positions', 'rows', 'parentMap', 'edit'));
    }
}

