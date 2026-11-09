<?php

namespace Tests\Feature;

use App\Models\Faculty;
use App\Models\Student;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcedPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private const NIM = '22010001';

    private function student(): User
    {
        $studyProgram = StudyProgram::create([
            'faculty_id' => Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT'])->id,
            'name' => 'Teknik Informatika', 'code' => 'TI',
        ]);
        $user = User::factory()->withRoles('mahasiswa')->create(['email' => 'budi@umkendari.ac.id', 'password' => self::NIM]);
        $user->forceFill(['must_change_password' => true])->save();

        Student::create([
            'user_id' => $user->id, 'study_program_id' => $studyProgram->id,
            'nim' => self::NIM, 'name' => 'Budi', 'entry_year' => '2022', 'status' => 'aktif',
        ]);

        return $user->fresh();
    }

    public function test_logging_in_with_the_nim_leads_to_the_change_password_page(): void
    {
        $this->student();

        $this->post(route('login'), ['email' => 'budi@umkendari.ac.id', 'password' => self::NIM])->assertRedirect(route('dashboard'));

        $this->followingRedirects()->get(route('dashboard'))->assertOk()->assertSee('Ubah Password')->assertDontSee('nav-item');
    }

    public function test_every_page_and_action_is_held_until_the_password_changes(): void
    {
        $user = $this->student();

        foreach (['dashboard', 'mahasiswa.supervision', 'profile.edit', 'mahasiswa.title-submission'] as $route) {
            $this->actingAs($user)->get(route($route))->assertRedirect(route('password.force'));
        }

        $this->actingAs($user)->post(route('mahasiswa.supervision.store'), [])->assertRedirect(route('password.force'));
    }

    public function test_the_student_can_still_log_out(): void
    {
        $this->actingAs($this->student())->post(route('logout'))->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_changing_the_password_releases_the_student(): void
    {
        $user = $this->student();

        $this->actingAs($user)->put(route('password.force.update'), [
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('PasswordBaru123', $user->password));
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('password.force'))->assertRedirect(route('dashboard'));
    }

    public function test_the_nim_is_refused_as_the_new_password(): void
    {
        $user = $this->student();

        $this->actingAs($user)->put(route('password.force.update'), [
            'password' => self::NIM,
            'password_confirmation' => self::NIM,
        ])->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_the_new_password_must_be_confirmed(): void
    {
        $this->actingAs($this->student())->put(route('password.force.update'), [
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'Berbeda123',
        ])->assertSessionHasErrors('password');
    }

    public function test_going_back_to_the_nim_from_the_profile_page_forces_a_change_again(): void
    {
        $user = $this->student();
        $user->update(['password' => 'PasswordBaru123']);
        $this->assertFalse($user->fresh()->must_change_password);

        $this->actingAs($user->fresh())->put(route('password.update'), [
            'current_password' => 'PasswordBaru123',
            'password' => self::NIM.'x',
            'password_confirmation' => self::NIM.'x',
        ])->assertRedirect();
        $this->assertFalse($user->fresh()->must_change_password);

        $this->actingAs($user->fresh())->put(route('password.update'), [
            'current_password' => self::NIM.'x',
            'password' => self::NIM,
            'password_confirmation' => self::NIM,
        ])->assertRedirect();

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_an_admin_resetting_the_password_to_the_nim_forces_a_change(): void
    {
        $user = $this->student();
        $user->update(['password' => 'PasswordBaru123']);

        $this->actingAs(User::factory()->withRoles('administrator')->create())->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => self::NIM,
            'password_confirmation' => self::NIM,
            'roles' => $user->roles->pluck('id')->all(),
        ])->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_other_accounts_are_not_affected(): void
    {
        $lecturer = User::factory()->withRoles('dosen_pa')->create();

        $this->actingAs($lecturer)->get(route('dashboard'))->assertOk();
        $this->assertFalse($lecturer->fresh()->must_change_password);
    }

    public function test_the_page_is_only_for_logged_in_users(): void
    {
        $this->get(route('password.force'))->assertRedirect(route('login'));
    }
}
