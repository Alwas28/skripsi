<?php

namespace Tests\Feature;

use App\Enums\DocumentReviewStatus;
use App\Enums\ExamKind;
use App\Enums\SupervisionStatus;
use App\Enums\ThesisStatus;
use App\Models\DocumentVersion;
use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\StudyProgram;
use App\Models\SupervisionSession;
use App\Models\Thesis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsThesisWorkflow;
use Tests\TestCase;

class SupervisionTest extends TestCase
{
    use BuildsThesisWorkflow;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->buildCast();
    }

    private function pdf(string $name = 'bab.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 100, 'application/pdf');
    }

    private function requestSession(?Lecturer $lecturer = null): TestResponse
    {
        return $this->actingAs($this->studentUser->fresh())->post(route('mahasiswa.supervision.store'), [
            'lecturer_id' => ($lecturer ?? $this->supervisorOne)->id,
            'chapter' => 'Bab II',
            'topic' => 'Landasan Teori',
            'session_date' => '2026-11-10',
            'file' => $this->pdf(),
        ]);
    }

    private function supervisionSession(Thesis $thesis, SupervisionStatus $status = SupervisionStatus::Requested): SupervisionSession
    {
        $session = $thesis->supervisionSessions()->create([
            'lecturer_id' => $this->supervisorOne->id, 'phase' => 'proposal', 'chapter' => 'Bab I',
            'topic' => 'Pendahuluan', 'session_date' => '2026-11-10', 'status' => $status,
        ]);
        $document = $thesis->documents()->create(['stage' => 'supervision', 'name' => "Bimbingan {$session->id}"]);
        $session->files()->create(['document_version_id' => $document->addVersion($this->pdf(), $this->studentUser)->id]);

        return $session;
    }

    /**
     * @return array<string, array{ThesisStatus, ThesisStatus, ExamKind}>
     */
    public static function firstSessionProvider(): array
    {
        return [
            'mulai bimbingan proposal' => [ThesisStatus::SupervisorAssigned, ThesisStatus::Supervision, ExamKind::Proposal],
            'lanjut bimbingan proposal' => [ThesisStatus::Supervision, ThesisStatus::Supervision, ExamKind::Proposal],
            'bimbingan ulang proposal' => [ThesisStatus::ProposalFailed, ThesisStatus::ProposalResupervision, ExamKind::Proposal],
            'mulai bimbingan hasil' => [ThesisStatus::Research, ThesisStatus::ResultSupervision, ExamKind::Result],
            'bimbingan ulang skripsi' => [ThesisStatus::ThesisFailed, ThesisStatus::ThesisResupervision, ExamKind::Thesis],
        ];
    }

    #[DataProvider('firstSessionProvider')]
    public function test_a_session_moves_the_thesis_into_its_supervision_stage(ThesisStatus $from, ThesisStatus $to, ExamKind $phase): void
    {
        $thesis = $this->thesisAt($from);

        $this->requestSession()->assertRedirect(route('mahasiswa.supervision'));

        $this->assertSame($to, $thesis->fresh()->status);
        $session = $thesis->supervisionSessions()->firstOrFail();
        $this->assertSame($phase, $session->phase);
        $this->assertSame(SupervisionStatus::Requested, $session->status);
        Storage::disk('local')->assertExists($session->files()->firstOrFail()->documentVersion->file_path);
        $this->assertSame(1, $this->supervisorOne->user->notifications()->count());
    }

    #[DataProvider('closedStatusProvider')]
    public function test_a_session_cannot_be_requested_outside_a_supervision_stage(ThesisStatus $status): void
    {
        $this->thesisAt($status);

        $this->requestSession()->assertForbidden();
    }

    /**
     * @return array<string, array{ThesisStatus}>
     */
    public static function closedStatusProvider(): array
    {
        return [
            'belum ada pembimbing' => [ThesisStatus::TitleApproved],
            'menunggu ujian' => [ThesisStatus::ReadyProposalExam],
            'sedang ujian' => [ThesisStatus::ProposalExam],
            'selesai' => [ThesisStatus::Completed],
        ];
    }

    public function test_only_the_students_own_supervisors_can_be_chosen(): void
    {
        $this->thesisAt(ThesisStatus::Supervision);

        $this->requestSession($this->examinerOne)->assertSessionHasErrors('lecturer_id');
    }

    public function test_a_supervisor_can_approve_a_session(): void
    {
        $session = $this->supervisionSession($this->thesisAt(ThesisStatus::Supervision));

        $this->actingAs($this->supervisorOne->user)->get(route('dosen.supervision.show', $session))->assertOk()->assertSee('Pendahuluan');
        $this->actingAs($this->supervisorOne->user)->post(route('dosen.supervision.review', $session), ['decision' => 'approved'])
            ->assertRedirect(route('dosen.supervision.show', $session));

        $session->refresh();
        $this->assertSame(SupervisionStatus::Approved, $session->status);
        $this->assertNotNull($session->approved_at);
        $this->assertSame(['Bimbingan disetujui'], $this->studentUser->notifications()->get()->pluck('data.title')->all());
    }

    public function test_a_revision_request_needs_a_note(): void
    {
        $session = $this->supervisionSession($this->thesisAt(ThesisStatus::Supervision));

        $this->actingAs($this->supervisorOne->user)->post(route('dosen.supervision.review', $session), ['decision' => 'revision_required'])
            ->assertSessionHasErrors('note');

        $this->assertSame(SupervisionStatus::Requested, $session->fresh()->status);
    }

    public function test_another_lecturer_cannot_open_or_review_the_session(): void
    {
        $session = $this->supervisionSession($this->thesisAt(ThesisStatus::Supervision));

        $this->actingAs($this->supervisorTwo->user)->get(route('dosen.supervision.show', $session))->assertForbidden();
        $this->actingAs($this->supervisorTwo->user)->post(route('dosen.supervision.review', $session), ['decision' => 'approved'])->assertForbidden();
    }

    public function test_an_approved_session_cannot_be_reviewed_again(): void
    {
        $session = $this->supervisionSession($this->thesisAt(ThesisStatus::Supervision), SupervisionStatus::Approved);

        $this->actingAs($this->supervisorOne->user)->post(route('dosen.supervision.review', $session), ['decision' => 'revision_required', 'note' => 'x'])->assertForbidden();
    }

    public function test_a_student_can_send_a_revision_as_a_new_document_version(): void
    {
        $session = $this->supervisionSession($this->thesisAt(ThesisStatus::Supervision), SupervisionStatus::RevisionRequired);

        $this->actingAs($this->studentUser->fresh())->post(route('mahasiswa.supervision.revise', $session), ['file' => $this->pdf('revisi.pdf'), 'notes' => 'Sudah diperbaiki.'])
            ->assertRedirect(route('mahasiswa.supervision'));

        $this->assertSame(SupervisionStatus::Resubmitted, $session->fresh()->status);
        $this->assertSame(['bab.pdf', 'revisi.pdf'], DocumentVersion::orderBy('version_number')->pluck('original_name')->all());
        $this->assertSame('revisi.pdf', $session->revisions()->firstOrFail()->documentVersion->original_name);
    }

    public function test_a_revision_is_only_accepted_when_requested_and_by_the_owner(): void
    {
        $thesis = $this->thesisAt(ThesisStatus::Supervision);
        $requested = $this->supervisionSession($thesis);
        $needsRevision = $this->supervisionSession($thesis, SupervisionStatus::RevisionRequired);
        $other = $this->makeStudent('2202', 'Lain');
        $this->thesisAt(ThesisStatus::Supervision, $other);

        $this->actingAs($this->studentUser->fresh())->post(route('mahasiswa.supervision.revise', $requested), ['file' => $this->pdf()])->assertForbidden();
        $this->actingAs($other)->post(route('mahasiswa.supervision.revise', $needsRevision), ['file' => $this->pdf()])->assertForbidden();
    }

    public function test_a_supervisor_can_review_the_active_document_only(): void
    {
        $thesis = $this->thesisAt(ThesisStatus::Supervision);
        $document = $thesis->documents()->create(['stage' => 'proposal', 'name' => 'Proposal']);
        $old = $document->addVersion($this->pdf('v1.pdf'), $this->studentUser);
        $active = $document->addVersion($this->pdf('v2.pdf'), $this->studentUser);
        $supervisor = $this->supervisorOne->user;

        $this->actingAs($supervisor)->get(route('dosen.document-review'))->assertOk()->assertSee(route('dosen.document-review.show', $thesis));
        $this->actingAs($supervisor)->get(route('dosen.document-review.show', $thesis))->assertOk()->assertSee('v2.pdf');
        $this->actingAs($supervisor)->get(route('dosen.document-review.download', $old))->assertOk();
        $this->actingAs($supervisor)->post(route('dosen.document-review.review', $active), ['review_status' => 'needs_revision'])->assertSessionHasErrors('review_notes');
        $this->actingAs($supervisor)->post(route('dosen.document-review.review', $active), ['review_status' => 'approved'])->assertRedirect();
        $this->actingAs($supervisor)->post(route('dosen.document-review.review', $old), ['review_status' => 'approved'])->assertForbidden();

        $this->assertSame(DocumentReviewStatus::Approved->value, $active->fresh()->review_status);
        $this->assertSame($supervisor->id, $active->fresh()->reviewed_by);
    }

    public function test_a_non_supervisor_cannot_read_the_documents(): void
    {
        $thesis = $this->thesisAt(ThesisStatus::Supervision);
        $version = $thesis->documents()->create(['stage' => 'proposal', 'name' => 'Proposal'])->addVersion($this->pdf(), $this->studentUser);
        $outsider = $this->makeLecturer('0099', 'Luar', 'dosen_pembimbing')->user;

        $this->actingAs($outsider)->get(route('dosen.document-review.show', $thesis))->assertForbidden();
        $this->actingAs($outsider)->get(route('dosen.document-review.download', $version))->assertForbidden();
        $this->actingAs($outsider)->post(route('dosen.document-review.review', $version), ['review_status' => 'approved'])->assertForbidden();
    }

    public function test_requirement_checks_follow_sessions_approvals_and_documents(): void
    {
        $thesis = $this->thesisAt(ThesisStatus::Supervision);
        $met = fn () => array_column($thesis->fresh()->examRequirementChecks(ExamKind::Proposal), 'met');

        $this->assertSame([false, false, false], $met());

        $this->approvedSessions($thesis, 5);
        $this->assertSame([true, false, false], $met());

        foreach ([$this->supervisorOne, $this->supervisorTwo] as $supervisor) {
            $this->actingAs($supervisor->user)->post(route('dosen.approvals.approve', [$thesis, 'proposal']))->assertRedirect(route('dosen.approvals'));
        }
        $this->assertSame([true, true, false], $met());

        $thesis->documents()->create(['stage' => 'proposal', 'name' => 'Proposal'])->addVersion($this->pdf(), $this->studentUser);
        $this->assertSame([true, true, true], $met());
    }

    public function test_only_a_supervisor_at_the_right_stage_can_approve_readiness(): void
    {
        $thesis = $this->thesisAt(ThesisStatus::Supervision);

        $this->actingAs($this->makeLecturer('0099', 'Luar', 'dosen_pembimbing')->user)->post(route('dosen.approvals.approve', [$thesis, 'proposal']))->assertForbidden();
        $this->actingAs($this->supervisorOne->user)->post(route('dosen.approvals.approve', [$thesis, 'result']))->assertForbidden();
    }

    public function test_the_approvals_page_lists_students_waiting_for_sign_off(): void
    {
        $this->thesisAt(ThesisStatus::Supervision);

        $this->actingAs($this->supervisorOne->user)->get(route('dosen.approvals'))->assertOk()->assertSee('Budi Mahasiswa')->assertSee('Ujian Proposal');
    }

    public function test_prodi_only_sees_supervision_of_their_own_program(): void
    {
        $this->supervisionSession($this->thesisAt(ThesisStatus::Supervision));
        $otherProgram = StudyProgram::create(['faculty_id' => Faculty::value('id'), 'name' => 'Sistem Informasi', 'code' => 'SI']);
        $outsider = User::factory()->withRoles('kaprodi')->create(['study_program_id' => $otherProgram->id]);

        $this->actingAs($this->kaprodi)->get(route('prodi.supervision'))->assertOk()->assertSee('Budi Mahasiswa');
        $this->actingAs($outsider)->get(route('prodi.supervision'))->assertOk()->assertDontSee('Budi Mahasiswa');
    }

    public function test_the_student_page_shows_sessions(): void
    {
        $this->supervisionSession($this->thesisAt(ThesisStatus::Supervision));

        $this->actingAs($this->studentUser->fresh())->get(route('mahasiswa.supervision'))->assertOk()->assertSee('Pendahuluan');
    }
}
