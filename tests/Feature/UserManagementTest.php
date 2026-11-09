<?php

namespace Tests\Feature;

use App\Models\Faculty;
use App\Models\Role;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        return User::factory()->withRoles('administrator')->create();
    }

    public function test_the_create_and_edit_forms_render(): void
    {
        $admin = $this->administrator();
        $otherUser = User::factory()->create();

        $this->actingAs($admin)->get(route('users.create'))->assertOk()->assertSee('Tambah User');
        $this->actingAs($admin)->get(route('users.edit', $otherUser))->assertOk()->assertSee($otherUser->email);
    }

    public function test_administrator_can_create_a_user_with_several_roles(): void
    {
        $roles = Role::whereIn('slug', ['dosen_pa', 'dosen_penguji'])->pluck('id')->all();

        $this->actingAs($this->administrator())->post(route('users.store'), [
            'name' => 'Budi Dosen',
            'email' => 'budi@umkendari.ac.id',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => $roles,
        ])->assertRedirect(route('users.index'));

        $this->assertEqualsCanonicalizing(['dosen_pa', 'dosen_penguji'], User::where('email', 'budi@umkendari.ac.id')->firstOrFail()->roles->pluck('slug')->all());
    }

    public function test_administrator_can_update_a_users_role_without_touching_password(): void
    {
        $mahasiswa = Role::where('slug', 'mahasiswa')->firstOrFail();
        $dosen = Role::where('slug', 'dosen_pa')->firstOrFail();
        $user = User::factory()->withRoles($mahasiswa)->create();
        $originalPassword = $user->password;

        $this->actingAs($this->administrator())->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => [$dosen->id],
        ])->assertRedirect(route('users.index'));

        $user->refresh();
        $this->assertSame(['dosen_pa'], $user->roles()->pluck('slug')->all());
        $this->assertSame($originalPassword, $user->password);
    }

    public function test_administrator_can_assign_a_study_program_to_prodi_staff(): void
    {
        $studyProgram = StudyProgram::create([
            'faculty_id' => Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT'])->id,
            'name' => 'Teknik Informatika', 'code' => 'TI',
        ]);
        $user = User::factory()->create();

        $this->actingAs($this->administrator())->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'study_program_id' => $studyProgram->id,
        ])->assertRedirect(route('users.index'));

        $this->assertSame($studyProgram->id, $user->fresh()->study_program_id);
    }

    public function test_an_unknown_study_program_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->administrator())->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'study_program_id' => 999,
        ])->assertSessionHasErrors('study_program_id');
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->administrator())->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => [999],
        ])->assertSessionHasErrors('roles.0');
    }

    public function test_a_user_cannot_delete_their_own_account(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)->delete(route('users.destroy', $admin))
            ->assertRedirect(route('users.index'));

        $this->assertModelExists($admin);
    }

    public function test_administrator_can_delete_another_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->administrator())->delete(route('users.destroy', $user))
            ->assertRedirect(route('users.index'));

        $this->assertModelMissing($user);
    }

    public function test_a_role_without_hapus_user_permission_cannot_delete_users(): void
    {
        $mahasiswa = User::factory()->withRoles('mahasiswa')->create();
        $victim = User::factory()->create();

        $this->actingAs($mahasiswa)->delete(route('users.destroy', $victim))
            ->assertForbidden();

        $this->assertModelExists($victim);
    }
}
