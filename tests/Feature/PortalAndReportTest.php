<?php

namespace Tests\Feature;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamDecision;
use App\Enums\ExamRevisionStatus;
use App\Enums\ThesisStatus;
use App\Models\ExamAttempt;
use App\Models\ExamType;
use App\Models\Faculty;
use App\Models\NotificationLog;
use App\Models\Role;
use App\Models\StudyProgram;
use App\Models\Thesis;
use App\Models\User;
use App\Notifications\ThesisUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\BuildsThesisWorkflow;
use Tests\TestCase;

class PortalAndReportTest extends TestCase
{
    use BuildsThesisWorkflow;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-20 08:00:00');
        $this->buildCast();
    }

    private function attemptFor(Thesis $thesis, ?ExamDecision $decision = null, string $date = '2026-11-25', int $number = 1): ExamAttempt
    {
        $exam = $thesis->exams()->firstOrCreate(['exam_type_id' => ExamType::where('code', 'proposal')->value('id')]);
        $attempt = $exam->attempts()->create([
            'attempt_number' => $number,
            'status' => $decision ? ExamAttemptStatus::Decided : ExamAttemptStatus::Scheduled,
        ]);
        $attempt->examiners()->create(['lecturer_id' => $this->examinerOne->id, 'position' => 1]);
        $attempt->examiners()->create(['lecturer_id' => $this->examinerTwo->id, 'position' => 2]);
        $attempt->schedule()->create(['room_id' => $this->room->id, 'exam_date' => $date, 'start_time' => '10:00', 'end_time' => '11:30']);

        if ($decision) {
            $attempt->result()->create(['final_score' => 77.5, 'decision' => $decision, 'decided_at' => now()]);
        }

        return $attempt;
    }

    private function outsider(string $roleSlug): User
    {
        $program = StudyProgram::create(['faculty_id' => Faculty::value('id'), 'name' => 'Sistem Informasi', 'code' => 'SI']);

        return User::factory()->withRoles($roleSlug)->create(['study_program_id' => $program->id]);
    }

    private function asStudent(string $route): TestResponse
    {
        return $this->actingAs($this->studentUser->fresh())->get(route($route));
    }

    public function test_the_student_overviews_show_their_exam(): void
    {
        $thesis = $this->thesisAt(ThesisStatus::Research);
        $attempt = $this->attemptFor($thesis, ExamDecision::Passed);

        $this->asStudent('mahasiswa.thesis')->assertOk()->assertSee('Sistem Monitoring Skripsi')->assertSee('Penelitian');
        $this->asStudent('mahasiswa.schedule')->assertOk()->assertSee('Ruang Sidang')->assertSee('Citra Penguji');
        $this->asStudent('mahasiswa.grades')->assertOk()->assertSee('77.50')->assertSee('Lulus');
        $this->asStudent('mahasiswa.minutes')->assertOk()->assertSee(route('minutes.show', $attempt));
    }

    public function test_the_student_overviews_work_without_a_thesis(): void
    {
        foreach (['mahasiswa.thesis', 'mahasiswa.schedule', 'mahasiswa.grades', 'mahasiswa.minutes'] as $route) {
            $this->asStudent($route)->assertOk();
        }
    }

    public function test_a_student_does_not_see_another_students_exam(): void
    {
        $this->attemptFor($this->thesisAt(ThesisStatus::ProposalExam));
        $other = $this->makeStudent('2202', 'Lain');
        $this->thesisAt(ThesisStatus::Supervision, $other);

        $this->actingAs($other)->get(route('mahasiswa.schedule'))->assertOk()->assertDontSee('Ruang Sidang');
    }

    public function test_lecturer_overviews_show_only_their_own_work(): void
    {
        $this->attemptFor($this->thesisAt(ThesisStatus::ProposalExam));
        $outsider = $this->makeLecturer('0099', 'Luar', 'dosen_penguji');

        $this->actingAs($this->examinerOne->user)->get(route('dosen.exam-schedule'))->assertOk()->assertSee('Budi Mahasiswa');
        $this->actingAs($this->supervisorOne->user)->get(route('dosen.exam-schedule'))->assertOk()->assertSee('Budi Mahasiswa');
        $this->actingAs($this->examinerOne->user)->get(route('dosen.history'))->assertOk()->assertSee('Penguji 1');
        $this->actingAs($outsider->user)->get(route('dosen.exam-schedule'))->assertOk()->assertDontSee('Budi Mahasiswa');
    }

    public function test_lecturer_pages_handle_an_account_without_a_lecturer_record(): void
    {
        $user = User::factory()->withRoles('dosen_penguji')->create();

        foreach (['dosen.exam-schedule', 'dosen.history', 'dosen.examinees', 'dosen.assessment', 'dosen.revisions'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk()->assertSee('Belum Terhubung');
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function prodiPageProvider(): array
    {
        return [
            'jadwal' => ['prodi.schedule'],
            'penilaian' => ['prodi.assessment'],
            'revisi' => ['prodi.revisions'],
        ];
    }

    #[DataProvider('prodiPageProvider')]
    public function test_prodi_pages_are_limited_to_the_study_program(string $route): void
    {
        $attempt = $this->attemptFor($this->thesisAt(ThesisStatus::ProposalRevision), ExamDecision::Revision);
        $attempt->revisions()->create(['lecturer_id' => $this->examinerOne->id, 'chapter' => 'Bab II', 'notes' => 'Perbaiki', 'status' => ExamRevisionStatus::Open]);

        $this->actingAs($this->kaprodi)->get(route($route))->assertOk()->assertSee('Budi Mahasiswa');
        $this->actingAs($this->outsider('kaprodi'))->get(route($route))->assertOk()->assertDontSee('Budi Mahasiswa');
    }

    public function test_the_examiner_overview_is_limited_to_the_study_program(): void
    {
        $this->attemptFor($this->thesisAt(ThesisStatus::ProposalExam));

        $this->actingAs($this->adminProdi)->get(route('prodi.examiners'))->assertOk()->assertSee('Citra Penguji');
        $this->actingAs($this->outsider('admin_prodi'))->get(route('prodi.examiners'))->assertOk()->assertDontSee('Citra Penguji');
    }

    public function test_monitoring_flags_a_student_without_recent_supervision(): void
    {
        $thesis = $this->thesisAt(ThesisStatus::Supervision);
        $this->approvedSessions($thesis, 1);
        Carbon::setTestNow('2027-01-05 08:00:00');

        $this->actingAs($this->kaprodi)->get(route('prodi.monitoring'))->assertOk()->assertSee('Tidak bimbingan &gt; 30 hari', false);
    }

    public function test_monitoring_flags_failed_late_and_revision_states(): void
    {
        $this->thesisAt(ThesisStatus::ProposalFailed);
        $revision = $this->makeStudent('2202', 'Sedang Revisi');
        $this->thesisAt(ThesisStatus::ResultRevision, $revision);

        $response = $this->actingAs($this->kaprodi)->get(route('prodi.monitoring'))->assertOk();
        $response->assertSee('Ujian tidak lulus')->assertSee('Revisi belum diselesaikan');

        Carbon::setTestNow('2028-01-01 08:00:00');
        $this->actingAs($this->kaprodi)->get(route('prodi.monitoring'))->assertSee('Melebihi batas waktu');
    }

    public function test_monitoring_shows_normal_progress(): void
    {
        $thesis = $this->thesisAt(ThesisStatus::Supervision);
        $this->approvedSessions($thesis, 1);

        $this->actingAs($this->kaprodi)->get(route('prodi.monitoring'))->assertOk()->assertSee('Progress normal');
    }

    public function test_monitoring_is_limited_to_the_study_program_and_permission(): void
    {
        $this->thesisAt(ThesisStatus::ProposalFailed);

        $this->actingAs($this->outsider('kaprodi'))->get(route('prodi.monitoring'))->assertOk()->assertDontSee('Budi Mahasiswa');
        $this->actingAs($this->adminProdi)->get(route('prodi.monitoring'))->assertForbidden();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function reportProvider(): array
    {
        return [
            'mahasiswa' => ['mahasiswa', 'Budi Mahasiswa'],
            'dosen' => ['dosen', 'Ani Pembimbing'],
            'ujian' => ['ujian', 'Ujian Proposal'],
            'prodi' => ['prodi', 'Tanpa tahun akademik'],
        ];
    }

    #[DataProvider('reportProvider')]
    public function test_each_report_renders(string $tab, string $expected): void
    {
        $this->attemptFor($this->thesisAt(ThesisStatus::Research), ExamDecision::Passed);

        $this->actingAs($this->kaprodi)->get(route('prodi.reports', ['tab' => $tab]))->assertOk()->assertSee($expected);
    }

    public function test_the_exam_report_counts_outcomes_and_reexams(): void
    {
        $thesis = $this->thesisAt(ThesisStatus::Research);
        $this->attemptFor($thesis, ExamDecision::Failed);
        $this->attemptFor($thesis, ExamDecision::Passed, '2026-11-26', 2);

        $rows = $this->actingAs($this->kaprodi)->get(route('prodi.reports', ['tab' => 'ujian']))->assertOk()->viewData('rows');

        $this->assertSame(['Ujian Proposal', 2, 1, 0, 1, 1, '77.50'], $rows->first());
    }

    public function test_the_student_dashboard_shows_progress_and_next_exam(): void
    {
        $thesis = $this->thesisAt(ThesisStatus::ProposalExam);
        $this->attemptFor($thesis);

        $panel = $this->asStudent('dashboard')->assertOk()->assertSee('Dashboard Skripsi')->viewData('studentPanel');

        $this->assertSame(30, $panel['percent']);
        $this->assertSame('2026-11-25', $panel['nextExam']->exam_date->toDateString());
    }

    public function test_the_lecturer_dashboard_counts_their_work(): void
    {
        $thesis = $this->thesisAt(ThesisStatus::Supervision);
        $thesis->supervisionSessions()->create([
            'lecturer_id' => $this->supervisorOne->id, 'phase' => 'proposal', 'chapter' => 'Bab I', 'topic' => 'T',
            'session_date' => today()->toDateString(), 'status' => 'requested',
        ]);

        $panel = $this->actingAs($this->supervisorOne->user)->get(route('dashboard'))->assertOk()->viewData('lecturerPanel');

        $this->assertSame(1, $panel['stats']['Mahasiswa Bimbingan']);
        $this->assertSame(1, $panel['stats']['Menunggu Review']);
        $this->assertSame(1, $panel['stats']['Bimbingan Hari Ini']);
    }

    public function test_the_prodi_dashboard_counts_its_own_program(): void
    {
        $this->thesisAt(ThesisStatus::ProposalFailed);
        $revision = $this->makeStudent('2202', 'Dua');
        $this->thesisAt(ThesisStatus::ResultRevision, $revision);

        $panel = $this->actingAs($this->kaprodi)->get(route('dashboard'))->assertOk()->viewData('prodiPanel');
        $this->assertSame(2, $panel['Mahasiswa Skripsi']);
        $this->assertSame(1, $panel['Ujian Tidak Lulus']);
        $this->assertSame(1, $panel['Revisi']);

        $outsiderPanel = $this->actingAs($this->outsider('kaprodi'))->get(route('dashboard'))->assertOk()->viewData('prodiPanel');
        $this->assertSame(0, $outsiderPanel['Mahasiswa Skripsi']);
    }

    public function test_a_user_with_several_roles_sees_each_panel(): void
    {
        $this->supervisorOne->user->roles()->attach(Role::where('slug', 'kaprodi')->value('id'));

        $response = $this->actingAs($this->supervisorOne->user->fresh())->get(route('dashboard'))->assertOk();

        $this->assertNotNull($response->viewData('prodiPanel'));
        $this->assertNotNull($response->viewData('lecturerPanel'));
    }

    public function test_email_is_added_only_when_enabled(): void
    {
        Notification::fake();

        ThesisUpdate::send($this->studentUser, 'Judul', 'Pesan', '/a');
        Notification::assertSentTo($this->studentUser, ThesisUpdate::class, fn ($notification, array $channels) => $channels === ['database']);

        config(['services.thesis_notifications.email' => true]);
        ThesisUpdate::send($this->kaprodi, 'Judul', 'Pesan', '/a');
        Notification::assertSentTo($this->kaprodi, ThesisUpdate::class, fn ($notification, array $channels) => $channels === ['database', 'mail']);
    }

    public function test_the_mail_message_carries_the_link(): void
    {
        $mail = (new ThesisUpdate('Judul', 'Isi pesan', '/mahasiswa/nilai'))->toMail($this->studentUser);

        $this->assertSame('Judul', $mail->subject);
        $this->assertSame(url('/mahasiswa/nilai'), $mail->actionUrl);
    }

    public function test_a_delivery_failure_is_logged_and_does_not_break_the_step(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP mati'));

        ThesisUpdate::send($this->studentUser, 'Judul', 'Pesan', '/a');

        $this->assertDatabaseHas('notification_logs', ['user_id' => $this->studentUser->id, 'status' => 'failed']);
        $this->assertSame(1, NotificationLog::count());
    }

    public function test_the_reminder_goes_to_everyone_in_tomorrows_exam_only(): void
    {
        $thesis = $this->thesisAt(ThesisStatus::ProposalExam);
        $this->attemptFor($thesis, null, '2026-11-21');
        $other = $this->makeStudent('2202', 'Lusa');
        $this->attemptFor($this->thesisAt(ThesisStatus::ProposalExam, $other), null, '2026-11-22');

        $this->artisan('exams:send-reminders')->assertSuccessful();

        $titles = fn (User $user) => $user->notifications()->get()->pluck('data.title')->all();
        $this->assertSame(['Pengingat ujian'], $titles($this->studentUser));
        $this->assertSame(['Pengingat ujian'], $titles($this->examinerOne->user));
        $this->assertSame(['Pengingat ujian'], $titles($this->supervisorTwo->user));
        $this->assertSame([], $titles($other));
    }
}
