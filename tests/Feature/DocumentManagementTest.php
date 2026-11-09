<?php

namespace Tests\Feature;

use App\Enums\DocumentStage;
use App\Enums\ThesisStatus;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\StudyProgram;
use App\Models\Thesis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function mahasiswa(string $nim = '2201'): User
    {
        $studyProgram = StudyProgram::first() ?? StudyProgram::create([
            'faculty_id' => Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT'])->id,
            'name' => 'Teknik Informatika', 'code' => 'TI',
        ]);
        $user = User::factory()->withRoles('mahasiswa')->create();

        Student::create([
            'user_id' => $user->id, 'study_program_id' => $studyProgram->id,
            'nim' => $nim, 'name' => "Mahasiswa {$nim}", 'entry_year' => '2022', 'status' => 'aktif',
        ]);

        return $user;
    }

    private function thesisOf(User $mahasiswa, ThesisStatus $status = ThesisStatus::SupervisorAssigned): Thesis
    {
        return $mahasiswa->studentRecord->thesis()->create(['status' => $status]);
    }

    private function pdf(string $name = 'proposal.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 200, 'application/pdf');
    }

    private function upload(User $mahasiswa, string $stage = 'proposal', string $name = 'Proposal Bab I'): TestResponse
    {
        return $this->actingAs($mahasiswa)->post(route('mahasiswa.documents.store'), [
            'stage' => $stage,
            'name' => $name,
            'file' => $this->pdf(),
        ]);
    }

    public function test_a_student_can_upload_a_document_to_an_open_stage(): void
    {
        $mahasiswa = $this->mahasiswa();
        $thesis = $this->thesisOf($mahasiswa);

        $this->upload($mahasiswa)->assertRedirect(route('mahasiswa.documents'));

        $document = $thesis->documents()->firstOrFail();
        $version = $document->versions()->firstOrFail();
        $this->assertSame(DocumentStage::Proposal, $document->stage);
        $this->assertSame(1, $version->version_number);
        $this->assertTrue($version->is_active);
        $this->assertSame($mahasiswa->id, $version->uploaded_by);
        $this->assertSame('proposal.pdf', $version->original_name);
        Storage::disk('local')->assertExists($version->file_path);
    }

    public function test_a_new_version_becomes_active_and_keeps_the_old_file(): void
    {
        $mahasiswa = $this->mahasiswa();
        $thesis = $this->thesisOf($mahasiswa);
        $this->upload($mahasiswa);
        $document = $thesis->documents()->firstOrFail();

        $this->actingAs($mahasiswa)->post(route('mahasiswa.documents.versions.store', $document), ['file' => $this->pdf('revisi.pdf')])
            ->assertRedirect(route('mahasiswa.documents'));

        $versions = $document->versions()->orderBy('version_number')->get();
        $this->assertSame([1, 2], $versions->pluck('version_number')->all());
        $this->assertFalse($versions[0]->is_active);
        $this->assertTrue($versions[1]->is_active);
        Storage::disk('local')->assertExists($versions[0]->file_path);
        Storage::disk('local')->assertExists($versions[1]->file_path);
    }

    public function test_uploading_under_an_existing_name_adds_a_version_instead_of_a_duplicate(): void
    {
        $mahasiswa = $this->mahasiswa();
        $thesis = $this->thesisOf($mahasiswa);

        $this->upload($mahasiswa);
        $this->upload($mahasiswa);

        $this->assertSame(1, $thesis->documents()->count());
        $this->assertSame(2, $thesis->documents()->firstOrFail()->versions()->count());
    }

    /**
     * @return array<string, array{ThesisStatus, string}>
     */
    public static function closedStageProvider(): array
    {
        return [
            'proposal sebelum ada pembimbing' => [ThesisStatus::TitleApproved, 'proposal'],
            'penelitian sebelum lulus proposal' => [ThesisStatus::Supervision, 'research'],
            'skripsi final terlalu dini' => [ThesisStatus::Research, 'final_thesis'],
            'berita acara tidak diunggah mahasiswa' => [ThesisStatus::ThesisPassed, 'minutes'],
            'pengajuan judul saat direview PA' => [ThesisStatus::Submitted, 'title_submission'],
            'pengajuan judul saat direview Kaprodi' => [ThesisStatus::TitleReview, 'title_submission'],
            'pengajuan judul setelah disetujui' => [ThesisStatus::TitleApproved, 'title_submission'],
            'skripsi sudah selesai' => [ThesisStatus::Completed, 'revision'],
        ];
    }

    #[DataProvider('closedStageProvider')]
    public function test_a_closed_stage_rejects_uploads(ThesisStatus $status, string $stage): void
    {
        $mahasiswa = $this->mahasiswa();
        $thesis = $this->thesisOf($mahasiswa, $status);

        $this->upload($mahasiswa, $stage)->assertSessionHasErrors('stage');

        $this->assertSame(0, $thesis->documents()->count());
    }

    /**
     * @return array<string, array{ThesisStatus}>
     */
    public static function editableTitleStatusProvider(): array
    {
        return [
            'draft' => [ThesisStatus::Draft],
            'perlu revisi' => [ThesisStatus::RevisionRequired],
        ];
    }

    #[DataProvider('editableTitleStatusProvider')]
    public function test_documents_can_be_uploaded_from_the_title_submission_stage(ThesisStatus $status): void
    {
        $mahasiswa = $this->mahasiswa();
        $thesis = $this->thesisOf($mahasiswa, $status);

        $this->upload($mahasiswa, 'title_submission', 'Transkrip Nilai')->assertRedirect(route('mahasiswa.documents'));

        $this->assertSame(DocumentStage::TitleSubmission, $thesis->documents()->firstOrFail()->stage);
    }

    public function test_the_title_stage_is_the_first_upload_option_for_a_new_thesis(): void
    {
        $mahasiswa = $this->mahasiswa();
        $this->thesisOf($mahasiswa, ThesisStatus::Draft);

        $openStages = $this->actingAs($mahasiswa)->get(route('mahasiswa.documents'))->assertOk()->viewData('openStages');

        $this->assertSame([DocumentStage::TitleSubmission], $openStages);
    }

    public function test_a_new_proposal_version_from_the_documents_page_reaches_the_title_review(): void
    {
        $mahasiswa = $this->mahasiswa();
        $thesis = $this->thesisOf($mahasiswa, ThesisStatus::Draft);
        $thesis->title()->create(['title' => 'Judul']);

        $this->upload($mahasiswa, 'title_submission', Document::TITLE_PROPOSAL_NAME)->assertRedirect();

        $this->assertSame(DocumentVersion::firstOrFail()->file_path, $thesis->title->fresh()->proposal_path);
    }

    private function prodiUser(string $roleSlug, ?int $studyProgramId = null): User
    {
        return User::factory()->withRoles($roleSlug)->create(['study_program_id' => $studyProgramId ?? StudyProgram::value('id')]);
    }

    public function test_prodi_staff_can_read_and_download_documents_of_their_study_program(): void
    {
        $mahasiswa = $this->mahasiswa();
        $thesis = $this->thesisOf($mahasiswa);
        $this->upload($mahasiswa);

        foreach (['admin_prodi', 'kaprodi'] as $roleSlug) {
            $user = $this->prodiUser($roleSlug);

            $this->actingAs($user)->get(route('dashboard'))->assertSee(route('prodi.documents'));
            $this->actingAs($user)->get(route('prodi.documents'))->assertOk()->assertSee(route('prodi.documents.show', $thesis));
            $this->actingAs($user)->get(route('prodi.documents.show', $thesis))->assertOk()->assertSee('Proposal Bab I');
            $this->actingAs($user)->get(route('prodi.documents.versions.download', DocumentVersion::firstOrFail()))->assertOk();
        }
    }

    public function test_the_administrator_sees_documents_of_every_study_program(): void
    {
        $mahasiswa = $this->mahasiswa();
        $thesis = $this->thesisOf($mahasiswa);
        $this->upload($mahasiswa);

        $this->actingAs(User::factory()->withRoles('administrator')->create())
            ->get(route('prodi.documents'))
            ->assertOk()
            ->assertSee(route('prodi.documents.show', $thesis));
    }

    public function test_prodi_staff_of_another_study_program_cannot_see_the_documents(): void
    {
        $mahasiswa = $this->mahasiswa();
        $thesis = $this->thesisOf($mahasiswa);
        $this->upload($mahasiswa);
        $otherProgram = StudyProgram::create(['faculty_id' => Faculty::value('id'), 'name' => 'Sistem Informasi', 'code' => 'SI']);
        $outsider = $this->prodiUser('admin_prodi', $otherProgram->id);

        $this->actingAs($outsider)->get(route('prodi.documents'))->assertOk()->assertDontSee(route('prodi.documents.show', $thesis));
        $this->actingAs($outsider)->get(route('prodi.documents.show', $thesis))->assertForbidden();
        $this->actingAs($outsider)->get(route('prodi.documents.versions.download', DocumentVersion::firstOrFail()))->assertForbidden();
    }

    public function test_prodi_staff_cannot_upload_documents(): void
    {
        $this->mahasiswa();

        $this->actingAs($this->prodiUser('admin_prodi'))
            ->post(route('mahasiswa.documents.store'), ['stage' => 'proposal', 'name' => 'x', 'file' => $this->pdf()])
            ->assertForbidden();
    }

    public function test_a_new_version_is_rejected_once_the_stage_is_closed(): void
    {
        $mahasiswa = $this->mahasiswa();
        $thesis = $this->thesisOf($mahasiswa, ThesisStatus::Completed);
        $document = $thesis->documents()->create(['stage' => 'proposal', 'name' => 'Proposal']);

        $this->actingAs($mahasiswa)->post(route('mahasiswa.documents.versions.store', $document), ['file' => $this->pdf()])->assertForbidden();
    }

    public function test_only_pdf_and_word_files_are_accepted(): void
    {
        $mahasiswa = $this->mahasiswa();
        $this->thesisOf($mahasiswa);

        $this->actingAs($mahasiswa)->post(route('mahasiswa.documents.store'), [
            'stage' => 'proposal',
            'name' => 'Proposal',
            'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
        ])->assertSessionHasErrors('file');
    }

    public function test_a_student_can_download_their_own_version(): void
    {
        $mahasiswa = $this->mahasiswa();
        $this->thesisOf($mahasiswa);
        $this->upload($mahasiswa);

        $this->actingAs($mahasiswa)->get(route('mahasiswa.documents.versions.download', DocumentVersion::firstOrFail()))->assertOk();
    }

    public function test_another_student_cannot_touch_the_document(): void
    {
        $owner = $this->mahasiswa();
        $this->thesisOf($owner);
        $this->upload($owner);
        $other = $this->mahasiswa('2202');
        $this->thesisOf($other);

        $this->actingAs($other)->get(route('mahasiswa.documents.versions.download', DocumentVersion::firstOrFail()))->assertForbidden();
        $this->actingAs($other)->post(route('mahasiswa.documents.versions.store', Document::firstOrFail()), ['file' => $this->pdf()])->assertForbidden();

        $this->assertSame(1, DocumentVersion::count());
    }

    public function test_a_student_without_a_thesis_sees_a_hint_and_cannot_upload(): void
    {
        $mahasiswa = $this->mahasiswa();

        $this->actingAs($mahasiswa)->get(route('mahasiswa.documents'))->assertOk()->assertSee('Belum ada data skripsi');
        $this->upload($mahasiswa)->assertForbidden();
    }

    public function test_a_role_without_the_permission_cannot_upload(): void
    {
        $this->actingAs(User::factory()->withRoles('dosen_pa')->create())
            ->post(route('mahasiswa.documents.store'), ['stage' => 'proposal', 'name' => 'x', 'file' => $this->pdf()])
            ->assertForbidden();
    }

    public function test_replacing_the_title_proposal_keeps_every_version(): void
    {
        $mahasiswa = $this->mahasiswa();
        $mahasiswa->studentRecord->update([
            'academic_advisor_id' => Lecturer::create(['nidn' => '0011', 'name' => 'PA', 'is_active' => true])->id,
        ]);
        $this->actingAs($mahasiswa)->get(route('mahasiswa.title-submission'));

        foreach (['draft-1.pdf', 'draft-2.pdf'] as $file) {
            $this->actingAs($mahasiswa)->put(route('mahasiswa.title-submission.update'), [
                'title' => 'Judul',
                'proposal' => $this->pdf($file),
                'action' => 'draft',
            ])->assertRedirect(route('mahasiswa.title-submission'));
        }

        $thesis = Thesis::firstOrFail();
        $versions = $thesis->documents()->where('stage', 'title_submission')->firstOrFail()->versions()->orderBy('version_number')->get();

        $this->assertSame(['draft-1.pdf', 'draft-2.pdf'], $versions->pluck('original_name')->all());
        Storage::disk('local')->assertExists($versions[0]->file_path);
        $this->assertSame($versions[1]->file_path, $thesis->title->proposal_path);
    }
}
