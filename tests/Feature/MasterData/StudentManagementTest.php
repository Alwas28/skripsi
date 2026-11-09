<?php

namespace Tests\Feature\MasterData;

use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRoles('admin_prodi')->create();
    }

    private function studyProgram(): StudyProgram
    {
        $faculty = Faculty::first() ?? Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT']);

        return StudyProgram::first() ?? StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'Teknik Informatika', 'code' => 'TI']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'email' => 'budi@umkendari.ac.id',
            'study_program_id' => $this->studyProgram()->id,
            'nim' => '22010001',
            'name' => 'Budi',
            'entry_year' => '2022',
            'status' => 'aktif',
        ], $overrides);
    }

    private function createStudent(array $overrides = []): Student
    {
        $this->actingAs($this->admin())->post(route('prodi.students.store'), $this->payload($overrides))
            ->assertRedirect(route('prodi.students.index'));

        return Student::where('nim', $overrides['nim'] ?? '22010001')->firstOrFail();
    }

    private function advisor(string $nidn, string $name, bool $isActive = true): Lecturer
    {
        return Lecturer::create([
            'user_id' => User::factory()->withRoles('dosen_pa')->create()->id,
            'nidn' => $nidn, 'name' => $name, 'is_active' => $isActive,
        ]);
    }

    public function test_the_create_and_edit_forms_render(): void
    {
        $student = $this->createStudent();

        $this->actingAs($this->admin())->get(route('prodi.students.create'))->assertOk()->assertSee('Email Login')->assertDontSee('name="user_id"', false);
        $this->actingAs($this->admin())->get(route('prodi.students.edit', $student))->assertOk()->assertSee('22010001')->assertSee('budi@umkendari.ac.id');
    }

    public function test_adding_a_student_creates_a_login_account_with_the_nim_as_password(): void
    {
        $student = $this->createStudent();
        $user = $student->user;

        $this->assertSame('budi@umkendari.ac.id', $user->email);
        $this->assertSame('Budi', $user->name);
        $this->assertTrue(Hash::check('22010001', $user->password));
        $this->assertTrue($user->must_change_password);
        $this->assertSame(['mahasiswa'], $user->roles->pluck('slug')->all());
    }

    public function test_the_email_is_required_and_must_be_unused(): void
    {
        User::factory()->create(['email' => 'budi@umkendari.ac.id']);

        $this->actingAs($this->admin())->post(route('prodi.students.store'), $this->payload(['email' => '']))->assertSessionHasErrors('email');
        $this->actingAs($this->admin())->post(route('prodi.students.store'), $this->payload())->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('students', ['nim' => '22010001']);
    }

    public function test_editing_updates_the_account_email_and_name(): void
    {
        $student = $this->createStudent();

        $this->actingAs($this->admin())->put(route('prodi.students.update', $student), $this->payload(['email' => 'baru@umkendari.ac.id', 'name' => 'Budi Santoso']))
            ->assertRedirect(route('prodi.students.index'));

        $user = $student->fresh()->user;
        $this->assertSame('baru@umkendari.ac.id', $user->email);
        $this->assertSame('Budi Santoso', $user->name);
        $this->assertSame(1, User::where('email', 'baru@umkendari.ac.id')->count());
    }

    public function test_keeping_the_same_email_is_not_a_conflict(): void
    {
        $student = $this->createStudent();

        $this->actingAs($this->admin())->put(route('prodi.students.update', $student), $this->payload())->assertSessionHasNoErrors();
    }

    public function test_correcting_the_nim_moves_a_still_default_password(): void
    {
        $student = $this->createStudent();

        $this->actingAs($this->admin())->put(route('prodi.students.update', $student), $this->payload(['nim' => '22010099']));

        $user = $student->fresh()->user;
        $this->assertTrue(Hash::check('22010099', $user->password));
        $this->assertTrue($user->must_change_password);
    }

    public function test_correcting_the_nim_leaves_a_changed_password_alone(): void
    {
        $student = $this->createStudent();
        $student->user->update(['password' => 'PasswordSendiri1']);

        $this->actingAs($this->admin())->put(route('prodi.students.update', $student), $this->payload(['nim' => '22010099']));

        $user = $student->fresh()->user;
        $this->assertTrue(Hash::check('PasswordSendiri1', $user->password));
        $this->assertFalse($user->must_change_password);
    }

    public function test_an_older_student_without_an_account_gets_one_when_edited(): void
    {
        $student = Student::create(['study_program_id' => $this->studyProgram()->id, 'nim' => '22010001', 'name' => 'Budi', 'entry_year' => '2022', 'status' => 'aktif']);

        $this->actingAs($this->admin())->put(route('prodi.students.update', $student), $this->payload())->assertSessionHasNoErrors();

        $this->assertTrue($student->fresh()->user->must_change_password);
    }

    public function test_deleting_a_student_removes_their_account(): void
    {
        $student = $this->createStudent();
        $user = $student->user;

        $this->actingAs($this->admin())->delete(route('prodi.students.destroy', $student))->assertRedirect(route('prodi.students.index'));

        $this->assertModelMissing($student);
        $this->assertModelMissing($user);
    }

    public function test_deleting_a_student_keeps_an_account_with_another_role(): void
    {
        $student = $this->createStudent();
        $user = $student->user;
        $user->roles()->attach(Role::where('slug', 'admin_prodi')->value('id'));

        $this->actingAs($this->admin())->delete(route('prodi.students.destroy', $student));

        $this->assertModelMissing($student);
        $this->assertModelExists($user);
    }

    public function test_an_academic_advisor_can_be_assigned_to_a_student(): void
    {
        $advisor = $this->advisor('0011', 'Ani Wijaya');

        $student = $this->createStudent(['academic_advisor_id' => $advisor->id]);

        $this->assertSame($advisor->id, $student->academic_advisor_id);
        $this->actingAs($this->admin())->get(route('prodi.students.index'))->assertOk()->assertSee('Ani Wijaya');
    }

    public function test_a_lecturer_without_the_dosen_pa_role_cannot_be_the_advisor(): void
    {
        $lecturer = Lecturer::create([
            'user_id' => User::factory()->withRoles('dosen_penguji')->create()->id,
            'nidn' => '0011', 'name' => 'Bukan PA', 'is_active' => true,
        ]);

        $this->actingAs($this->admin())->get(route('prodi.students.create'))->assertOk()->assertDontSee('Bukan PA');

        $this->actingAs($this->admin())->post(route('prodi.students.store'), $this->payload(['academic_advisor_id' => $lecturer->id]))
            ->assertSessionHasErrors('academic_advisor_id');
    }

    public function test_an_unknown_academic_advisor_is_rejected(): void
    {
        $this->actingAs($this->admin())->post(route('prodi.students.store'), $this->payload(['academic_advisor_id' => 999]))
            ->assertSessionHasErrors('academic_advisor_id');
    }

    public function test_an_inactive_lecturer_is_only_offered_when_already_the_students_advisor(): void
    {
        $inactive = $this->advisor('0011', 'Dosen Nonaktif', false);
        $student = Student::create([
            'academic_advisor_id' => $inactive->id, 'study_program_id' => $this->studyProgram()->id,
            'nim' => '2201', 'name' => 'Budi', 'entry_year' => '2022', 'status' => 'aktif',
        ]);

        $this->actingAs($this->admin())->get(route('prodi.students.create'))->assertOk()->assertDontSee('Dosen Nonaktif');
        $this->actingAs($this->admin())->get(route('prodi.students.edit', $student))->assertOk()->assertSee('Dosen Nonaktif');
    }

    public function test_duplicate_nim_is_rejected(): void
    {
        $this->createStudent();

        $this->actingAs($this->admin())->post(route('prodi.students.store'), $this->payload(['email' => 'citra@umkendari.ac.id', 'name' => 'Citra']))
            ->assertSessionHasErrors('nim');

        $this->assertDatabaseMissing('users', ['email' => 'citra@umkendari.ac.id']);
    }

    public function test_dosen_cannot_view_student_master_data(): void
    {
        $dosen = User::factory()->withRoles('dosen_pa')->create();

        $this->actingAs($dosen)->get(route('prodi.students.index'))->assertForbidden();
    }
}
