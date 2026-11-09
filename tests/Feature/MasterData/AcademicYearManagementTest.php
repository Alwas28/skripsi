<?php

namespace Tests\Feature\MasterData;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicYearManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRoles('admin_prodi')->create();
    }

    public function test_the_create_and_edit_forms_render(): void
    {
        $year = AcademicYear::create(['name' => '2025/2026', 'semester' => 'Ganjil']);

        $this->actingAs($this->admin())->get(route('prodi.academic-years.create'))->assertOk();
        $this->actingAs($this->admin())->get(route('prodi.academic-years.edit', $year))->assertOk()->assertSee('2025/2026');
    }

    public function test_marking_a_new_academic_year_active_deactivates_the_previous_one(): void
    {
        $current = AcademicYear::create(['name' => '2024/2025', 'semester' => 'Genap', 'is_active' => true]);

        $this->actingAs($this->admin())->post(route('prodi.academic-years.store'), [
            'name' => '2025/2026', 'semester' => 'Ganjil', 'is_active' => '1',
        ])->assertRedirect(route('prodi.academic-years.index'));

        $this->assertFalse($current->fresh()->is_active);
        $this->assertDatabaseHas('academic_years', ['name' => '2025/2026', 'is_active' => true]);
    }

    public function test_the_same_year_can_have_both_a_ganjil_and_genap_record(): void
    {
        AcademicYear::create(['name' => '2025/2026', 'semester' => 'Ganjil']);

        $this->actingAs($this->admin())->post(route('prodi.academic-years.store'), [
            'name' => '2025/2026', 'semester' => 'Genap',
        ])->assertRedirect(route('prodi.academic-years.index'));

        $this->assertDatabaseHas('academic_years', ['name' => '2025/2026', 'semester' => 'Genap']);
    }

    public function test_duplicate_year_and_semester_pair_is_rejected(): void
    {
        AcademicYear::create(['name' => '2025/2026', 'semester' => 'Ganjil']);

        $this->actingAs($this->admin())->post(route('prodi.academic-years.store'), [
            'name' => '2025/2026', 'semester' => 'Ganjil',
        ])->assertSessionHasErrors('name');
    }
}
