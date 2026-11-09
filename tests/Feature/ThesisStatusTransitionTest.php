<?php

namespace Tests\Feature;

use App\Enums\ThesisStatus;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\StudyProgram;
use App\Models\Thesis;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ThesisStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    private function thesisWithStatus(ThesisStatus $status): Thesis
    {
        $studyProgram = StudyProgram::create([
            'faculty_id' => Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT'])->id,
            'name' => 'Teknik Informatika', 'code' => 'TI',
        ]);
        $student = Student::create([
            'study_program_id' => $studyProgram->id,
            'nim' => '2201', 'name' => 'Budi', 'entry_year' => '2022', 'status' => 'aktif',
        ]);

        return $student->thesis()->create(['status' => $status]);
    }

    public function test_an_allowed_transition_updates_the_status_and_records_history(): void
    {
        $thesis = $this->thesisWithStatus(ThesisStatus::Draft);

        $thesis->transitionTo(ThesisStatus::Submitted, null, 'Diajukan.');

        $this->assertSame(ThesisStatus::Submitted, $thesis->fresh()->status);
        $this->assertDatabaseHas('thesis_status_histories', ['thesis_id' => $thesis->id, 'from_status' => 'DRAFT', 'to_status' => 'SUBMITTED', 'notes' => 'Diajukan.']);
    }

    /**
     * @return array<string, array{ThesisStatus, ThesisStatus}>
     */
    public static function skippedStageProvider(): array
    {
        return [
            'draft langsung disetujui' => [ThesisStatus::Draft, ThesisStatus::TitleApproved],
            'melewati Kaprodi' => [ThesisStatus::Submitted, ThesisStatus::TitleApproved],
            'bimbingan tanpa pembimbing' => [ThesisStatus::TitleApproved, ThesisStatus::Supervision],
            'tidak lulus langsung penelitian' => [ThesisStatus::ProposalFailed, ThesisStatus::Research],
            'tidak lulus langsung ujian ulang' => [ThesisStatus::ResultFailed, ThesisStatus::ResultReexam],
            'selesai dibuka lagi' => [ThesisStatus::Completed, ThesisStatus::Draft],
        ];
    }

    #[DataProvider('skippedStageProvider')]
    public function test_a_skipped_stage_is_rejected_and_changes_nothing(ThesisStatus $from, ThesisStatus $to): void
    {
        $thesis = $this->thesisWithStatus($from);

        try {
            $thesis->transitionTo($to, null);
            $this->fail('A skipped stage must throw.');
        } catch (DomainException) {
            $this->assertSame($from, $thesis->fresh()->status);
            $this->assertDatabaseCount('thesis_status_histories', 0);
        }
    }

    public function test_the_whole_workflow_can_reach_completed_through_revision_and_reexam(): void
    {
        $thesis = $this->thesisWithStatus(ThesisStatus::Draft);

        foreach ([
            ThesisStatus::Submitted, ThesisStatus::RevisionRequired, ThesisStatus::Submitted,
            ThesisStatus::TitleReview, ThesisStatus::TitleApproved, ThesisStatus::SupervisorAssigned,
            ThesisStatus::Supervision, ThesisStatus::ReadyProposalExam, ThesisStatus::ProposalExam,
            ThesisStatus::ProposalFailed, ThesisStatus::ProposalResupervision, ThesisStatus::ProposalReexam,
            ThesisStatus::ProposalPassed, ThesisStatus::Research, ThesisStatus::ResultSupervision,
            ThesisStatus::ReadyResultExam, ThesisStatus::ResultExam, ThesisStatus::ResultRevision,
            ThesisStatus::ResultPassed, ThesisStatus::FinalSupervision, ThesisStatus::ReadyThesisExam,
            ThesisStatus::ThesisExam, ThesisStatus::ThesisFailed, ThesisStatus::ThesisResupervision,
            ThesisStatus::ThesisReexam, ThesisStatus::ThesisPassed, ThesisStatus::FinalValidation,
            ThesisStatus::Completed,
        ] as $status) {
            $thesis->transitionTo($status, null);
        }

        $this->assertSame(ThesisStatus::Completed, $thesis->fresh()->status);
    }
}
