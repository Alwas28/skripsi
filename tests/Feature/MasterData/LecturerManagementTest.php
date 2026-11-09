<?php

namespace Tests\Feature\MasterData;

use App\Models\Lecturer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LecturerManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRoles('admin_prodi')->create();
    }

    public function test_the_create_and_edit_forms_render(): void
    {
        $lecturer = Lecturer::create(['nidn' => '0011', 'name' => 'Dr. Ani']);

        $this->actingAs($this->admin())->get(route('prodi.lecturers.create'))->assertOk();
        $this->actingAs($this->admin())->get(route('prodi.lecturers.edit', $lecturer))->assertOk()->assertSee('0011');
    }

    public function test_admin_prodi_can_create_a_lecturer_not_tied_to_any_study_program(): void
    {
        $this->actingAs($this->admin())->post(route('prodi.lecturers.store'), [
            'nidn' => '0011', 'name' => 'Dr. Ani', 'expertise_field' => 'Basis Data', 'is_active' => '1',
        ])->assertRedirect(route('prodi.lecturers.index'));

        $this->assertDatabaseHas('lecturers', ['nidn' => '0011', 'study_program_id' => null, 'is_active' => true]);
    }

    public function test_omitting_is_active_saves_the_lecturer_as_inactive(): void
    {
        $this->actingAs($this->admin())->post(route('prodi.lecturers.store'), [
            'nidn' => '0011', 'name' => 'Dr. Ani',
        ])->assertRedirect(route('prodi.lecturers.index'));

        $this->assertDatabaseHas('lecturers', ['nidn' => '0011', 'is_active' => false]);
    }

    public function test_duplicate_nidn_is_rejected(): void
    {
        Lecturer::create(['nidn' => '0011', 'name' => 'Dr. Ani']);

        $this->actingAs($this->admin())->post(route('prodi.lecturers.store'), [
            'nidn' => '0011', 'name' => 'Dr. Budi',
        ])->assertSessionHasErrors('nidn');
    }

    public function test_kaprodi_cannot_delete_lecturers(): void
    {
        $kaprodi = User::factory()->withRoles('kaprodi')->create();
        $lecturer = Lecturer::create(['nidn' => '0011', 'name' => 'Dr. Ani']);

        $this->actingAs($kaprodi)->delete(route('prodi.lecturers.destroy', $lecturer))->assertForbidden();
        $this->assertModelExists($lecturer);
    }

    public function test_gelar_and_no_wa_are_saved(): void
    {
        $this->actingAs($this->admin())->post(route('prodi.lecturers.store'), [
            'nidn' => '0011', 'name' => 'Ani Wijaya',
            'gelar_depan' => 'Dr.', 'gelar_belakang' => 'M.Kom', 'no_wa' => '08123456789',
        ])->assertRedirect(route('prodi.lecturers.index'));

        $lecturer = Lecturer::where('nidn', '0011')->firstOrFail();
        $this->assertSame('Dr.', $lecturer->gelar_depan);
        $this->assertSame('M.Kom', $lecturer->gelar_belakang);
        $this->assertSame('08123456789', $lecturer->no_wa);
        $this->assertSame('Dr. Ani Wijaya, M.Kom', $lecturer->full_name);
    }

    public function test_filling_email_and_password_provisions_a_login_account_with_the_dosen_role_by_default(): void
    {
        $this->actingAs($this->admin())->post(route('prodi.lecturers.store'), [
            'nidn' => '0011', 'name' => 'Ani Wijaya',
            'new_account_email' => 'ani@umkendari.ac.id',
            'new_account_password' => 'password123',
            'new_account_password_confirmation' => 'password123',
        ])->assertRedirect(route('prodi.lecturers.index'));

        $lecturer = Lecturer::where('nidn', '0011')->firstOrFail();
        $this->assertNotNull($lecturer->user_id);
        $this->assertSame('ani@umkendari.ac.id', $lecturer->user->email);
        $this->assertEqualsCanonicalizing(['dosen_pa', 'dosen_pembimbing', 'dosen_penguji'], $lecturer->user->roles->pluck('slug')->all());
        $this->assertTrue(Hash::check('password123', $lecturer->user->password));
    }

    public function test_the_freshly_provisioned_credentials_can_actually_log_in(): void
    {
        $this->actingAs($this->admin())->post(route('prodi.lecturers.store'), [
            'nidn' => '0011', 'name' => 'Ani Wijaya',
            'new_account_email' => 'ani@umkendari.ac.id',
            'new_account_password' => 'password123',
            'new_account_password_confirmation' => 'password123',
        ]);

        $this->post(route('logout'));

        $this->post(route('login'), ['email' => 'ani@umkendari.ac.id', 'password' => 'password123'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs(Lecturer::where('nidn', '0011')->firstOrFail()->user);
    }

    public function test_a_custom_role_can_be_chosen_when_provisioning_the_account(): void
    {
        $kaprodiRole = Role::where('slug', 'kaprodi')->firstOrFail();

        $this->actingAs($this->admin())->post(route('prodi.lecturers.store'), [
            'nidn' => '0011', 'name' => 'Ani Wijaya',
            'new_account_email' => 'ani@umkendari.ac.id',
            'new_account_password' => 'password123',
            'new_account_password_confirmation' => 'password123',
            'new_account_roles' => [$kaprodiRole->id],
        ])->assertRedirect(route('prodi.lecturers.index'));

        $this->assertSame(['kaprodi'], Lecturer::where('nidn', '0011')->firstOrFail()->user->roles->pluck('slug')->all());
    }

    public function test_leaving_the_account_password_blank_creates_no_account(): void
    {
        $this->actingAs($this->admin())->post(route('prodi.lecturers.store'), [
            'nidn' => '0011', 'name' => 'Ani Wijaya',
            'new_account_email' => 'ani@umkendari.ac.id',
        ])->assertSessionHasErrors('new_account_password');

        $this->assertDatabaseMissing('lecturers', ['nidn' => '0011']);
        $this->assertDatabaseMissing('users', ['email' => 'ani@umkendari.ac.id']);
    }

    public function test_an_email_already_taken_is_rejected_and_nothing_is_persisted(): void
    {
        User::factory()->create(['email' => 'ani@umkendari.ac.id']);

        $this->actingAs($this->admin())->post(route('prodi.lecturers.store'), [
            'nidn' => '0011', 'name' => 'Ani Wijaya',
            'new_account_email' => 'ani@umkendari.ac.id',
            'new_account_password' => 'password123',
            'new_account_password_confirmation' => 'password123',
        ])->assertSessionHasErrors('new_account_email');

        // the whole request rolls back — the lecturer must not be half-created
        $this->assertDatabaseMissing('lecturers', ['nidn' => '0011']);
    }

    public function test_quick_connect_modal_creates_and_links_an_account_for_an_existing_lecturer(): void
    {
        $lecturer = Lecturer::create(['nidn' => '0011', 'name' => 'Ani Wijaya']);

        $this->actingAs($this->admin())->post(route('prodi.lecturers.connect-account', $lecturer), [
            'email' => 'ani@umkendari.ac.id',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('prodi.lecturers.index'));

        $lecturer->refresh();
        $this->assertNotNull($lecturer->user_id);
        $this->assertSame('ani@umkendari.ac.id', $lecturer->user->email);
        $this->assertEqualsCanonicalizing(['dosen_pa', 'dosen_pembimbing', 'dosen_penguji'], $lecturer->user->roles->pluck('slug')->all());
    }

    public function test_quick_connect_refuses_a_lecturer_that_is_already_linked(): void
    {
        $existingUser = User::factory()->create();
        $lecturer = Lecturer::create(['nidn' => '0011', 'name' => 'Ani Wijaya', 'user_id' => $existingUser->id]);

        $this->actingAs($this->admin())->post(route('prodi.lecturers.connect-account', $lecturer), [
            'email' => 'someone-else@umkendari.ac.id',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('prodi.lecturers.index'));

        $this->assertDatabaseMissing('users', ['email' => 'someone-else@umkendari.ac.id']);
        $this->assertSame($existingUser->id, $lecturer->fresh()->user_id);
    }

    public function test_a_role_without_edit_lecturer_permission_cannot_use_quick_connect(): void
    {
        $mahasiswa = User::factory()->withRoles('mahasiswa')->create();
        $lecturer = Lecturer::create(['nidn' => '0011', 'name' => 'Ani Wijaya']);

        $this->actingAs($mahasiswa)->post(route('prodi.lecturers.connect-account', $lecturer), [
            'email' => 'ani@umkendari.ac.id', 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertForbidden();

        $this->assertNull($lecturer->fresh()->user_id);
    }
}
