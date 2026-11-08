<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('roles.index', ['roles' => Role::withCount('users')->orderByDesc('is_system')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('roles.form', ['role' => new Role, 'permissions' => Role::PERMISSIONS]);
    }

    public function store(Request $request): RedirectResponse
    {
        Role::create($this->validated($request));

        return redirect()->route('roles.index')->with('success', 'Role created.');
    }

    public function edit(Role $role): View
    {
        abort_if($role->is_system, 403, 'The Administrator role is protected.');

        return view('roles.form', ['role' => $role, 'permissions' => Role::PERMISSIONS]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->is_system, 403, 'The Administrator role is protected.');
        abort_if($role->id === $request->user()->role_id, 403, 'Ask another administrator to change your own role permissions.');
        $role->update($this->validated($request, $role));

        return redirect()->route('roles.index')->with('success', 'Role permissions updated.');
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles')->ignore($role?->id)],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(array_keys(Role::PERMISSIONS))],
        ]);
        $data['permissions'] = Role::normalizePermissions($data['permissions'] ?? []);

        return $data;
    }
}
