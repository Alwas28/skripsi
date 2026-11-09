<?php

namespace Tests\Feature;

use App\Enums\ThesisStatus;
use App\Models\NotificationLog;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ThesisUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsThesisWorkflow;
use Tests\TestCase;

class WhatsAppNotificationTest extends TestCase
{
    use BuildsThesisWorkflow;
    use RefreshDatabase;

    private const GATEWAY = 'https://gateway.test/send';

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildCast();
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.url' => self::GATEWAY,
            'services.whatsapp.token' => 'token-rahasia',
        ]);
    }

    private function giveStudentNumber(string $number = '081200000001'): void
    {
        $this->studentUser->studentRecord->update(['no_wa' => $number]);
        $this->studentUser->unsetRelation('studentRecord');
    }

    public function test_a_student_with_a_number_receives_the_message(): void
    {
        Http::fake([self::GATEWAY => Http::response(['status' => true])]);
        $this->giveStudentNumber();

        ThesisUpdate::send($this->studentUser, 'Jadwal ujian', 'Besok pukul 10.00.', '/mahasiswa/jadwal');

        Http::assertSent(fn (ClientRequest $request) => $request->url() === self::GATEWAY
            && $request->hasHeader('Authorization', 'token-rahasia')
            && $request['target'] === '081200000001'
            && str_contains($request['message'], 'Jadwal ujian')
            && str_contains($request['message'], url('/mahasiswa/jadwal')));
        $this->assertDatabaseHas('notification_logs', ['user_id' => $this->studentUser->id, 'channel' => 'whatsapp', 'status' => 'sent']);
    }

    public function test_a_lecturer_uses_the_number_from_their_record(): void
    {
        Http::fake([self::GATEWAY => Http::response(['status' => true])]);
        $this->supervisorOne->update(['no_wa' => '081200000002']);

        ThesisUpdate::send($this->supervisorOne->user, 'Bimbingan baru', 'Pesan.', '/dosen/bimbingan');

        Http::assertSent(fn (ClientRequest $request) => $request['target'] === '081200000002');
    }

    public function test_nothing_is_sent_without_a_number(): void
    {
        Http::fake();

        ThesisUpdate::send($this->studentUser, 'Judul', 'Pesan.', '/a');

        Http::assertNothingSent();
        $this->assertSame(1, $this->studentUser->notifications()->count());
    }

    public function test_nothing_is_sent_while_disabled_or_without_a_token(): void
    {
        Http::fake();
        $this->giveStudentNumber();

        config(['services.whatsapp.enabled' => false]);
        ThesisUpdate::send($this->studentUser, 'Judul', 'Pesan.', '/a');

        config(['services.whatsapp.enabled' => true, 'services.whatsapp.token' => null]);
        ThesisUpdate::send($this->studentUser, 'Judul', 'Pesan.', '/a');

        Http::assertNothingSent();
    }

    public function test_a_gateway_failure_is_logged_and_still_reaches_other_recipients(): void
    {
        Http::fake([self::GATEWAY => Http::response('error', 500)]);
        $this->withoutExceptionHandling();
        $this->giveStudentNumber();

        ThesisUpdate::send([$this->studentUser, $this->kaprodi], 'Judul', 'Pesan.', '/a');

        $this->assertDatabaseHas('notification_logs', ['user_id' => $this->studentUser->id, 'channel' => 'whatsapp', 'status' => 'failed']);
        $this->assertSame(1, $this->studentUser->notifications()->count());
        $this->assertSame(1, $this->kaprodi->notifications()->count());
    }

    public function test_a_gateway_rejection_in_the_response_body_counts_as_failed(): void
    {
        Http::fake([self::GATEWAY => Http::response(['status' => false, 'reason' => 'invalid token'])]);
        $this->giveStudentNumber();

        ThesisUpdate::send($this->studentUser, 'Judul', 'Pesan.', '/a');

        $this->assertSame('failed', NotificationLog::where('channel', 'whatsapp')->value('status'));
    }

    public function test_the_student_form_saves_the_number(): void
    {
        $admin = User::factory()->withRoles('admin_prodi')->create();

        $this->actingAs($admin)->post(route('prodi.students.store'), [
            'email' => 'wa@umkendari.ac.id', 'no_wa' => '081200000009',
            'study_program_id' => $this->studyProgram->id,
            'nim' => '22019999', 'name' => 'Punya WA', 'entry_year' => '2022', 'status' => 'aktif',
        ])->assertSessionHasNoErrors();

        $student = Student::where('nim', '22019999')->firstOrFail();
        $this->assertSame('081200000009', $student->no_wa);
        $this->actingAs($admin)->get(route('prodi.students.edit', $student))->assertOk()->assertSee('081200000009');
    }

    public function test_the_workflow_sends_whatsapp_too(): void
    {
        Http::fake([self::GATEWAY => Http::response(['status' => true])]);
        $this->giveStudentNumber();
        $thesis = $this->thesisAt(ThesisStatus::TitleReview);

        $this->actingAs($this->kaprodi)->post(route('prodi.title-submissions.approve', $thesis))->assertRedirect();

        Http::assertSent(fn (ClientRequest $request) => str_contains($request['message'], 'Judul disetujui'));
    }
}
