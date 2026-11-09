<?php

namespace Tests\Feature;

use App\Enums\ThesisStatus;
use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\StudyProgram;
use App\Models\Thesis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ThesisTitleSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function mahasiswa(): User
    {
        $studyProgram = StudyProgram::create([
            'faculty_id' => Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT'])->id,
            'name' => 'Teknik Informatika', 'code' => 'TI',
        ]);

        $user = User::factory()->withRoles('mahasiswa')->create();

        Student::create([
            'user_id' => $user->id, 'study_program_id' => $studyProgram->id,
            'academic_advisor_id' => $this->lecturerUser('0011')->lecturerRecord->id,
            'nim' => '2201', 'name' => 'Budi Santoso', 'entry_year' => '2022', 'status' => 'aktif',
        ]);

        return $user;
    }

    private function lecturerUser(string $nidn, string ...$roleSlugs): User
    {
        $user = User::factory()->withRoles(...($roleSlugs ?: ['dosen_pa']))->create();

        Lecturer::create(['user_id' => $user->id, 'nidn' => $nidn, 'name' => "Dosen {$nidn}", 'is_active' => true]);

        return $user;
    }

    private function advisorOf(User $mahasiswa): User
    {
        return $mahasiswa->studentRecord->academicAdvisor->user;
    }

    private function admin(): User
    {
        return User::factory()->withRoles('admin_prodi')->create(['study_program_id' => StudyProgram::value('id')]);
    }

    private function kaprodi(): User
    {
        return User::factory()->withRoles('kaprodi')->create(['study_program_id' => StudyProgram::value('id')]);
    }

    private function submitTitle(User $mahasiswa): Thesis
    {
        Storage::fake('local');
        $this->actingAs($mahasiswa)->get(route('mahasiswa.title-submission'));
        $this->actingAs($mahasiswa)->put(route('mahasiswa.title-submission.update'), [
            ...$this->validPayload(),
            'proposal' => UploadedFile::fake()->create('proposal.pdf', 500),
            'action' => 'submit',
        ]);

        return Thesis::firstOrFail();
    }

    private function titleApprovedByAdvisor(User $mahasiswa): Thesis
    {
        $thesis = $this->submitTitle($mahasiswa);
        $this->actingAs($this->advisorOf($mahasiswa))->post(route('dosen.advisor-titles.approve', $thesis));

        return $thesis->refresh();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Sistem Informasi Monitoring Tugas Akhir',
            'research_field' => 'Rekayasa Perangkat Lunak',
            'background' => 'Latar belakang...',
            'problem_statement' => 'Rumusan masalah...',
            'objectives' => 'Tujuan...',
            'method' => 'Metode...',
            'research_object' => 'Objek...',
            'initial_references' => 'Referensi...',
        ], $overrides);
    }

    public function test_a_user_without_a_linked_student_record_sees_the_not_linked_page(): void
    {
        $user = User::factory()->withRoles('mahasiswa')->create();

        $this->actingAs($user)->get(route('mahasiswa.title-submission'))
            ->assertOk()
            ->assertSee('Belum Terhubung');
    }

    public function test_visiting_the_page_auto_provisions_a_draft_thesis(): void
    {
        $mahasiswa = $this->mahasiswa();

        $this->actingAs($mahasiswa)->get(route('mahasiswa.title-submission'))->assertOk();

        $this->assertDatabaseHas('theses', ['student_id' => $mahasiswa->studentRecord->id, 'status' => 'DRAFT']);
        $this->assertDatabaseHas('thesis_titles', ['title' => '']);
    }

    public function test_saving_as_draft_keeps_the_thesis_editable(): void
    {
        $mahasiswa = $this->mahasiswa();
        $this->actingAs($mahasiswa)->get(route('mahasiswa.title-submission'));

        $this->actingAs($mahasiswa)->put(route('mahasiswa.title-submission.update'), [
            ...$this->validPayload(),
            'action' => 'draft',
        ])->assertRedirect(route('mahasiswa.title-submission'));

        $thesis = Thesis::firstOrFail();
        $this->assertSame(ThesisStatus::Draft, $thesis->status);
        $this->assertSame('Sistem Informasi Monitoring Tugas Akhir', $thesis->title->title);
        $this->assertNull($thesis->title->submitted_at);
    }

    public function test_submitting_with_all_fields_and_a_file_moves_status_to_submitted(): void
    {
        Storage::fake('local');
        $mahasiswa = $this->mahasiswa();
        $this->actingAs($mahasiswa)->get(route('mahasiswa.title-submission'));

        $this->actingAs($mahasiswa)->put(route('mahasiswa.title-submission.update'), [
            ...$this->validPayload(),
            'proposal' => UploadedFile::fake()->create('proposal.pdf', 500, 'application/pdf'),
            'action' => 'submit',
        ])->assertRedirect(route('mahasiswa.title-submission'));

        $thesis = Thesis::firstOrFail();
        $this->assertSame(ThesisStatus::Submitted, $thesis->status);
        $this->assertNotNull($thesis->title->submitted_at);
        Storage::disk('local')->assertExists($thesis->title->proposal_path);
        $this->assertDatabaseHas('thesis_status_histories', ['thesis_id' => $thesis->id, 'to_status' => 'SUBMITTED']);
    }

    public function test_submitting_without_a_proposal_file_is_rejected(): void
    {
        $mahasiswa = $this->mahasiswa();
        $this->actingAs($mahasiswa)->get(route('mahasiswa.title-submission'));

        $this->actingAs($mahasiswa)->put(route('mahasiswa.title-submission.update'), [
            ...$this->validPayload(),
            'action' => 'submit',
        ])->assertSessionHasErrors('proposal');

        $this->assertSame(ThesisStatus::Draft, Thesis::firstOrFail()->status);
    }

    public function test_a_submitted_thesis_can_no_longer_be_updated_by_the_student(): void
    {
        Storage::fake('local');
        $mahasiswa = $this->mahasiswa();
        $this->actingAs($mahasiswa)->get(route('mahasiswa.title-submission'));
        $this->actingAs($mahasiswa)->put(route('mahasiswa.title-submission.update'), [
            ...$this->validPayload(),
            'proposal' => UploadedFile::fake()->create('proposal.pdf', 500),
            'action' => 'submit',
        ]);

        $this->actingAs($mahasiswa)->put(route('mahasiswa.title-submission.update'), [
            ...$this->validPayload(['title' => 'Judul Diubah']),
            'action' => 'draft',
        ])->assertForbidden();

        $this->assertSame('Sistem Informasi Monitoring Tugas Akhir', Thesis::firstOrFail()->title->title);
    }

    public function test_submitting_without_an_academic_advisor_is_rejected(): void
    {
        Storage::fake('local');
        $mahasiswa = $this->mahasiswa();
        $mahasiswa->studentRecord->update(['academic_advisor_id' => null]);
        $this->actingAs($mahasiswa)->get(route('mahasiswa.title-submission'));

        $this->actingAs($mahasiswa)->put(route('mahasiswa.title-submission.update'), [
            ...$this->validPayload(),
            'proposal' => UploadedFile::fake()->create('proposal.pdf', 500),
            'action' => 'submit',
        ])->assertSessionHasErrors('advisor');

        $this->assertSame(ThesisStatus::Draft, Thesis::firstOrFail()->status);
    }

    public function test_the_advisor_approval_forwards_the_title_to_the_kaprodi(): void
    {
        $mahasiswa = $this->mahasiswa();
        $thesis = $this->submitTitle($mahasiswa);
        $advisor = $this->advisorOf($mahasiswa);

        $this->actingAs($advisor)->get(route('dosen.advisor-titles.index'))->assertOk()->assertSee('Sistem Informasi Monitoring Tugas Akhir');
        $this->actingAs($advisor)->get(route('dosen.advisor-titles.show', $thesis))->assertOk();

        $this->actingAs($advisor)->post(route('dosen.advisor-titles.approve', $thesis))
            ->assertRedirect(route('dosen.advisor-titles.index'));

        $thesis->refresh();
        $this->assertSame(ThesisStatus::TitleReview, $thesis->status);
        $this->assertSame($advisor->id, $thesis->title->reviewed_by);
        $this->assertDatabaseHas('thesis_status_histories', ['thesis_id' => $thesis->id, 'to_status' => 'TITLE_REVIEW', 'changed_by' => $advisor->id]);
    }

    public function test_the_kaprodi_can_approve_after_the_advisor(): void
    {
        $thesis = $this->titleApprovedByAdvisor($this->mahasiswa());
        $kaprodi = $this->kaprodi();

        $this->actingAs($kaprodi)->get(route('prodi.title-submissions.show', $thesis))->assertOk()->assertSee(route('prodi.title-submissions.approve', $thesis));

        $this->actingAs($kaprodi)->post(route('prodi.title-submissions.approve', $thesis))
            ->assertRedirect(route('prodi.title-submissions.index'));

        $thesis->refresh();
        $this->assertSame(ThesisStatus::TitleApproved, $thesis->status);
        $this->assertSame($kaprodi->id, $thesis->title->reviewed_by);
        $this->assertNotNull($thesis->title->reviewed_at);
    }

    public function test_the_kaprodi_cannot_approve_before_the_advisor(): void
    {
        $thesis = $this->submitTitle($this->mahasiswa());

        $this->actingAs($this->kaprodi())->post(route('prodi.title-submissions.approve', $thesis))->assertForbidden();

        $this->assertSame(ThesisStatus::Submitted, $thesis->fresh()->status);
    }

    public function test_the_advisor_cannot_approve_twice(): void
    {
        $mahasiswa = $this->mahasiswa();
        $thesis = $this->titleApprovedByAdvisor($mahasiswa);

        $this->actingAs($this->advisorOf($mahasiswa))->post(route('dosen.advisor-titles.approve', $thesis))->assertForbidden();

        $this->assertSame(ThesisStatus::TitleReview, $thesis->fresh()->status);
    }

    public function test_another_lecturer_cannot_access_or_approve_the_title(): void
    {
        $thesis = $this->submitTitle($this->mahasiswa());
        $otherLecturer = $this->lecturerUser('0022');

        $this->actingAs($otherLecturer)->get(route('dosen.advisor-titles.index'))->assertOk()->assertDontSee('Sistem Informasi Monitoring Tugas Akhir');
        $this->actingAs($otherLecturer)->get(route('dosen.advisor-titles.show', $thesis))->assertForbidden();
        $this->actingAs($otherLecturer)->get(route('dosen.advisor-titles.download', $thesis))->assertForbidden();
        $this->actingAs($otherLecturer)->post(route('dosen.advisor-titles.approve', $thesis))->assertForbidden();

        $this->assertSame(ThesisStatus::Submitted, $thesis->fresh()->status);
    }

    public function test_a_user_without_a_lecturer_record_sees_the_not_linked_page(): void
    {
        $dosen = User::factory()->withRoles('dosen_pa')->create();

        $this->actingAs($dosen)->get(route('dosen.advisor-titles.index'))->assertOk()->assertSee('Belum Terhubung');
    }

    public function test_a_draft_title_is_hidden_from_the_advisor(): void
    {
        $mahasiswa = $this->mahasiswa();
        $this->actingAs($mahasiswa)->get(route('mahasiswa.title-submission'));

        $this->actingAs($this->advisorOf($mahasiswa))
            ->get(route('dosen.advisor-titles.show', Thesis::firstOrFail()))
            ->assertForbidden();
    }

    public function test_a_kaprodi_who_is_also_the_advisor_approves_both_steps(): void
    {
        $mahasiswa = $this->mahasiswa();
        $kaprodiAdvisor = $this->lecturerUser('0033', 'kaprodi', 'dosen_pa');
        $kaprodiAdvisor->update(['study_program_id' => StudyProgram::value('id')]);
        $mahasiswa->studentRecord->update(['academic_advisor_id' => $kaprodiAdvisor->lecturerRecord->id]);
        $thesis = $this->submitTitle($mahasiswa);

        $this->actingAs($kaprodiAdvisor)->get(route('dosen.advisor-titles.index'))->assertOk()->assertSee('Persetujuan Judul');
        $this->actingAs($kaprodiAdvisor)->post(route('dosen.advisor-titles.approve', $thesis));
        $this->actingAs($kaprodiAdvisor)->post(route('prodi.title-submissions.approve', $thesis));

        $this->assertSame(ThesisStatus::TitleApproved, $thesis->fresh()->status);
    }

    public function test_a_kaprodi_from_another_study_program_cannot_see_or_act_on_the_title(): void
    {
        $thesis = $this->titleApprovedByAdvisor($this->mahasiswa());
        $otherProgram = StudyProgram::create([
            'faculty_id' => Faculty::value('id'), 'name' => 'Sistem Informasi', 'code' => 'SI',
        ]);
        $otherKaprodi = User::factory()->withRoles('kaprodi')->create(['study_program_id' => $otherProgram->id]);

        $this->actingAs($otherKaprodi)->get(route('prodi.title-submissions.index'))->assertOk()->assertDontSee(route('prodi.title-submissions.show', $thesis));
        $this->actingAs($otherKaprodi)->get(route('prodi.title-submissions.show', $thesis))->assertForbidden();
        $this->actingAs($otherKaprodi)->get(route('prodi.title-submissions.download', $thesis))->assertForbidden();
        $this->actingAs($otherKaprodi)->post(route('prodi.title-submissions.approve', $thesis))->assertForbidden();
        $this->actingAs($otherKaprodi)->post(route('prodi.title-submissions.request-revision', $thesis), ['review_notes' => 'Catatan.'])->assertForbidden();

        $this->assertSame(ThesisStatus::TitleReview, $thesis->fresh()->status);
    }

    public function test_prodi_staff_without_a_study_program_see_no_titles(): void
    {
        $thesis = $this->titleApprovedByAdvisor($this->mahasiswa());
        $kaprodi = User::factory()->withRoles('kaprodi')->create();

        $this->actingAs($kaprodi)->get(route('prodi.title-submissions.index'))
            ->assertOk()
            ->assertSee('belum terhubung ke program studi')
            ->assertDontSee(route('prodi.title-submissions.show', $thesis));
        $this->actingAs($kaprodi)->post(route('prodi.title-submissions.approve', $thesis))->assertForbidden();
    }

    public function test_a_super_admin_sees_titles_of_every_study_program(): void
    {
        $this->titleApprovedByAdvisor($this->mahasiswa());
        $administrator = User::factory()->withRoles('administrator')->create();

        $this->actingAs($administrator)->get(route('prodi.title-submissions.index'))->assertOk()->assertSee('Sistem Informasi Monitoring Tugas Akhir');
    }

    public function test_admin_prodi_can_view_but_not_approve(): void
    {
        $thesis = $this->titleApprovedByAdvisor($this->mahasiswa());
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('prodi.title-submissions.index'))->assertOk()->assertSee('Sistem Informasi Monitoring Tugas Akhir');
        $this->actingAs($admin)->get(route('prodi.title-submissions.show', $thesis))->assertOk()->assertDontSee(route('prodi.title-submissions.approve', $thesis));
        $this->actingAs($admin)->post(route('prodi.title-submissions.approve', $thesis))->assertForbidden();
        $this->actingAs($admin)->post(route('prodi.title-submissions.request-revision', $thesis), ['review_notes' => 'Catatan.'])->assertForbidden();

        $this->assertSame(ThesisStatus::TitleReview, $thesis->fresh()->status);
    }

    public function test_the_advisor_can_request_revision(): void
    {
        $mahasiswa = $this->mahasiswa();
        $thesis = $this->submitTitle($mahasiswa);

        $this->actingAs($this->advisorOf($mahasiswa))->post(route('dosen.advisor-titles.request-revision', $thesis), [
            'review_notes' => 'Persempit objek penelitian.',
        ])->assertRedirect(route('dosen.advisor-titles.index'));

        $thesis->refresh();
        $this->assertSame(ThesisStatus::RevisionRequired, $thesis->status);
        $this->assertSame('Persempit objek penelitian.', $thesis->title->review_notes);
    }

    public function test_kaprodi_can_request_revision_with_notes_and_the_resubmission_returns_to_the_advisor(): void
    {
        $mahasiswa = $this->mahasiswa();
        $thesis = $this->titleApprovedByAdvisor($mahasiswa);

        $this->actingAs($this->kaprodi())->post(route('prodi.title-submissions.request-revision', $thesis), [
            'review_notes' => 'Perbaiki latar belakang.',
        ])->assertRedirect(route('prodi.title-submissions.index'));

        $thesis->refresh();
        $this->assertSame(ThesisStatus::RevisionRequired, $thesis->status);
        $this->assertSame('Perbaiki latar belakang.', $thesis->title->review_notes);

        // student sees it as editable again and can resubmit
        $this->actingAs($mahasiswa)->get(route('mahasiswa.title-submission'))->assertOk()->assertSee('Perbaiki latar belakang.');

        $this->actingAs($mahasiswa)->put(route('mahasiswa.title-submission.update'), [
            ...$this->validPayload(['background' => 'Latar belakang yang sudah diperbaiki.']),
            'action' => 'submit',
        ])->assertRedirect(route('mahasiswa.title-submission'));

        $thesis->refresh();
        $this->assertSame(ThesisStatus::Submitted, $thesis->status);
        $this->assertNull($thesis->title->review_notes);
        $this->assertSame('Latar belakang yang sudah diperbaiki.', $thesis->title->background);
    }

    public function test_requesting_revision_without_notes_is_rejected(): void
    {
        $mahasiswa = $this->mahasiswa();
        $thesis = $this->submitTitle($mahasiswa);

        $this->actingAs($this->advisorOf($mahasiswa))->post(route('dosen.advisor-titles.request-revision', $thesis), [])
            ->assertSessionHasErrors('review_notes');

        $this->assertSame(ThesisStatus::Submitted, $thesis->fresh()->status);

        $this->actingAs($this->advisorOf($mahasiswa))->post(route('dosen.advisor-titles.approve', $thesis));

        $this->actingAs($this->kaprodi())->post(route('prodi.title-submissions.request-revision', $thesis), [])
            ->assertSessionHasErrors('review_notes');

        $this->assertSame(ThesisStatus::TitleReview, $thesis->fresh()->status);
    }

    public function test_an_already_approved_title_cannot_be_approved_again(): void
    {
        $thesis = $this->titleApprovedByAdvisor($this->mahasiswa());
        $kaprodi = $this->kaprodi();
        $this->actingAs($kaprodi)->post(route('prodi.title-submissions.approve', $thesis));

        $this->actingAs($kaprodi)->post(route('prodi.title-submissions.approve', $thesis))->assertForbidden();
    }

    public function test_dosen_cannot_view_title_submissions(): void
    {
        $dosen = User::factory()->withRoles('dosen_pa')->create();

        $this->actingAs($dosen)->get(route('prodi.title-submissions.index'))->assertForbidden();
    }

    public function test_student_can_download_their_own_proposal(): void
    {
        Storage::fake('local');
        $mahasiswa = $this->mahasiswa();
        $this->actingAs($mahasiswa)->get(route('mahasiswa.title-submission'));
        $this->actingAs($mahasiswa)->put(route('mahasiswa.title-submission.update'), [
            ...$this->validPayload(),
            'proposal' => UploadedFile::fake()->create('proposal.pdf', 500),
            'action' => 'submit',
        ]);

        $this->actingAs($mahasiswa)->get(route('mahasiswa.title-submission.download'))->assertOk();
    }
}
