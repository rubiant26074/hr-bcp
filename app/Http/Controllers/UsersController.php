<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Employee;
use App\Models\RoleDefinition;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        if ($request->isMethod('post') && $request->input('action') === 'delete') {
            $deleteId = (int) $request->input('id', 0);
            $current = current_user();
            if ($deleteId > 0 && (int)($current['id'] ?? 0) !== $deleteId) {
                User::whereKey($deleteId)->delete();
                return redirect()->route('users.index', ['deleted' => 1]);
            }
            return redirect()->route('users.index', ['error' => 'self_delete']);
        }
        if ($request->isMethod('post') && $request->input('action') === 'toggle_active') {
            $targetId = (int) $request->input('id', 0);
            $active = (int) $request->input('active', 0) === 1 ? 1 : 0;
            $current = current_user();
            if ($targetId > 0 && (int) ($current['id'] ?? 0) !== $targetId) {
                $target = User::find($targetId);
                if ($target) {
                    $target->is_active = $active;
                    $target->save();
                    return redirect()->route('users.index', ['updated' => 1]);
                }
            }
            return redirect()->route('users.index');
        }

        $users = User::query()
            ->leftJoin('companies as c', 'c.id', '=', 'users.company_id')
            ->leftJoin('employees as e', 'e.id', '=', 'users.employee_id')
            ->orderByDesc('users.id')
            ->select('users.*', 'c.company_name', 'e.name as employee_name', 'e.nik as employee_nik', 'e.department as employee_department')
            ->get();
        return view('modules.users.index', compact('users'));
    }

    public function form(Request $request)
    {
        $id = (int) ($request->query('id') ?? $request->input('id') ?? 0);
        $edit = $id ? User::find($id) : null;
        if ($id && !$edit) {
            abort(404, 'User not found');
        }

        if ($request->isMethod('post')) {
            $allowedRoles = RoleDefinition::orderBy('id')->pluck('name')->all();
            $data = $request->validate([
                'name' => ['required','string','max:255'],
                'email' => ['required','email', Rule::unique('users','email')->ignore($id)],
                'password' => [$id ? 'nullable' : 'required','string','min:8'],
                'role' => ['required', Rule::in($allowedRoles)],
                'company_id' => ['nullable','integer'],
                'employee_id' => ['nullable','integer'],
                'is_active' => ['nullable', 'integer', Rule::in([0, 1])],
                'signature_file' => ['nullable','file','max:5120'],
            ]);

            $employee = null;
            if (!is_global_role((string) $data['role']) && ((int)($data['company_id'] ?? 0) <= 0)) {
                return back()->withErrors(['company_id' => 'Company wajib dipilih untuk role non-global.'])->withInput();
            }

            if ($data['role'] === 'Employee') {
                $employeeId = (int) ($data['employee_id'] ?? 0);
                if ($employeeId <= 0) {
                    return back()->withErrors(['employee_id' => 'Employee wajib dipilih untuk role Employee.'])->withInput();
                }
                $employee = Employee::find($employeeId);
                if (!$employee) {
                    return back()->withErrors(['employee_id' => 'Data employee tidak ditemukan.'])->withInput();
                }
                if ((int)$employee->company_id !== (int)($data['company_id'] ?? 0)) {
                    return back()->withErrors(['employee_id' => 'Employee harus berasal dari company yang dipilih.'])->withInput();
                }
                $existsEmployee = User::where('employee_id', $employeeId)->where('id', '<>', $id)->exists();
                if ($existsEmployee) {
                    return back()->withErrors(['employee_id' => 'Employee ini sudah terhubung ke user lain.'])->withInput();
                }
            }

            $finalCompanyId = is_global_role((string) $data['role']) ? null : (int) ($data['company_id'] ?? 0);
            $finalEmployeeId = null;
            if ($data['role'] === 'Employee' && $employee) {
                $finalEmployeeId = (int) $employee->id;
                $finalCompanyId = (int) $employee->company_id;
            }
            $payload = [
                'company_id' => $finalCompanyId,
                'employee_id' => $finalEmployeeId,
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
            ];
            if (array_key_exists('is_active', $data)) {
                $payload['is_active'] = (int) $data['is_active'] === 1 ? 1 : 0;
            }
            if (!empty($data['password'])) {
                $payload['password_hash'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
            }
            if ($request->hasFile('signature_file')) {
                $file = $request->file('signature_file');
                if ($file->isValid()) {
                    $ext = strtolower($file->getClientOriginalExtension());
                    $allowed = ['jpg','jpeg','png'];
                    $mime = $file->getMimeType();
                    $allowedMime = ['image/jpeg','image/png'];
                    if (!in_array($ext, $allowed, true) || !in_array($mime, $allowedMime, true)) {
                        return back()->withErrors(['signature_file' => 'Tanda tangan harus JPG/PNG.'])->withInput();
                    }
                    $dir = public_path('uploads/signatures');
                    if (!File::exists($dir)) {
                        File::makeDirectory($dir, 0755, true);
                    }
                    $filename = 'signature_' . uniqid() . '.' . $ext;
                    $file->move($dir, $filename);
                    $payload['signature_path'] = 'uploads/signatures/' . $filename;
                }
            }

            if ($id) {
                $edit->fill($payload);
                $edit->save();
                return redirect()->route('users.index', ['updated' => 1]);
            }
            User::create($payload);
            return redirect()->route('users.index', ['created' => 1]);
        }

        $companies = Company::orderBy('id')->get();
        $employees = Employee::orderBy('id')->get(['id', 'company_id', 'nik', 'name', 'department']);
        $roles = RoleDefinition::orderBy('id')->get();
        return view('modules.users.form', [
            'messages' => [],
            'edit' => $edit,
            'companies' => $companies,
            'employees' => $employees,
            'roles' => $roles,
        ]);
    }
}
