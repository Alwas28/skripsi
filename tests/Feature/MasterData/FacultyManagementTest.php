<?php

namespace Tests\Feature\MasterData;

use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacultyManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRoles('admin_prodi')->create();
    }

    public function test_the_create_and_edit_forms_render(): void
    {
        $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT']);

        $this->actingAs($this->admin())->get(route('prodi.faculties.create'))->assertOk();
        $this->actingAs($this->admin())->get(route('prodi.faculties.edit', $faculty))->assertOk()->assertSee('FT');
    }

    public function test_admin_prodi_can_create_a_faculty(): void
    {
        $this->actingAs($this->admin())->post(route('prodi.faculties.store'), [
            'name' => 'Fakultas Teknik', 'code' => 'FT',
        ])->assertRedirect(route('prodi.faculties.index'));

        $this->assertDatabaseHas('faculties', ['code' => 'FT']);
    }

    public function test_a_faculty_with_study_programs_cannot_be_deleted(): void
    {
        $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT']);
        StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'Teknik Informatika', 'code' => 'TI']);

        $this->actingAs($this->admin())->delete(route('prodi.faculties.destroy', $faculty))
            ->assertRedirect(route('prodi.faculties.index'));

        $this->assertModelExists($faculty);
    }

    public function test_an_empty_faculty_can_be_deleted(): void
    {
        $faculty = Faculty::create(['name' => 'Fakultas Kosong', 'code' => 'FK']);

        $this->actingAs($this->admin())->delete(route('prodi.faculties.destroy', $faculty))
            ->assertRedirect(route('prodi.faculties.index'));

        $this->assertModelMissing($faculty);
    }

    public function test_kaprodi_can_view_but_not_create_faculties(): void
    {
        $kaprodi = User::factory()->withRoles('kaprodi')->create();

        $this->actingAs($kaprodi)->get(route('prodi.faculties.index'))->assertOk();
        $this->actingAs($kaprodi)->post(route('prodi.faculties.store'), ['name' => 'X', 'code' => 'X'])->assertForbidden();
    }

    public function test_mahasiswa_cannot_view_faculties(): void
    {
        $mahasiswa = User::factory()->withRoles('mahasiswa')->create();

        $this->actingAs($mahasiswa)->get(route('prodi.faculties.index'))->assertForbidden();
    }
}
