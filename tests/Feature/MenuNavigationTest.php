<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MenuNavigationTest extends TestCase
{
    use RefreshDatabase;

    public static function roleProvider(): array
    {
        return [
            'mahasiswa' => ['mahasiswa'],
            'dosen pa' => ['dosen_pa'],
            'dosen pembimbing' => ['dosen_pembimbing'],
            'dosen penguji' => ['dosen_penguji'],
            'prodi (admin_prodi)' => ['admin_prodi'],
            'prodi (kaprodi)' => ['kaprodi'],
            'prodi (administrator)' => ['administrator'],
        ];
    }

    /**
     * Every item the role's menu_group exposes should be reachable — except
     * items gated by a permission the role's default seed doesn't grant
     * (only 'administrator' is a super admin and bypasses every gate).
     */
    #[DataProvider('roleProvider')]
    public function test_every_permitted_menu_item_for_the_role_is_reachable(string $roleSlug): void
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $user = User::factory()->withRoles($role)->create();
        $group = $user->menuGroups()[0] ?? null;

        $this->assertNotNull($group, "menuGroups() should map role [{$roleSlug}] to a menu group.");

        $items = collect(config('menu.'.$group.'.sections'))->flatMap(fn (array $section) => $section['items']);

        $this->assertNotEmpty($items);

        foreach ($items as $item) {
            if (isset($item['permission']) && ! $user->hasPermission($item['permission'])) {
                continue;
            }

            $this->actingAs($user)
                ->get(route($item['route']))
                ->assertOk();
        }
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function defaultMenuProvider(): array
    {
        return [
            'mahasiswa' => ['mahasiswa', 'mahasiswa.supervision', 'dosen.supervision'],
            'dosen pa' => ['dosen_pa', 'dosen.advisor-titles.index', 'dosen.supervisees'],
            'dosen pembimbing' => ['dosen_pembimbing', 'dosen.supervisees', 'dosen.examinees'],
            'dosen penguji' => ['dosen_penguji', 'dosen.examinees', 'dosen.advisor-titles.index'],
            'admin prodi' => ['admin_prodi', 'prodi.settings', 'prodi.monitoring'],
            'kaprodi' => ['kaprodi', 'prodi.monitoring', 'prodi.settings'],
        ];
    }

    #[DataProvider('defaultMenuProvider')]
    public function test_each_role_sees_only_its_own_default_menu(string $roleSlug, string $visibleRoute, string $hiddenRoute): void
    {
        $user = User::factory()->withRoles($roleSlug)->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route($visibleRoute))
            ->assertDontSee(route($hiddenRoute));
    }

    public function test_revoking_a_permission_hides_the_menu_and_blocks_its_route(): void
    {
        $role = Role::where('slug', 'kaprodi')->firstOrFail();
        $role->permissions()->detach(Permission::where('name', 'lihat.monitoring')->value('id'));
        $user = User::factory()->withRoles($role)->create();

        $this->actingAs($user)->get(route('dashboard'))->assertDontSee(route('prodi.monitoring'));
        $this->actingAs($user)->get(route('prodi.monitoring'))->assertForbidden();
    }

    public function test_granting_a_permission_shows_the_menu_and_opens_its_route(): void
    {
        $role = Role::where('slug', 'admin_prodi')->firstOrFail();
        $role->permissions()->attach(Permission::where('name', 'lihat.monitoring')->value('id'));
        $user = User::factory()->withRoles($role)->create();

        $this->actingAs($user)->get(route('dashboard'))->assertSee(route('prodi.monitoring'));
        $this->actingAs($user)->get(route('prodi.monitoring'))->assertOk();
    }

    public function test_the_advisor_menu_needs_the_dosen_pa_role(): void
    {
        $user = User::factory()->withRoles('kaprodi')->create();

        $this->actingAs($user)->get(route('dashboard'))->assertDontSee(route('dosen.advisor-titles.index'));
    }

    public function test_a_user_with_several_roles_gets_the_combined_menu_and_access(): void
    {
        $user = User::factory()->withRoles('kaprodi', 'dosen_pembimbing')->create();

        $response = $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('prodi.monitoring'))
            ->assertSee(route('dosen.supervisees'))
            ->assertDontSee(route('dosen.examinees'));

        $this->assertSame(1, substr_count($response->getContent(), 'href="'.route('dashboard').'" class="nav-item'));
        $this->actingAs($user)->get(route('prodi.monitoring'))->assertOk();
        $this->actingAs($user)->get(route('dosen.supervisees'))->assertOk();
        $this->actingAs($user)->get(route('dosen.examinees'))->assertForbidden();
    }

    public function test_two_roles_of_the_same_menu_group_combine_their_items(): void
    {
        $user = User::factory()->withRoles('dosen_pa', 'dosen_penguji')->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertSee(route('dosen.advisor-titles.index'))
            ->assertSee(route('dosen.examinees'))
            ->assertDontSee(route('dosen.supervisees'));
    }

    public function test_a_role_without_the_permission_is_blocked_from_the_gated_route(): void
    {
        $role = Role::where('slug', 'admin_prodi')->firstOrFail();
        $user = User::factory()->withRoles($role)->create();

        $this->assertFalse($user->hasPermission('lihat.user'));

        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
    }

    public function test_super_admin_can_reach_the_access_control_pages(): void
    {
        $role = Role::where('slug', 'administrator')->firstOrFail();
        $user = User::factory()->withRoles($role)->create();

        $this->actingAs($user)->get(route('users.index'))->assertOk();
        $this->actingAs($user)->get(route('roles.index'))->assertOk();
    }

    public function test_guests_are_redirected_to_login_for_module_routes(): void
    {
        $this->get(route('mahasiswa.thesis'))->assertRedirect(route('login'));
    }

    public function test_user_without_a_mapped_role_falls_back_to_the_minimal_menu(): void
    {
        $user = User::factory()->create();

        $this->assertSame([], $user->menuGroups());

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }
}
