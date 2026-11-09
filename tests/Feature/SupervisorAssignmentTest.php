<?php

namespace Tests\Feature;

use App\Enums\SupervisorApprovalStatus;
use App\Enums\ThesisStatus;
use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\StudyProgram;
use App\Models\Thesis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private StudyProgram $studyProgram;

    private Lecturer $lecturerOne;

    private Lecturer $lecturerTwo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->studyProgram = StudyProgram::create([
            'faculty_id' => Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT'])->id,
            'name' => 'Teknik Informatika', 'code' => 'TI',
        ]);
        $this->lecturerOne = $this->lecturer('0011', 'Ani Wijaya');
        $this->lecturerTwo = $this->lecturer('0022', 'Bima Putra');
    }

    private function lecturer(string $nidn, string $name, bool $isActive = true, ?int $studyProgramId = null): Lecturer
    {
        $user = User::factory()->withRoles('dosen_pembimbing')->create();

        return Lecturer::create([
            'user_id' => $user->id, 'study_program_id' => $studyProgramId ?? $this->studyProgram->id,
            'nidn' => $nidn, 'name' => $name, 'is_active' => $isActive,
        ]);
    }

    private function prodiUser(string $roleSlug, ?int $studyProgramId = null): User
    {
        return User::factory()->withRoles($roleSlug)->create(['study_program_id' => $studyProgramId ?? $this->studyProgram->id]);
    }

    private function thesis(ThesisStatus $status = ThesisStatus::TitleApproved): Thesis
    {
        $user = User::factory()->withRoles('mahasiswa')->create();
        $student = Student::create([
            'user_id' => $user->id, 'study_program_id' => $this->studyProgram->id,
            'academic_advisor_id' => $this->lecturerOne->id,
            'nim' => '2201', 'name' => 'Budi', 'entry_year' => '2022', 'status' => 'aktif',
        ]);
        $thesis = $student->thesis()->create(['status' => $status]);
        $thesis->title()->create(['title' => 'Sistem Monitoring']);

        return $thesis;
    }

    private function propose(Thesis $thesis): void
    {
        $this->actingAs($this->prodiUser('admin_prodi'))->post(route('prodi.supervisors.store', $thesis), [
            'supervisor_1_id' => $this->lecturerOne->id,
            'supervisor_2_id' => $this->lecturerTwo->id,
        ])->assertRedirect(route('prodi.supervisors.show', $thesis));
    }

    public function test_admin_prodi_proposal_waits_for_the_kaprodi(): void
    {
        $thesis = $this->thesis();

        $this->propose($thesis);

        $this->assertDatabaseHas('thesis_supervisors', ['thesis_id' => $thesis->id, 'lecturer_id' => $this->lecturerOne->id, 'position' => 1, 'status' => 'pending']);
        $this->assertDatabaseHas('thesis_supervisors', ['thesis_id' => $thesis->id, 'lecturer_id' => $this->lecturerTwo->id, 'position' => 2, 'status' => 'pending']);
        $this->assertSame(ThesisStatus::TitleApproved, $thesis->fresh()->status);
    }

    public function test_the_kaprodi_approval_assigns_the_supervisors(): void
    {
        $thesis = $this->thesis();
        $this->propose($thesis);
        $kaprodi = $this->prodiUser('kaprodi');

        $this->actingAs($kaprodi)->post(route('prodi.supervisors.approve', $thesis))->assertRedirect(route('prodi.supervisors'));

        $this->assertSame(ThesisStatus::SupervisorAssigned, $thesis->fresh()->status);
        $this->assertDatabaseMissing('thesis_supervisors', ['thesis_id' => $thesis->id, 'status' => 'pending']);
        $this->assertDatabaseHas('thesis_supervisors', ['thesis_id' => $thesis->id, 'position' => 1, 'status' => 'approved', 'reviewed_by' => $kaprodi->id]);
        $this->assertDatabaseHas('thesis_status_histories', ['thesis_id' => $thesis->id, 'to_status' => 'SUPERVISOR_ASSIGNED', 'changed_by' => $kaprodi->id]);
    }

    public function test_admin_prodi_cannot_approve_and_the_kaprodi_cannot_propose(): void
    {
        $thesis = $this->thesis();
        $this->propose($thesis);

        $this->actingAs($this->prodiUser('admin_prodi'))->post(route('prodi.supervisors.approve', $thesis))->assertForbidden();
        $this->actingAs($this->prodiUser('kaprodi'))->post(route('prodi.supervisors.store', $thesis), [
            'supervisor_1_id' => $this->lecturerTwo->id,
            'supervisor_2_id' => $this->lecturerOne->id,
        ])->assertForbidden();

        $this->assertSame(ThesisStatus::TitleApproved, $thesis->fresh()->status);
    }

    public function test_the_same_lecturer_cannot_hold_both_supervisor_positions(): void
    {
        $thesis = $this->thesis();

        $this->actingAs($this->prodiUser('admin_prodi'))->post(route('prodi.supervisors.store', $thesis), [
            'supervisor_1_id' => $this->lecturerOne->id,
            'supervisor_2_id' => $this->lecturerOne->id,
        ])->assertSessionHasErrors('supervisor_2_id');

        $this->assertDatabaseCount('thesis_supervisors', 0);
    }

    public function test_an_inactive_lecturer_cannot_be_proposed(): void
    {
        $thesis = $this->thesis();
        $inactive = $this->lecturer('0033', 'Citra Nonaktif', false);

        $this->actingAs($this->prodiUser('admin_prodi'))->post(route('prodi.supervisors.store', $thesis), [
            'supervisor_1_id' => $this->lecturerOne->id,
            'supervisor_2_id' => $inactive->id,
        ])->assertSessionHasErrors('supervisor_2_id');
    }

    public function test_a_supervisor_must_come_from_the_students_study_program(): void
    {
        $thesis = $this->thesis();
        $otherProgram = StudyProgram::create(['faculty_id' => Faculty::value('id'), 'name' => 'Sistem Informasi', 'code' => 'SI']);
        $outsider = $this->lecturer('0055', 'Eka Prodi Lain', true, $otherProgram->id);

        $this->actingAs($this->prodiUser('admin_prodi'))->get(route('prodi.supervisors.show', $thesis))
            ->assertOk()
            ->assertSee('Ani Wijaya')
            ->assertDontSee('Eka Prodi Lain');

        $this->actingAs($this->prodiUser('admin_prodi'))->post(route('prodi.supervisors.store', $thesis), [
            'supervisor_1_id' => $this->lecturerOne->id,
            'supervisor_2_id' => $outsider->id,
        ])->assertSessionHasErrors('supervisor_2_id');

        $this->assertDatabaseCount('thesis_supervisors', 0);
    }

    public function test_a_lecturer_without_the_pembimbing_role_cannot_be_proposed(): void
    {
        $thesis = $this->thesis();
        $lecturer = Lecturer::create([
            'user_id' => User::factory()->withRoles('dosen_penguji')->create()->id,
            'study_program_id' => $this->studyProgram->id,
            'nidn' => '0066', 'name' => 'Hanya Penguji', 'is_active' => true,
        ]);

        $this->actingAs($this->prodiUser('admin_prodi'))->post(route('prodi.supervisors.store', $thesis), [
            'supervisor_1_id' => $this->lecturerOne->id,
            'supervisor_2_id' => $lecturer->id,
        ])->assertSessionHasErrors('supervisor_2_id');
    }

    public function test_the_academic_advisor_may_also_be_a_supervisor(): void
    {
        $thesis = $this->thesis();

        $this->propose($thesis);

        $this->assertSame($this->lecturerOne->id, $thesis->student->academic_advisor_id);
        $this->assertDatabaseHas('thesis_supervisors', ['thesis_id' => $thesis->id, 'lecturer_id' => $this->lecturerOne->id]);
    }

    public function test_a_rejection_needs_notes_and_can_be_proposed_again(): void
    {
        $thesis = $this->thesis();
        $this->propose($thesis);
        $kaprodi = $this->prodiUser('kaprodi');

        $this->actingAs($kaprodi)->post(route('prodi.supervisors.reject', $thesis), [])->assertSessionHasErrors('review_notes');
        $this->actingAs($kaprodi)->post(route('prodi.supervisors.reject', $thesis), ['review_notes' => 'Beban terlalu banyak.'])
            ->assertRedirect(route('prodi.supervisors'));

        $this->assertDatabaseHas('thesis_supervisors', ['thesis_id' => $thesis->id, 'status' => 'rejected', 'review_notes' => 'Beban terlalu banyak.']);
        $this->assertSame(ThesisStatus::TitleApproved, $thesis->fresh()->status);

        $this->actingAs($kaprodi)->post(route('prodi.supervisors.approve', $thesis))->assertForbidden();

        $this->propose($thesis);

        $this->assertDatabaseCount('thesis_supervisors', 2);
        $this->assertDatabaseMissing('thesis_supervisors', ['thesis_id' => $thesis->id, 'status' => 'rejected']);
    }

    public function test_approving_without_a_proposal_is_forbidden(): void
    {
        $thesis = $this->thesis();

        $this->actingAs($this->prodiUser('kaprodi'))->post(route('prodi.supervisors.approve', $thesis))->assertForbidden();
    }

    public function test_supervisors_cannot_be_proposed_before_the_title_is_approved(): void
    {
        $thesis = $this->thesis(ThesisStatus::TitleReview);

        $this->actingAs($this->prodiUser('admin_prodi'))->post(route('prodi.supervisors.store', $thesis), [
            'supervisor_1_id' => $this->lecturerOne->id,
            'supervisor_2_id' => $this->lecturerTwo->id,
        ])->assertForbidden();

        $this->actingAs($this->prodiUser('admin_prodi'))->get(route('prodi.supervisors.show', $thesis))->assertNotFound();
    }

    public function test_assigned_supervisors_can_no_longer_be_replaced(): void
    {
        $thesis = $this->thesis();
        $this->propose($thesis);
        $this->actingAs($this->prodiUser('kaprodi'))->post(route('prodi.supervisors.approve', $thesis));

        $this->actingAs($this->prodiUser('admin_prodi'))->post(route('prodi.supervisors.store', $thesis), [
            'supervisor_1_id' => $this->lecturerTwo->id,
            'supervisor_2_id' => $this->lecturerOne->id,
        ])->assertForbidden();
    }

    public function test_staff_of_another_study_program_cannot_see_or_act(): void
    {
        $thesis = $this->thesis();
        $this->propose($thesis);
        $otherProgram = StudyProgram::create(['faculty_id' => Faculty::value('id'), 'name' => 'Sistem Informasi', 'code' => 'SI']);
        $otherKaprodi = $this->prodiUser('kaprodi', $otherProgram->id);

        $this->actingAs($otherKaprodi)->get(route('prodi.supervisors'))->assertOk()->assertDontSee(route('prodi.supervisors.show', $thesis));
        $this->actingAs($otherKaprodi)->get(route('prodi.supervisors.show', $thesis))->assertForbidden();
        $this->actingAs($otherKaprodi)->post(route('prodi.supervisors.approve', $thesis))->assertForbidden();
        $this->actingAs($otherKaprodi)->post(route('prodi.supervisors.reject', $thesis), ['review_notes' => 'x'])->assertForbidden();
    }

    public function test_the_lecturer_load_counts_only_approved_active_supervision(): void
    {
        $thesis = $this->thesis();
        $this->propose($thesis);
        $kaprodi = $this->prodiUser('kaprodi');

        $beforeApproval = $this->actingAs($kaprodi)->get(route('prodi.supervisors.show', $thesis))->assertOk()->viewData('lecturers');
        $this->assertSame(0, $beforeApproval->firstWhere('id', $this->lecturerOne->id)->supervision_load);

        $this->actingAs($kaprodi)->post(route('prodi.supervisors.approve', $thesis));

        $afterApproval = $this->actingAs($kaprodi)->get(route('prodi.supervisors.show', $thesis))->assertOk()->viewData('lecturers');
        $this->assertSame(1, $afterApproval->firstWhere('id', $this->lecturerOne->id)->supervision_load);
    }

    public function test_the_student_sees_supervisors_only_after_kaprodi_approval(): void
    {
        $thesis = $this->thesis();
        $this->propose($thesis);
        $student = $thesis->student->user;

        $this->actingAs($student)->get(route('mahasiswa.supervisor'))->assertOk()->assertDontSee('Ani Wijaya');

        $this->actingAs($this->prodiUser('kaprodi'))->post(route('prodi.supervisors.approve', $thesis));

        $this->actingAs($student)->get(route('mahasiswa.supervisor'))->assertOk()->assertSee('Ani Wijaya')->assertSee('Bima Putra');
    }

    public function test_a_lecturer_sees_only_their_own_approved_supervisees(): void
    {
        $thesis = $this->thesis();
        $this->propose($thesis);
        $otherLecturer = $this->lecturer('0044', 'Dewi Lain');

        $this->actingAs($this->lecturerOne->user)->get(route('dosen.supervisees'))->assertOk()->assertDontSee('Sistem Monitoring');

        $this->actingAs($this->prodiUser('kaprodi'))->post(route('prodi.supervisors.approve', $thesis));

        $this->actingAs($this->lecturerOne->user)->get(route('dosen.supervisees'))->assertOk()->assertSee('Sistem Monitoring')->assertSee('Pembimbing 1');
        $this->actingAs($otherLecturer->user)->get(route('dosen.supervisees'))->assertOk()->assertDontSee('Sistem Monitoring');
    }

    public function test_a_lecturer_who_is_a_supervisor_cannot_be_deleted(): void
    {
        $thesis = $this->thesis();
        $this->propose($thesis);

        $this->actingAs($this->prodiUser('admin_prodi'))->delete(route('prodi.lecturers.destroy', $this->lecturerOne))
            ->assertRedirect(route('prodi.lecturers.index'));

        $this->assertModelExists($this->lecturerOne);
        $this->assertSame(SupervisorApprovalStatus::Pending, $thesis->supervisors()->first()->status);
    }
}
