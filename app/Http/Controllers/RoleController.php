<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::withCount(['users', 'permissions'])->orderBy('name')->get();

        return view('roles.index', ['roles' => $roles]);
    }

    public function create(): View
    {
        return view('roles.create', [
            'role' => new Role,
            'permissionGroups' => $this->permissionGroups(),
            'rolePermissionIds' => [],
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::create($request->safe()->except('permissions'));
        $role->syncPermissionsWithAudit($request->input('permissions', []));

        return Redirect::route('roles.index')->with('status', 'Role berhasil ditambahkan.');
    }

    public function edit(Role $role): View
    {
        return view('roles.edit', [
            'role' => $role,
            'permissionGroups' => $this->permissionGroups(),
            'rolePermissionIds' => $role->permissions()->pluck('permissions.id')->all(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $role->update($request->safe()->except('permissions'));
        $role->syncPermissionsWithAudit($request->input('permissions', []));

        return Redirect::route('roles.index')->with('status', 'Role berhasil diperbarui.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_super_admin) {
            return Redirect::route('roles.index')->with('error', 'Role super admin tidak dapat dihapus.');
        }

        if ($role->users()->exists()) {
            return Redirect::route('roles.index')->with('error', 'Role masih dipakai oleh user, pindahkan usernya dulu.');
        }

        $role->delete();

        return Redirect::route('roles.index')->with('status', 'Role berhasil dihapus.');
    }

    /**
     * Permissions grouped by feature, in the shape the switch-grid form partial expects.
     *
     * @return Collection<string, array{label: string, permissions: Collection<int, Permission>}>
     */
    private function permissionGroups()
    {
        return Permission::orderBy('feature')->orderBy('action')->get()
            ->groupBy('feature')
            ->map(fn ($permissions, $feature) => [
                'label' => $permissions->first()->feature_label,
                'permissions' => $permissions,
            ]);
    }
}
