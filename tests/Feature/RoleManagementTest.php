<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        return User::factory()->withRoles('administrator')->create();
    }

    public function test_the_create_and_edit_forms_render_with_the_permission_switch_grid(): void
    {
        $role = Role::where('slug', 'dosen_pa')->firstOrFail();
        $admin = $this->administrator();

        $permission = Permission::where('name', 'tambah.user')->firstOrFail();

        $this->actingAs($admin)->get(route('roles.create'))
            ->assertOk()
            ->assertSee('Hak Akses')
            ->assertSee('Manajemen User')
            ->assertSee('Tambah')
            ->assertSee("value=\"{$permission->id}\"", false);

        $this->actingAs($admin)->get(route('roles.edit', $role))
            ->assertOk()
            ->assertSee($role->name);
    }

    public function test_administrator_can_create_a_role_with_selected_permissions(): void
    {
        $lihatUser = Permission::where('name', 'lihat.user')->firstOrFail();
        $editUser = Permission::where('name', 'edit.user')->firstOrFail();

        $response = $this->actingAs($this->administrator())->post(route('roles.store'), [
            'name' => 'Staf TU',
            'slug' => 'staf_tu',
            'description' => 'Tata usaha',
            'menu_group' => 'prodi',
            'permissions' => [$lihatUser->id, $editUser->id],
        ]);

        $response->assertRedirect(route('roles.index'));

        $role = Role::where('slug', 'staf_tu')->firstOrFail();
        $this->assertSame(['edit.user', 'lihat.user'], $role->permissions()->pluck('name')->sort()->values()->all());
        $this->assertFalse($role->is_super_admin);
    }

    /**
     * Regression: the slug field (and every permission switch) used to be
     * HTML `disabled` when editing the super-admin role, which browsers
     * simply omit from the submitted form — causing "The slug field is
     * required" and silently wiping the role's stored permissions. Fields
     * are no longer disabled; this posts a full real-browser-shaped payload.
     */
    public function test_the_super_admin_role_can_be_edited_and_keeps_full_access(): void
    {
        $role = Role::where('slug', 'administrator')->firstOrFail();
        $lihatUser = Permission::where('name', 'lihat.user')->firstOrFail();

        $this->actingAs($this->administrator())->put(route('roles.update', $role), [
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => 'Diperbarui',
            'menu_group' => $role->menu_group,
            'permissions' => [$lihatUser->id],
        ])->assertRedirect(route('roles.index'));

        $role->refresh();
        $this->assertTrue($role->is_super_admin);
        $this->assertTrue($role->hasPermission('hapus.role'), 'super admin must still bypass every permission check');
    }

    public function test_administrator_can_change_which_permissions_a_role_has(): void
    {
        $role = Role::where('slug', 'dosen_pa')->firstOrFail();
        $lihatRole = Permission::where('name', 'lihat.role')->firstOrFail();

        $this->actingAs($this->administrator())->put(route('roles.update', $role), [
            'name' => $role->name,
            'slug' => $role->slug,
            'menu_group' => $role->menu_group,
            'permissions' => [$lihatRole->id],
        ])->assertRedirect(route('roles.index'));

        $this->assertSame(['lihat.role'], $role->fresh()->permissions()->pluck('name')->all());
    }

    public function test_super_admin_role_cannot_be_deleted(): void
    {
        $role = Role::where('slug', 'administrator')->firstOrFail();

        $this->actingAs($this->administrator())->delete(route('roles.destroy', $role))
            ->assertRedirect(route('roles.index'));

        $this->assertModelExists($role);
    }

    public function test_a_role_still_assigned_to_users_cannot_be_deleted(): void
    {
        $role = Role::where('slug', 'mahasiswa')->firstOrFail();
        User::factory()->withRoles($role)->create();

        $this->actingAs($this->administrator())->delete(route('roles.destroy', $role))
            ->assertRedirect(route('roles.index'));

        $this->assertModelExists($role);
    }

    public function test_an_unused_role_can_be_deleted(): void
    {
        $role = Role::create(['name' => 'Sementara', 'slug' => 'sementara']);

        $this->actingAs($this->administrator())->delete(route('roles.destroy', $role))
            ->assertRedirect(route('roles.index'));

        $this->assertModelMissing($role);
    }

    public function test_a_role_without_tambah_role_permission_cannot_create_roles(): void
    {
        $mahasiswa = User::factory()->withRoles('mahasiswa')->create();

        $this->actingAs($mahasiswa)->post(route('roles.store'), [
            'name' => 'Hacked', 'slug' => 'hacked',
        ])->assertForbidden();

        $this->assertDatabaseMissing('roles', ['slug' => 'hacked']);
    }
}
