<?php

namespace Tests\Feature;

use App\Enums\SupervisorApprovalStatus;
use App\Enums\ThesisStatus;
use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\NotificationLog;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudyProgram;
use App\Models\Thesis;
use App\Models\User;
use App\Notifications\ThesisUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private StudyProgram $studyProgram;

    private User $advisor;

    private User $studentUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->studyProgram = StudyProgram::create([
            'faculty_id' => Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT'])->id,
            'name' => 'Teknik Informatika', 'code' => 'TI',
        ]);
        $this->advisor = User::factory()->withRoles('dosen_pa')->create();
        $this->studentUser = User::factory()->withRoles('mahasiswa')->create();

        Student::create([
            'user_id' => $this->studentUser->id,
            'study_program_id' => $this->studyProgram->id,
            'academic_advisor_id' => $this->lecturerFor($this->advisor, '0011')->id,
            'nim' => '2201', 'name' => 'Budi', 'entry_year' => '2022', 'status' => 'aktif',
        ]);
    }

    private function lecturerFor(User $user, string $nidn): Lecturer
    {
        return Lecturer::create(['user_id' => $user->id, 'nidn' => $nidn, 'name' => "Dosen {$nidn}", 'is_active' => true]);
    }

    private function thesis(ThesisStatus $status): Thesis
    {
        $thesis = $this->studentUser->studentRecord->thesis()->create(['status' => $status]);
        $thesis->title()->create(['title' => 'Sistem Monitoring', 'proposal_path' => 'proposal.pdf']);

        return $thesis;
    }

    private function prodiUser(string $roleSlug, ?int $studyProgramId = null): User
    {
        return User::factory()->withRoles($roleSlug)->create(['study_program_id' => $studyProgramId ?? $this->studyProgram->id]);
    }

    /**
     * @return list<string>
     */
    private function titlesOf(User $user): array
    {
        return $user->notifications()->get()->map(fn ($notification) => $notification->data['title'])->all();
    }

    private function thesisWithPendingSupervisors(): Thesis
    {
        $thesis = $this->thesis(ThesisStatus::TitleApproved);

        foreach ([1, 2] as $position) {
            $user = User::factory()->withRoles('dosen_pembimbing')->create();
            $thesis->supervisors()->create([
                'lecturer_id' => $this->lecturerFor($user, "002{$position}")->id,
                'position' => $position,
                'status' => SupervisorApprovalStatus::Pending,
            ]);
        }

        return $thesis;
    }

    public function test_submitting_a_title_notifies_the_advisor(): void
    {
        $this->thesis(ThesisStatus::Draft);

        $this->actingAs($this->studentUser)->put(route('mahasiswa.title-submission.update'), [
            'title' => 'Sistem Monitoring', 'research_field' => 'RPL', 'background' => 'a', 'problem_statement' => 'b',
            'objectives' => 'c', 'method' => 'd', 'research_object' => 'e', 'initial_references' => 'f',
            'action' => 'submit',
        ])->assertRedirect();

        $this->assertSame(['Usulan judul menunggu persetujuan Anda'], $this->titlesOf($this->advisor));
    }

    public function test_advisor_approval_notifies_the_student_and_only_the_kaprodi_of_that_study_program(): void
    {
        $thesis = $this->thesis(ThesisStatus::Submitted);
        $kaprodi = $this->prodiUser('kaprodi');
        $otherProgram = StudyProgram::create(['faculty_id' => Faculty::value('id'), 'name' => 'Sistem Informasi', 'code' => 'SI']);
        $otherKaprodi = $this->prodiUser('kaprodi', $otherProgram->id);

        $this->actingAs($this->advisor)->post(route('dosen.advisor-titles.approve', $thesis))->assertRedirect();

        $this->assertSame(['Judul disetujui Dosen PA'], $this->titlesOf($this->studentUser));
        $this->assertSame(['Judul menunggu persetujuan Kaprodi'], $this->titlesOf($kaprodi));
        $this->assertSame([], $this->titlesOf($otherKaprodi));
    }

    public function test_a_revision_request_notifies_the_student(): void
    {
        $thesis = $this->thesis(ThesisStatus::Submitted);

        $this->actingAs($this->advisor)->post(route('dosen.advisor-titles.request-revision', $thesis), ['review_notes' => 'Perbaiki.'])->assertRedirect();

        $this->assertSame(['Judul perlu revisi'], $this->titlesOf($this->studentUser));
    }

    public function test_kaprodi_approval_notifies_the_student_and_admin_prodi(): void
    {
        $thesis = $this->thesis(ThesisStatus::TitleReview);
        $admin = $this->prodiUser('admin_prodi');

        $this->actingAs($this->prodiUser('kaprodi'))->post(route('prodi.title-submissions.approve', $thesis))->assertRedirect();

        $this->assertSame(['Judul disetujui'], $this->titlesOf($this->studentUser));
        $this->assertSame(['Judul siap ditetapkan pembimbing'], $this->titlesOf($admin));
    }

    public function test_a_kaprodi_revision_request_notifies_the_student(): void
    {
        $thesis = $this->thesis(ThesisStatus::TitleReview);

        $this->actingAs($this->prodiUser('kaprodi'))->post(route('prodi.title-submissions.request-revision', $thesis), ['review_notes' => 'Perbaiki.'])->assertRedirect();

        $this->assertSame(['Judul perlu revisi'], $this->titlesOf($this->studentUser));
    }

    public function test_a_supervisor_proposal_notifies_the_kaprodi(): void
    {
        $thesis = $this->thesis(ThesisStatus::TitleApproved);
        $kaprodi = $this->prodiUser('kaprodi');
        $ids = collect(['0031', '0032'])->map(function (string $nidn) {
            $user = User::factory()->withRoles('dosen_pembimbing')->create();

            return Lecturer::create([
                'user_id' => $user->id, 'study_program_id' => $this->studyProgram->id,
                'nidn' => $nidn, 'name' => "Dosen {$nidn}", 'is_active' => true,
            ])->id;
        });

        $this->actingAs($this->prodiUser('admin_prodi'))->post(route('prodi.supervisors.store', $thesis), [
            'supervisor_1_id' => $ids[0],
            'supervisor_2_id' => $ids[1],
        ])->assertRedirect();

        $this->assertSame(['Usulan pembimbing menunggu persetujuan'], $this->titlesOf($kaprodi));
    }

    public function test_assigning_supervisors_notifies_the_student_and_both_supervisors(): void
    {
        $thesis = $this->thesisWithPendingSupervisors();

        $this->actingAs($this->prodiUser('kaprodi'))->post(route('prodi.supervisors.approve', $thesis))->assertRedirect();

        $this->assertSame(['Pembimbing ditetapkan'], $this->titlesOf($this->studentUser));
        foreach ($thesis->supervisors()->with('lecturer.user')->get() as $supervisor) {
            $this->assertSame(['Mahasiswa bimbingan baru'], $this->titlesOf($supervisor->lecturer->user));
        }
    }

    public function test_rejecting_supervisors_notifies_admin_prodi(): void
    {
        $thesis = $this->thesisWithPendingSupervisors();
        $admin = $this->prodiUser('admin_prodi');

        $this->actingAs($this->prodiUser('kaprodi'))->post(route('prodi.supervisors.reject', $thesis), ['review_notes' => 'Beban penuh.'])->assertRedirect();

        $this->assertSame(['Usulan pembimbing ditolak'], $this->titlesOf($admin));
    }

    public function test_the_person_who_acted_is_not_notified(): void
    {
        $thesis = $this->thesis(ThesisStatus::Submitted);
        $this->advisor->roles()->attach(Role::where('slug', 'kaprodi')->value('id'));
        $this->advisor->update(['study_program_id' => $this->studyProgram->id]);

        $this->actingAs($this->advisor)->post(route('dosen.advisor-titles.approve', $thesis))->assertRedirect();

        $this->assertSame([], $this->titlesOf($this->advisor));
    }

    public function test_a_sent_notification_is_logged(): void
    {
        ThesisUpdate::send($this->studentUser, 'Judul disetujui', 'Pesan.', '/mahasiswa/pengajuan-judul');

        $log = NotificationLog::firstOrFail();
        $this->assertSame($this->studentUser->id, $log->user_id);
        $this->assertSame('database', $log->channel);
        $this->assertSame('ThesisUpdate', $log->notification_type);
        $this->assertSame('Judul disetujui', $log->title);
        $this->assertSame('sent', $log->status);
    }

    public function test_the_page_lists_only_the_users_own_notifications(): void
    {
        ThesisUpdate::send($this->studentUser, 'Milik mahasiswa', 'Pesan.', '/a');
        ThesisUpdate::send($this->advisor, 'Milik dosen', 'Pesan.', '/b');

        $this->actingAs($this->studentUser)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Milik mahasiswa')
            ->assertDontSee('Milik dosen');
    }

    public function test_the_bell_shows_the_unread_count(): void
    {
        ThesisUpdate::send($this->studentUser, 'Satu', 'Pesan.', '/a');
        ThesisUpdate::send($this->studentUser, 'Dua', 'Pesan.', '/b');

        $this->actingAs($this->studentUser)->get(route('dashboard'))->assertSee('data-unread="2"', false);
    }

    public function test_opening_a_notification_marks_it_read_and_goes_to_its_page(): void
    {
        ThesisUpdate::send($this->studentUser, 'Judul disetujui', 'Pesan.', route('mahasiswa.supervisor', absolute: false));
        $notification = $this->studentUser->notifications()->firstOrFail();

        $this->actingAs($this->studentUser)->post(route('notifications.read', $notification->id))
            ->assertRedirect(route('mahasiswa.supervisor'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_another_users_notification_cannot_be_opened(): void
    {
        ThesisUpdate::send($this->studentUser, 'Rahasia', 'Pesan.', '/a');
        $notification = $this->studentUser->notifications()->firstOrFail();

        $this->actingAs($this->advisor)->post(route('notifications.read', $notification->id))->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read_only_touches_the_users_own_notifications(): void
    {
        ThesisUpdate::send($this->studentUser, 'Satu', 'Pesan.', '/a');
        ThesisUpdate::send($this->advisor, 'Dua', 'Pesan.', '/b');

        $this->actingAs($this->studentUser)->post(route('notifications.read-all'))->assertRedirect(route('notifications.index'));

        $this->assertSame(0, $this->studentUser->unreadNotifications()->count());
        $this->assertSame(1, $this->advisor->unreadNotifications()->count());
    }

    public function test_an_account_without_the_permission_has_no_bell_or_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('data-unread', false);
        $this->actingAs($user)->get(route('notifications.index'))->assertForbidden();
    }
}
