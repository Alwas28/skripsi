<?php

namespace Tests\Feature\MasterData;

use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudyProgramManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRoles('admin_prodi')->create();
    }

    private function faculty(): Faculty
    {
        return Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT']);
    }

    public function test_the_create_and_edit_forms_render(): void
    {
        $studyProgram = StudyProgram::create([
            'faculty_id' => $this->faculty()->id, 'name' => 'Teknik Informatika', 'code' => 'TI',
        ]);

        $this->actingAs($this->admin())->get(route('prodi.study-programs.create'))->assertOk();
        $this->actingAs($this->admin())->get(route('prodi.study-programs.edit', $studyProgram))->assertOk()->assertSee('TI');
    }

    public function test_admin_prodi_can_create_a_study_program_under_a_faculty(): void
    {
        $faculty = $this->faculty();

        $this->actingAs($this->admin())->post(route('prodi.study-programs.store'), [
            'faculty_id' => $faculty->id, 'name' => 'Teknik Informatika', 'code' => 'TI', 'degree_level' => 'S1',
        ])->assertRedirect(route('prodi.study-programs.index'));

        $this->assertDatabaseHas('study_programs', ['code' => 'TI', 'faculty_id' => $faculty->id]);
    }

    public function test_a_study_program_with_students_cannot_be_deleted(): void
    {
        $studyProgram = StudyProgram::create(['faculty_id' => $this->faculty()->id, 'name' => 'TI', 'code' => 'TI']);
        Student::create(['study_program_id' => $studyProgram->id, 'nim' => '123', 'name' => 'Budi', 'entry_year' => '2022', 'status' => 'aktif']);

        $this->actingAs($this->admin())->delete(route('prodi.study-programs.destroy', $studyProgram))
            ->assertRedirect(route('prodi.study-programs.index'));

        $this->assertModelExists($studyProgram);
    }

    public function test_a_study_program_with_lecturers_cannot_be_deleted(): void
    {
        $studyProgram = StudyProgram::create(['faculty_id' => $this->faculty()->id, 'name' => 'TI', 'code' => 'TI']);
        Lecturer::create(['study_program_id' => $studyProgram->id, 'nidn' => '001', 'name' => 'Dr. Ani']);

        $this->actingAs($this->admin())->delete(route('prodi.study-programs.destroy', $studyProgram))
            ->assertRedirect(route('prodi.study-programs.index'));

        $this->assertModelExists($studyProgram);
    }
}
