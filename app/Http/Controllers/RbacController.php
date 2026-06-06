<?php

namespace App\Http\Controllers;

use App\Support\Rbac;
use App\Models\RoleDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class RbacController extends Controller
{
    public function index(Request $request)
    {
        if ($request->isMethod('post')) {
            $role = (string) $request->input('role', '');
            if ($role !== '' && $role !== 'Super Admin' && in_array($role, Rbac::roles(), true)) {
                $allowed = $request->input("allowed.$role", []);
                Rbac::saveRolePermissions($role, $allowed);
            }
            return redirect()->route('rbac.index', ['saved' => 1, 'role' => $role]);
        }

        if (Schema::hasTable('role_definitions')) {
            $roles = RoleDefinition::orderBy('id')->pluck('name')->all();
        } else {
            $roles = Rbac::roles();
        }
        if (!in_array('Super Admin', $roles, true)) {
            array_unshift($roles, 'Super Admin');
        }
        $permissions = Rbac::allPermissions();
        $matrix = Rbac::matrixByRole();

        $defaultRole = $request->query('role', $roles[0] ?? 'Super Admin');
        return view('modules.rbac.index', compact('roles', 'permissions', 'matrix', 'defaultRole'));
    }
}
