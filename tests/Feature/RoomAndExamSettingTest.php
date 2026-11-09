<?php

namespace Tests\Feature;

use App\Enums\ExamKind;
use App\Models\Faculty;
use App\Models\Permission;
use App\Models\Room;
use App\Models\Setting;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomAndExamSettingTest extends TestCase
{
    use RefreshDatabase;

    private function program(string $code = 'TI'): StudyProgram
    {
        $faculty = Faculty::first() ?? Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT']);

        return StudyProgram::create(['faculty_id' => $faculty->id, 'name' => "Prodi {$code}", 'code' => $code]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function settingsPayload(StudyProgram $program, array $overrides = []): array
    {
        $exam = collect(ExamKind::cases())->mapWithKeys(fn (ExamKind $kind) => [$kind->value => [
            'min_supervisions' => 7,
            'pass_score' => 65,
            'examiner_weight' => 60,
            'supervisor_weight' => 40,
            'components' => "Materi:60\nPresentasi:40",
            'supervisor_approval' => '1',
        ]])->all();

        return array_replace_recursive([
            'study_program_id' => $program->id,
            'no_supervision_days' => 14,
            'max_duration_days' => 200,
            'exam' => $exam,
        ], $overrides);
    }

    public function test_admin_prodi_can_manage_rooms(): void
    {
        $admin = User::factory()->withRoles('admin_prodi')->create();

        $this->actingAs($admin)->get(route('prodi.rooms.create'))->assertOk();
        $this->actingAs($admin)->post(route('prodi.rooms.store'), ['name' => 'Ruang Sidang', 'code' => 'RS-1', 'is_active' => '1'])
            ->assertRedirect(route('prodi.rooms.index'));

        $room = Room::firstOrFail();
        $this->assertTrue($room->is_active);

        $this->actingAs($admin)->get(route('prodi.rooms.edit', $room))->assertOk()->assertSee('RS-1');
        $this->actingAs($admin)->put(route('prodi.rooms.update', $room), ['name' => 'Ruang Baru', 'code' => 'RS-1']);
        $this->assertFalse($room->fresh()->is_active);

        $this->actingAs($admin)->delete(route('prodi.rooms.destroy', $room));
        $this->assertModelMissing($room);
    }

    public function test_the_kaprodi_can_view_rooms_but_not_change_them(): void
    {
        $kaprodi = User::factory()->withRoles('kaprodi')->create();

        $this->actingAs($kaprodi)->get(route('prodi.rooms.index'))->assertOk();
        $this->actingAs($kaprodi)->post(route('prodi.rooms.store'), ['name' => 'X', 'code' => 'X'])->assertForbidden();
    }

    public function test_defaults_apply_until_a_program_saves_its_own_rules(): void
    {
        $program = $this->program();

        $this->assertSame(5, Setting::examRules($program->id, ExamKind::Proposal)['min_supervisions']);
        $this->assertSame(10, Setting::examRules($program->id, ExamKind::Result)['min_supervisions']);
        $this->assertSame(30, Setting::monitoringRules($program->id)['no_supervision_days']);
    }

    public function test_admin_prodi_can_save_rules_for_their_program(): void
    {
        $program = $this->program();
        $admin = User::factory()->withRoles('admin_prodi')->create(['study_program_id' => $program->id]);

        $this->actingAs($admin)->get(route('prodi.settings'))->assertOk()->assertSee('Minimal Bimbingan');
        $this->actingAs($admin)->put(route('prodi.settings.update'), $this->settingsPayload($program))
            ->assertRedirect(route('prodi.settings', ['program' => $program->id]));

        $rules = Setting::examRules($program->id, ExamKind::Proposal);
        $this->assertSame(7, $rules['min_supervisions']);
        $this->assertTrue($rules['supervisor_approval']);
        $this->assertFalse($rules['document_required']);
        $this->assertSame([['name' => 'Materi', 'weight' => 60], ['name' => 'Presentasi', 'weight' => 40]], $rules['components']);
        $this->assertSame(14, Setting::monitoringRules($program->id)['no_supervision_days']);
    }

    public function test_rules_are_kept_per_program(): void
    {
        $program = $this->program();
        $other = $this->program('SI');
        $admin = User::factory()->withRoles('admin_prodi')->create(['study_program_id' => $program->id]);

        $this->actingAs($admin)->put(route('prodi.settings.update'), $this->settingsPayload($program));

        $this->assertSame(5, Setting::examRules($other->id, ExamKind::Proposal)['min_supervisions']);
    }

    public function test_another_programs_rules_cannot_be_changed(): void
    {
        $program = $this->program();
        $other = $this->program('SI');
        $admin = User::factory()->withRoles('admin_prodi')->create(['study_program_id' => $program->id]);

        $this->actingAs($admin)->put(route('prodi.settings.update'), $this->settingsPayload($other))
            ->assertSessionHasErrors('study_program_id');

        $this->assertSame(5, Setting::examRules($other->id, ExamKind::Proposal)['min_supervisions']);
    }

    public function test_weights_must_add_up_to_one_hundred(): void
    {
        $program = $this->program();
        $admin = User::factory()->withRoles('admin_prodi')->create(['study_program_id' => $program->id]);

        $this->actingAs($admin)->put(route('prodi.settings.update'), $this->settingsPayload($program, [
            'exam' => ['proposal' => ['examiner_weight' => 50, 'supervisor_weight' => 40, 'components' => "Materi:50\nPresentasi:40"]],
        ]))->assertSessionHasErrors(['exam.proposal.examiner_weight', 'exam.proposal.components']);
    }

    public function test_the_kaprodi_can_read_rules_but_not_save_them(): void
    {
        $program = $this->program();
        $kaprodi = User::factory()->withRoles('kaprodi')->create(['study_program_id' => $program->id]);

        $kaprodi->roles->first()->permissions()->attach(Permission::where('name', 'lihat.setting')->value('id'));

        $this->actingAs($kaprodi->fresh())->get(route('prodi.settings'))->assertOk();
        $this->actingAs($kaprodi->fresh())->put(route('prodi.settings.update'), $this->settingsPayload($program))->assertForbidden();
    }
}
