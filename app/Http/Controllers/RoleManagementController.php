<?php

namespace App\Http\Controllers;

use App\Models\RoleDefinition;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class RoleManagementController extends Controller
{
    public function index(Request $request)
    {
        $edit = null;
        $messages = [];

        if ($request->isMethod('post')) {
            $action = $request->input('action', 'save');
            $id = (int) $request->input('id', 0);

            if ($action === 'delete') {
                if ($id > 0) {
                    $role = RoleDefinition::find($id);
                    if ($role) {
                        $inUse = DB::table('users')->where('role', $role->name)->exists();
                        if ($inUse) {
                            return redirect()->route('settings.roles', ['error' => 'in_use']);
                        }
                        $role->delete();
                    }
                }
                return redirect()->route('settings.roles', ['deleted' => 1]);
            }

            $data = $request->validate([
                'name' => ['required', 'string', 'max:50', Rule::unique('role_definitions', 'name')->ignore($id)],
                'description' => ['nullable', 'string', 'max:255'],
            ]);

            if ($id > 0) {
                $edit = RoleDefinition::find($id);
                if (!$edit) {
                    abort(404, 'Role not found');
                }
                $edit->fill($data);
                $edit->save();
                return redirect()->route('settings.roles', ['updated' => 1]);
            }

            RoleDefinition::create($data);
            return redirect()->route('settings.roles', ['created' => 1]);
        }

        if ($request->query('edit')) {
            $edit = RoleDefinition::find((int) $request->query('edit'));
        }

        $roles = RoleDefinition::orderBy('id')->get();
        return view('modules.settings.roles', compact('roles', 'edit', 'messages'));
    }
}
