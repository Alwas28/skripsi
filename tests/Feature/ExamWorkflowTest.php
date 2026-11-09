<?php

namespace Tests\Feature;

use App\Enums\DocumentStage;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamDecision;
use App\Enums\ExamKind;
use App\Enums\ExamRevisionStatus;
use App\Enums\ThesisStatus;
use App\Models\ExamAttempt;
use App\Models\ExamReadinessApproval;
use App\Models\ExamType;
use App\Models\Faculty;
use App\Models\Role;
use App\Models\Room;
use App\Models\Setting;
use App\Models\StudyProgram;
use App\Models\Thesis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsThesisWorkflow;
use Tests\TestCase;

class ExamWorkflowTest extends TestCase
{
    use BuildsThesisWorkflow;
    use RefreshDatabase;

    private const EXAM_DATE = '2026-12-01';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Carbon::setTestNow('2026-11-20 08:00:00');
        $this->buildCast();
    }

    private function pdf(string $name = 'berkas.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 100, 'application/pdf');
    }

    private function eligibleThesis(?User $student = null): Thesis
    {
        $thesis = $this->thesisAt(ThesisStatus::Supervision, $student);
        $this->approvedSessions($thesis, 5);
        $this->approveReadiness($thesis, 1);
        $thesis->documents()->create(['stage' => 'proposal', 'name' => 'Proposal'])->addVersion($this->pdf(), $this->studentUser);

        return $thesis->fresh();
    }

    private function approveReadiness(Thesis $thesis, int $attempt, ExamKind $kind = ExamKind::Proposal): void
    {
        foreach ([$this->supervisorOne, $this->supervisorTwo] as $supervisor) {
            ExamReadinessApproval::create(['thesis_id' => $thesis->id, 'exam_kind' => $kind->value, 'attempt_number' => $attempt, 'lecturer_id' => $supervisor->id]);
        }
    }

    private function requestExam(ExamKind $kind = ExamKind::Proposal): ExamAttempt
    {
        $this->actingAs($this->studentUser->fresh())->post(route('mahasiswa.exams.request', $kind))->assertRedirect(route("mahasiswa.exams.{$kind->value}"));

        return ExamAttempt::latest('id')->firstOrFail();
    }

    private function verify(ExamAttempt $attempt): void
    {
        $this->actingAs($this->adminProdi)->post(route('prodi.exams.verify', $attempt), ['action' => 'approve', 'administration_ok' => '1'])->assertRedirect();
    }

    private function assignExaminers(ExamAttempt $attempt): void
    {
        $this->actingAs($this->adminProdi)->post(route('prodi.exams.examiners', $attempt), [
            'examiner_1_id' => $this->examinerOne->id,
            'examiner_2_id' => $this->examinerTwo->id,
        ])->assertRedirect();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function slot(array $overrides = []): array
    {
        return array_merge(['room_id' => $this->room->id, 'exam_date' => self::EXAM_DATE, 'start_time' => '10:00', 'end_time' => '11:30'], $overrides);
    }

    private function schedule(ExamAttempt $attempt): void
    {
        $this->actingAs($this->adminProdi)->post(route('prodi.exams.schedule', $attempt), $this->slot())->assertSessionHasNoErrors();
    }

    private function scheduledAttempt(?Thesis $thesis = null): ExamAttempt
    {
        $thesis ??= $this->eligibleThesis();
        $attempt = $this->requestExam();
        $this->verify($attempt);
        $this->assignExaminers($attempt);
        $this->schedule($attempt);

        return $attempt->fresh();
    }

    private function grade(ExamAttempt $attempt, float $examinerScore, float $supervisorScore, string $revisions = ''): void
    {
        Carbon::setTestNow(self::EXAM_DATE.' 12:00:00');

        foreach ([1 => $this->examinerOne, 2 => $this->examinerTwo] as $position => $examiner) {
            $this->actingAs($examiner->user)->post(route('dosen.assessment.store', $attempt), [
                'assessor_role' => "examiner_{$position}",
                'scores' => [$examinerScore, $examinerScore, $examinerScore],
                'revisions' => $position === 1 ? $revisions : '',
            ])->assertSessionHasNoErrors();
        }

        foreach ([1 => $this->supervisorOne, 2 => $this->supervisorTwo] as $position => $supervisor) {
            $this->actingAs($supervisor->user)->post(route('dosen.assessment.store', $attempt), [
                'assessor_role' => "supervisor_{$position}",
                'score' => $supervisorScore,
            ])->assertSessionHasNoErrors();
        }
    }

    private function decide(ExamAttempt $attempt, ExamDecision $decision): void
    {
        $this->actingAs($this->kaprodi)->post(route('prodi.exams.decide', $attempt), ['decision' => $decision->value])->assertSessionHasNoErrors();
    }

    public function test_the_whole_exam_passes_and_moves_to_research(): void
    {
        $thesis = $this->eligibleThesis();
        $attempt = $this->scheduledAttempt($thesis);

        $this->assertSame(ThesisStatus::ProposalExam, $thesis->fresh()->status);

        $this->grade($attempt, 80, 70);
        $this->decide($attempt, ExamDecision::Passed);

        $thesis->refresh();
        $this->assertSame(ThesisStatus::Research, $thesis->status);
        $this->assertDatabaseHas('thesis_status_histories', ['thesis_id' => $thesis->id, 'to_status' => 'PROPOSAL_PASSED']);
        $this->assertSame(77.0, $attempt->fresh()->result->final_score);
        $this->assertSame(ExamAttemptStatus::Decided, $attempt->fresh()->status);
        $this->assertSame(1, $thesis->documents()->where('stage', DocumentStage::Minutes->value)->count());
        $this->assertContains('Nilai tersedia: Lulus', $this->studentUser->notifications()->get()->pluck('data.title')->all());
    }

    public function test_an_exam_cannot_be_requested_until_requirements_are_met(): void
    {
        $this->thesisAt(ThesisStatus::Supervision);

        $this->actingAs($this->studentUser->fresh())->post(route('mahasiswa.exams.request', ExamKind::Proposal))->assertForbidden();
        $this->assertDatabaseCount('exam_attempts', 0);
    }

    public function test_an_exam_of_another_stage_cannot_be_requested(): void
    {
        $this->eligibleThesis();

        $this->actingAs($this->studentUser->fresh())->post(route('mahasiswa.exams.request', ExamKind::Thesis))->assertForbidden();
    }

    public function test_the_study_programs_settings_decide_the_requirements(): void
    {
        $thesis = $this->thesisAt(ThesisStatus::Supervision);
        Setting::store($this->studyProgram->id, 'exam.proposal', ['min_supervisions' => 0, 'supervisor_approval' => false, 'document_required' => false]);

        $this->assertSame([], $thesis->examRequirementChecks(ExamKind::Proposal));
        $this->requestExam();

        $this->assertSame(ThesisStatus::ReadyProposalExam, $thesis->fresh()->status);
    }

    public function test_requesting_notifies_admin_prodi(): void
    {
        $this->eligibleThesis();
        $this->requestExam();

        $this->assertSame(1, $this->adminProdi->notifications()->count());
    }

    public function test_a_rejected_request_returns_to_supervision_and_can_be_repeated(): void
    {
        $thesis = $this->eligibleThesis();
        $attempt = $this->requestExam();

        $this->actingAs($this->adminProdi)->post(route('prodi.exams.verify', $attempt), ['action' => 'reject'])->assertSessionHasErrors('notes');
        $this->actingAs($this->adminProdi)->post(route('prodi.exams.verify', $attempt), ['action' => 'reject', 'notes' => 'Berkas kurang.'])->assertRedirect();

        $this->assertSame(ThesisStatus::Supervision, $thesis->fresh()->status);
        $this->assertSame(ExamAttemptStatus::Rejected, $attempt->fresh()->status);

        $second = $this->requestExam();
        $this->assertSame(1, $second->attempt_number);
        $this->assertDatabaseCount('exam_attempts', 2);
    }

    public function test_verification_needs_the_administration_confirmation(): void
    {
        $this->eligibleThesis();
        $attempt = $this->requestExam();

        $this->actingAs($this->adminProdi)->post(route('prodi.exams.verify', $attempt), ['action' => 'approve'])->assertSessionHasErrors('administration_ok');

        $this->assertSame(ExamAttemptStatus::Requested, $attempt->fresh()->status);
    }

    public function test_only_admin_prodi_can_verify_and_only_for_their_program(): void
    {
        $this->eligibleThesis();
        $attempt = $this->requestExam();
        $otherProgram = StudyProgram::create(['faculty_id' => Faculty::value('id'), 'name' => 'Sistem Informasi', 'code' => 'SI']);
        $outsider = User::factory()->withRoles('admin_prodi')->create(['study_program_id' => $otherProgram->id]);

        $this->actingAs($this->kaprodi)->post(route('prodi.exams.verify', $attempt), ['action' => 'approve', 'administration_ok' => '1'])->assertForbidden();
        $this->actingAs($outsider)->post(route('prodi.exams.verify', $attempt), ['action' => 'approve', 'administration_ok' => '1'])->assertForbidden();
        $this->actingAs($outsider)->get(route('prodi.exams.proposal'))->assertOk()->assertDontSee(route('prodi.exams.attempt', $attempt));
    }

    public function test_examiners_must_be_two_different_active_penguji(): void
    {
        $this->eligibleThesis();
        $attempt = $this->requestExam();
        $this->verify($attempt);
        $admin = $this->actingAs($this->adminProdi);

        $admin->post(route('prodi.exams.examiners', $attempt), ['examiner_1_id' => $this->examinerOne->id, 'examiner_2_id' => $this->examinerOne->id])->assertSessionHasErrors('examiner_2_id');
        $admin->post(route('prodi.exams.examiners', $attempt), ['examiner_1_id' => $this->examinerOne->id, 'examiner_2_id' => $this->supervisorOne->id])->assertSessionHasErrors('examiner_2_id');

        $this->assertDatabaseCount('exam_examiners', 0);
    }

    public function test_a_supervisor_who_also_holds_the_penguji_role_may_examine(): void
    {
        $this->eligibleThesis();
        $this->supervisorOne->user->roles()->attach(Role::where('slug', 'dosen_penguji')->value('id'));
        $attempt = $this->requestExam();
        $this->verify($attempt);

        $this->actingAs($this->adminProdi)->post(route('prodi.exams.examiners', $attempt), [
            'examiner_1_id' => $this->supervisorOne->id,
            'examiner_2_id' => $this->examinerTwo->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(['supervisor_1', 'supervisor_2', 'examiner_1', 'examiner_2'], array_values(array_unique([...array_filter(array_keys($attempt->fresh()->requiredAssessors()), fn ($r) => str_starts_with($r, 'supervisor')), ...array_filter(array_keys($attempt->fresh()->requiredAssessors()), fn ($r) => str_starts_with($r, 'examiner'))])));
    }

    public function test_scheduling_needs_examiners_first(): void
    {
        $this->eligibleThesis();
        $attempt = $this->requestExam();
        $this->verify($attempt);

        $this->actingAs($this->adminProdi)->post(route('prodi.exams.schedule', $attempt), $this->slot())->assertForbidden();
    }

    /**
     * Builds a second student's exam already sitting in the 10:00 slot.
     */
    private function occupiedSlot(callable $configure): ExamAttempt
    {
        $otherUser = $this->makeStudent('2299', 'Mahasiswa Lain');
        $otherThesis = $otherUser->studentRecord->thesis()->create(['status' => ThesisStatus::ProposalExam]);
        $exam = $otherThesis->exams()->create(['exam_type_id' => ExamType::where('code', 'proposal')->value('id')]);
        $attempt = $exam->attempts()->create(['attempt_number' => 1, 'status' => ExamAttemptStatus::Scheduled]);
        $configure($attempt, $otherThesis);

        return $attempt;
    }

    public function test_a_double_booked_room_is_rejected(): void
    {
        $this->eligibleThesis();
        $attempt = $this->requestExam();
        $this->verify($attempt);
        $this->assignExaminers($attempt);

        $this->occupiedSlot(fn (ExamAttempt $other) => $other->schedule()->create($this->slot()));

        $this->actingAs($this->adminProdi)->post(route('prodi.exams.schedule', $attempt), $this->slot())
            ->assertSessionHasErrors('schedule');

        $this->assertSame(ExamAttemptStatus::Verified, $attempt->fresh()->status);
    }

    public function test_a_busy_examiner_is_named_in_the_conflict(): void
    {
        $this->eligibleThesis();
        $attempt = $this->requestExam();
        $this->verify($attempt);
        $this->assignExaminers($attempt);
        $otherRoom = Room::create(['name' => 'Ruang Dua', 'code' => 'R2', 'is_active' => true]);

        $this->occupiedSlot(function (ExamAttempt $other) use ($otherRoom) {
            $other->schedule()->create($this->slot(['room_id' => $otherRoom->id]));
            $other->examiners()->create(['lecturer_id' => $this->examinerOne->id, 'position' => 1]);
        });

        $this->actingAs($this->adminProdi)->post(route('prodi.exams.schedule', $attempt), $this->slot())
            ->assertSessionHasErrors(['schedule' => 'Penguji 1 (Citra Penguji) sudah memiliki jadwal pada pukul 10.00–11.30.']);
    }

    public function test_a_busy_supervisor_conflicts_too(): void
    {
        $this->eligibleThesis();
        $attempt = $this->requestExam();
        $this->verify($attempt);
        $this->assignExaminers($attempt);
        $otherRoom = Room::create(['name' => 'Ruang Dua', 'code' => 'R2', 'is_active' => true]);

        $this->occupiedSlot(function (ExamAttempt $other, Thesis $otherThesis) use ($otherRoom) {
            $other->schedule()->create($this->slot(['room_id' => $otherRoom->id]));
            $otherThesis->supervisors()->create(['lecturer_id' => $this->supervisorTwo->id, 'position' => 1, 'status' => 'approved']);
        });

        $this->actingAs($this->adminProdi)->post(route('prodi.exams.schedule', $attempt), $this->slot())->assertSessionHasErrors('schedule');
    }

    public function test_adjacent_slots_do_not_conflict(): void
    {
        $this->eligibleThesis();
        $attempt = $this->requestExam();
        $this->verify($attempt);
        $this->assignExaminers($attempt);

        $this->occupiedSlot(fn (ExamAttempt $other) => $other->schedule()->create($this->slot(['start_time' => '08:00', 'end_time' => '10:00'])));

        $this->actingAs($this->adminProdi)->post(route('prodi.exams.schedule', $attempt), $this->slot())->assertSessionHasNoErrors();
    }

    public function test_an_exam_can_be_rescheduled_without_conflicting_with_itself(): void
    {
        $attempt = $this->scheduledAttempt();

        $this->actingAs($this->adminProdi)->post(route('prodi.exams.schedule', $attempt), $this->slot(['start_time' => '10:30', 'end_time' => '12:00']))->assertSessionHasNoErrors();

        $this->assertStringStartsWith('10:30', $attempt->fresh()->schedule->start_time);
    }

    public function test_a_past_date_cannot_be_scheduled(): void
    {
        $this->eligibleThesis();
        $attempt = $this->requestExam();
        $this->verify($attempt);
        $this->assignExaminers($attempt);

        $this->actingAs($this->adminProdi)->post(route('prodi.exams.schedule', $attempt), $this->slot(['exam_date' => '2026-11-01']))->assertSessionHasErrors('exam_date');
    }

    public function test_grading_opens_on_the_exam_day_only_for_assessors(): void
    {
        $attempt = $this->scheduledAttempt();
        $payload = ['assessor_role' => 'examiner_1', 'scores' => [80, 80, 80]];

        $this->actingAs($this->examinerOne->user)->post(route('dosen.assessment.store', $attempt), $payload)->assertForbidden();

        Carbon::setTestNow(self::EXAM_DATE.' 09:00:00');
        $this->actingAs($this->makeLecturer('0098', 'Luar', 'dosen_penguji')->user)->post(route('dosen.assessment.store', $attempt), $payload)->assertForbidden();
        $this->actingAs($this->examinerTwo->user)->post(route('dosen.assessment.store', $attempt), $payload)->assertSessionHasErrors('assessor_role');
        $this->actingAs($this->examinerOne->user)->post(route('dosen.assessment.store', $attempt), $payload)->assertSessionHasNoErrors();
    }

    public function test_the_examiner_score_is_weighted_by_component(): void
    {
        $attempt = $this->scheduledAttempt();
        Carbon::setTestNow(self::EXAM_DATE.' 09:00:00');

        $this->actingAs($this->examinerOne->user)->post(route('dosen.assessment.store', $attempt), ['assessor_role' => 'examiner_1', 'scores' => [100, 50, 50]]);

        $this->assertSame(70.0, $attempt->assessments()->firstOrFail()->score);
        $this->assertSame(3, $attempt->assessments()->firstOrFail()->details()->count());
    }

    public function test_a_decision_needs_every_assessment(): void
    {
        $attempt = $this->scheduledAttempt();

        $this->actingAs($this->kaprodi)->post(route('prodi.exams.decide', $attempt), ['decision' => 'passed'])->assertForbidden();
    }

    public function test_only_the_kaprodi_decides(): void
    {
        $attempt = $this->scheduledAttempt();
        $this->grade($attempt, 80, 80);

        $this->actingAs($this->adminProdi)->post(route('prodi.exams.decide', $attempt), ['decision' => 'passed'])->assertForbidden();
    }

    public function test_a_score_below_the_pass_mark_can_only_fail(): void
    {
        $attempt = $this->scheduledAttempt();
        $this->grade($attempt, 50, 50);

        $this->actingAs($this->kaprodi)->post(route('prodi.exams.decide', $attempt), ['decision' => 'passed'])->assertSessionHasErrors('decision');
    }

    public function test_a_passing_score_cannot_be_failed(): void
    {
        $attempt = $this->scheduledAttempt();
        $this->grade($attempt, 80, 80);

        $this->actingAs($this->kaprodi)->post(route('prodi.exams.decide', $attempt), ['decision' => 'failed'])->assertSessionHasErrors('decision');
    }

    public function test_revision_needs_at_least_one_item(): void
    {
        $attempt = $this->scheduledAttempt();
        $this->grade($attempt, 80, 80);

        $this->actingAs($this->kaprodi)->post(route('prodi.exams.decide', $attempt), ['decision' => 'revision'])->assertSessionHasErrors('decision');
    }

    public function test_a_malformed_revision_line_is_rejected(): void
    {
        $attempt = $this->scheduledAttempt();
        Carbon::setTestNow(self::EXAM_DATE.' 09:00:00');

        $this->actingAs($this->examinerOne->user)->post(route('dosen.assessment.store', $attempt), [
            'assessor_role' => 'examiner_1', 'scores' => [80, 80, 80], 'revisions' => 'tanpa pemisah',
        ])->assertSessionHasErrors('revisions');
    }

    public function test_the_revision_cycle_ends_the_exam_when_every_item_is_approved(): void
    {
        $thesis = $this->eligibleThesis();
        $attempt = $this->scheduledAttempt($thesis);
        $this->grade($attempt, 80, 80, "Bab II: Perbaiki teori\nBab III: Perjelas metode");
        $this->decide($attempt, ExamDecision::Revision);
        $this->assertSame(ThesisStatus::ProposalRevision, $thesis->fresh()->status);

        [$first, $second] = $attempt->revisions()->orderBy('id')->get()->all();
        $examiner = $this->examinerOne->user;

        $this->actingAs($this->studentUser->fresh())->get(route('mahasiswa.revisions'))->assertOk()->assertSee('Perbaiki teori');
        $this->actingAs($this->studentUser->fresh())->post(route('mahasiswa.revisions.submit', $first), ['file' => $this->pdf()])->assertRedirect();
        $this->actingAs($examiner)->post(route('dosen.revisions.validate', $first), ['decision' => 'revision'])->assertSessionHasErrors('notes');
        $this->actingAs($examiner)->post(route('dosen.revisions.validate', $first), ['decision' => 'revision', 'notes' => 'Kurang.'])->assertRedirect();
        $this->assertSame(ExamRevisionStatus::Open, $first->fresh()->status);

        $this->actingAs($this->studentUser->fresh())->post(route('mahasiswa.revisions.submit', $first), ['file' => $this->pdf('v2.pdf')])->assertRedirect();
        $this->actingAs($examiner)->get(route('dosen.revisions.download', $first))->assertOk();
        $this->actingAs($examiner)->post(route('dosen.revisions.validate', $first), ['decision' => 'approved'])->assertRedirect();
        $this->assertSame(ThesisStatus::ProposalRevision, $thesis->fresh()->status);

        $this->actingAs($this->studentUser->fresh())->post(route('mahasiswa.revisions.submit', $second), ['file' => $this->pdf()])->assertRedirect();
        $this->actingAs($examiner)->post(route('dosen.revisions.validate', $second), ['decision' => 'approved'])->assertRedirect();

        $this->assertSame(ThesisStatus::Research, $thesis->fresh()->status);
        $this->assertSame(2, $first->validations()->count());
    }

    public function test_another_examiner_or_student_cannot_touch_a_revision(): void
    {
        $thesis = $this->eligibleThesis();
        $attempt = $this->scheduledAttempt($thesis);
        $this->grade($attempt, 80, 80, 'Bab II: Perbaiki teori');
        $this->decide($attempt, ExamDecision::Revision);
        $revision = $attempt->revisions()->firstOrFail();
        $other = $this->makeStudent('2298', 'Lain');
        $this->thesisAt(ThesisStatus::Supervision, $other);

        $this->actingAs($other)->post(route('mahasiswa.revisions.submit', $revision), ['file' => $this->pdf()])->assertForbidden();

        $this->actingAs($this->studentUser->fresh())->post(route('mahasiswa.revisions.submit', $revision), ['file' => $this->pdf()]);
        $this->actingAs($this->examinerTwo->user)->post(route('dosen.revisions.validate', $revision), ['decision' => 'approved'])->assertForbidden();
    }

    public function test_a_failed_exam_goes_through_resupervision_and_a_new_attempt_keeping_history(): void
    {
        $thesis = $this->eligibleThesis();
        $first = $this->scheduledAttempt($thesis);
        $this->grade($first, 50, 50);
        $this->decide($first, ExamDecision::Failed);
        $this->assertSame(ThesisStatus::ProposalFailed, $thesis->fresh()->status);

        $this->actingAs($this->studentUser->fresh())->post(route('mahasiswa.exams.request', ExamKind::Proposal))->assertForbidden();

        $this->actingAs($this->studentUser->fresh())->post(route('mahasiswa.supervision.store'), [
            'lecturer_id' => $this->supervisorOne->id, 'chapter' => 'Bab I', 'topic' => 'Perbaikan',
            'session_date' => self::EXAM_DATE, 'file' => $this->pdf(),
        ])->assertRedirect();
        $this->assertSame(ThesisStatus::ProposalResupervision, $thesis->fresh()->status);

        $session = $thesis->supervisionSessions()->latest('id')->firstOrFail();
        $this->actingAs($this->supervisorOne->user)->post(route('dosen.supervision.review', $session), ['decision' => 'approved']);

        $this->actingAs($this->studentUser->fresh())->post(route('mahasiswa.exams.request', ExamKind::Proposal))->assertForbidden();

        $this->approveReadiness($thesis, 2);
        $second = $this->requestExam();

        $this->assertSame(2, $second->attempt_number);
        $this->assertSame(ThesisStatus::ProposalReexam, $thesis->fresh()->status);
        $this->assertModelExists($first);

        Carbon::setTestNow('2026-12-02 08:00:00');
        $this->verify($second);
        $this->assignExaminers($second);
        $this->actingAs($this->adminProdi)->post(route('prodi.exams.schedule', $second), $this->slot(['exam_date' => '2026-12-05']))->assertSessionHasNoErrors();
        $this->assertSame(ThesisStatus::ProposalReexam, $thesis->fresh()->status);

        Carbon::setTestNow('2026-12-05 12:00:00');
        foreach ([1 => $this->examinerOne, 2 => $this->examinerTwo] as $position => $examiner) {
            $this->actingAs($examiner->user)->post(route('dosen.assessment.store', $second), ['assessor_role' => "examiner_{$position}", 'scores' => [75, 75, 75]]);
        }
        foreach ([1 => $this->supervisorOne, 2 => $this->supervisorTwo] as $position => $supervisor) {
            $this->actingAs($supervisor->user)->post(route('dosen.assessment.store', $second), ['assessor_role' => "supervisor_{$position}", 'score' => 75]);
        }
        $this->decide($second->fresh(), ExamDecision::Passed);

        $this->assertSame(ThesisStatus::Research, $thesis->fresh()->status);
        $this->assertSame(ExamDecision::Failed, $first->fresh()->result->decision);
        $this->assertSame(ExamDecision::Passed, $second->fresh()->result->decision);
    }

    public function test_a_rejected_reexam_request_returns_to_resupervision(): void
    {
        $thesis = $this->thesisAt(ThesisStatus::ProposalReexam);
        $exam = $thesis->exams()->create(['exam_type_id' => ExamType::where('code', 'proposal')->value('id')]);
        $attempt = $exam->attempts()->create(['attempt_number' => 2, 'status' => ExamAttemptStatus::Requested]);

        $this->actingAs($this->adminProdi)->post(route('prodi.exams.verify', $attempt), ['action' => 'reject', 'notes' => 'Belum lengkap.'])->assertRedirect();

        $this->assertSame(ThesisStatus::ProposalResupervision, $thesis->fresh()->status);
    }

    public function test_the_thesis_exam_leads_to_final_validation_and_completion(): void
    {
        $thesis = $this->thesisAt(ThesisStatus::FinalSupervision);
        Setting::store($this->studyProgram->id, 'exam.thesis', ['supervisor_approval' => false, 'document_required' => false, 'administration_required' => false]);

        $attempt = $this->requestExam(ExamKind::Thesis);
        $this->actingAs($this->adminProdi)->post(route('prodi.exams.verify', $attempt), ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->assignExaminers($attempt);
        $this->schedule($attempt);
        $this->grade($attempt->fresh(), 85, 85);
        $this->decide($attempt->fresh(), ExamDecision::Passed);

        $this->assertSame(ThesisStatus::FinalValidation, $thesis->fresh()->status);

        $this->actingAs($this->adminProdi)->post(route('prodi.exams.final-validation', $thesis))->assertForbidden();
        $this->actingAs($this->kaprodi)->post(route('prodi.exams.final-validation', $thesis))->assertRedirect();

        $this->assertSame(ThesisStatus::Completed, $thesis->fresh()->status);
        $this->assertContains('Skripsi selesai', $this->studentUser->notifications()->get()->pluck('data.title')->all());
    }

    public function test_the_result_exam_uses_the_same_flow(): void
    {
        $thesis = $this->thesisAt(ThesisStatus::ResultSupervision);
        $this->approvedSessions($thesis, 10, 'result');
        $this->approveReadiness($thesis, 1, ExamKind::Result);

        $attempt = $this->requestExam(ExamKind::Result);
        $this->assertSame(ThesisStatus::ReadyResultExam, $thesis->fresh()->status);

        $this->actingAs($this->adminProdi)->post(route('prodi.exams.verify', $attempt), ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->assignExaminers($attempt);
        $this->schedule($attempt);
        $this->assertSame(ThesisStatus::ResultExam, $thesis->fresh()->status);

        $this->grade($attempt->fresh(), 80, 80);
        $this->decide($attempt->fresh(), ExamDecision::Passed);

        $this->assertSame(ThesisStatus::FinalSupervision, $thesis->fresh()->status);
    }

    public function test_pages_render_for_each_party(): void
    {
        $thesis = $this->eligibleThesis();
        $this->actingAs($this->studentUser->fresh())->get(route('mahasiswa.exams.proposal'))->assertOk()->assertSee('Persyaratan Ujian Proposal');

        $attempt = $this->scheduledAttempt($thesis);
        $this->grade($attempt, 80, 80, 'Bab II: Perbaiki teori');
        $this->decide($attempt, ExamDecision::Revision);

        $this->actingAs($this->studentUser->fresh())->get(route('mahasiswa.exams.proposal'))->assertOk()->assertSee('Lihat Berita Acara');
        $this->actingAs($this->adminProdi)->get(route('prodi.exams.proposal'))->assertOk()->assertSee(route('prodi.exams.attempt', $attempt));
        $this->actingAs($this->adminProdi)->get(route('prodi.exams.attempt', $attempt))->assertOk()->assertSee('Rekap Penilaian');
        $this->actingAs($this->examinerOne->user)->get(route('dosen.examinees'))->assertOk()->assertSee('Budi Mahasiswa');
        $this->actingAs($this->examinerOne->user)->get(route('dosen.assessment.show', $attempt))->assertOk();
        $this->actingAs($this->examinerOne->user)->get(route('dosen.revisions'))->assertOk()->assertSee('Perbaiki teori');
        $this->actingAs($this->studentUser->fresh())->get(route('minutes.show', $attempt))->assertOk()->assertSee('BERITA ACARA UJIAN PROPOSAL');
    }

    public function test_minutes_are_private_to_those_involved(): void
    {
        $attempt = $this->scheduledAttempt();
        $this->grade($attempt, 80, 80);
        $this->decide($attempt, ExamDecision::Passed);
        $other = $this->makeStudent('2297', 'Lain');

        $this->actingAs($other)->get(route('minutes.show', $attempt))->assertForbidden();
        $this->actingAs($this->kaprodi)->get(route('minutes.show', $attempt))->assertOk();
    }
}
