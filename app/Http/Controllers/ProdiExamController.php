<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStage;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamDecision;
use App\Enums\ExamKind;
use App\Enums\ThesisStatus;
use App\Models\ExamAttempt;
use App\Models\ExamSchedule;
use App\Models\Lecturer;
use App\Models\Role;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Thesis;
use App\Notifications\ThesisUpdate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Prodi side of an exam: verification → examiners → schedule → decision.
 */
class ProdiExamController extends Controller
{
    public function index(Request $request, ExamKind $examKind): View
    {
        return view('shared.table', [
            'title' => $examKind->label(),
            'tableTitle' => "Daftar {$examKind->label()}",
            'needsStudyProgram' => true,
            'columns' => ['Mahasiswa', 'Judul', 'Percobaan', 'Jadwal', 'Status', 'Keputusan', ''],
            'empty' => 'Belum ada pengajuan ujian.',
            'rows' => ExamAttempt::whereHas('exam', fn (Builder $exam) => $exam
                ->whereHas('type', fn (Builder $type) => $type->where('code', $examKind->value))
                ->whereHas('thesis', fn (Builder $thesis) => $thesis->visibleToProdiStaff($request->user())))
                ->with(['exam.thesis.student', 'exam.thesis.title', 'schedule.room', 'result'])
                ->latest('id')
                ->paginate(15)
                ->through(fn (ExamAttempt $attempt) => [
                    $attempt->exam->thesis->student->name,
                    $attempt->exam->thesis->title?->title,
                    $attempt->attempt_number,
                    $attempt->schedule ? $attempt->schedule->exam_date->format('d M Y').' '.$attempt->schedule->timeRange() : null,
                    ['badge' => $attempt->status->label(), 'class' => $attempt->status->badgeClass()],
                    $attempt->result ? ['badge' => $attempt->result->decision->label(), 'class' => $attempt->result->decision->badgeClass()] : null,
                    ['link' => route('prodi.exams.attempt', $attempt), 'text' => 'Kelola'],
                ]),
        ]);
    }

    public function show(Request $request, ExamAttempt $examAttempt): View
    {
        $this->ensureVisible($request, $examAttempt);
        abort_unless($request->user()->hasPermission($examAttempt->kind()->viewPermission()), 403);

        $examAttempt->load(['exam.thesis.student.studyProgram', 'exam.thesis.title', 'exam.thesis.supervisors.lecturer', 'examiners.lecturer', 'schedule.room', 'assessments.lecturer', 'result', 'revisions.lecturer']);

        $thesis = $examAttempt->exam->thesis;
        $rules = Setting::examRules($thesis->student->study_program_id, $examAttempt->kind());
        $score = $examAttempt->status === ExamAttemptStatus::Scheduled ? $examAttempt->calculatedScore() : null;

        return view('prodi.exams.show', [
            'attempt' => $examAttempt,
            'kind' => $examAttempt->kind(),
            'thesis' => $thesis,
            'rules' => $rules,
            'score' => $score,
            'assessors' => $examAttempt->requiredAssessors(),
            'examinerOptions' => Lecturer::where('is_active', true)->holdingRole(Role::DOSEN_PENGUJI)->orderBy('name')->get(),
            'rooms' => Room::where('is_active', true)->orderBy('name')->get(),
            'decisions' => $score === null ? [] : $this->allowedDecisions($score, $rules['pass_score']),
        ]);
    }

    public function verify(Request $request, ExamAttempt $examAttempt): RedirectResponse
    {
        $this->ensureVisible($request, $examAttempt);
        abort_unless($examAttempt->status === ExamAttemptStatus::Requested, 403, 'Pengajuan ini tidak sedang menunggu verifikasi.');

        $kind = $examAttempt->kind();
        $thesis = $examAttempt->exam->thesis;
        $administrationRequired = Setting::examRules($thesis->student->study_program_id, $kind)['administration_required'];

        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'notes' => ['nullable', 'required_if:action,reject', 'string'],
            'administration_ok' => $administrationRequired && $request->input('action') === 'approve' ? ['required', 'accepted'] : ['nullable'],
        ], [
            'administration_ok.required' => 'Administrasi akademik harus dikonfirmasi terpenuhi.',
            'administration_ok.accepted' => 'Administrasi akademik harus dikonfirmasi terpenuhi.',
        ]);

        $approved = $data['action'] === 'approve';

        DB::transaction(function () use ($request, $examAttempt, $thesis, $kind, $data, $approved) {
            $examAttempt->update([
                'status' => $approved ? ExamAttemptStatus::Verified : ExamAttemptStatus::Rejected,
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
                'verification_notes' => $data['notes'] ?? null,
            ]);

            if (! $approved) {
                $thesis->transitionTo(
                    $thesis->status === $kind->reexamStatus() ? $kind->resupervisionStatus() : $kind->supervisionStatus(),
                    $request->user(),
                    $data['notes'],
                );
            }
        });

        ThesisUpdate::send(
            $thesis->student->user,
            $approved ? 'Ujian disetujui' : 'Pengajuan ujian ditolak',
            $approved ? "Pengajuan {$kind->label()} terverifikasi. Tunggu penetapan penguji dan jadwal." : "Prodi menolak pengajuan {$kind->label()}: {$data['notes']}",
            route("mahasiswa.exams.{$kind->value}", absolute: false),
        );

        return Redirect::route('prodi.exams.attempt', $examAttempt)->with('status', $approved ? 'Pengajuan terverifikasi.' : 'Pengajuan ditolak.');
    }

    public function assignExaminers(Request $request, ExamAttempt $examAttempt): RedirectResponse
    {
        $this->ensureVisible($request, $examAttempt);
        abort_unless($examAttempt->status === ExamAttemptStatus::Verified, 403, 'Penguji hanya bisa ditetapkan setelah verifikasi dan sebelum dijadwalkan.');

        $eligible = Rule::in(Lecturer::where('is_active', true)->holdingRole(Role::DOSEN_PENGUJI)->pluck('id'));
        $message = 'Pilih dosen aktif yang memiliki role Dosen Penguji.';

        $data = $request->validate([
            'examiner_1_id' => ['required', $eligible],
            'examiner_2_id' => ['required', 'different:examiner_1_id', $eligible],
        ], [
            'examiner_1_id.in' => $message,
            'examiner_2_id.in' => $message,
            'examiner_2_id.different' => 'Penguji 2 harus berbeda dari Penguji 1.',
        ]);

        DB::transaction(function () use ($examAttempt, $data) {
            $examAttempt->examiners->each->delete();

            foreach ([1 => $data['examiner_1_id'], 2 => $data['examiner_2_id']] as $position => $lecturerId) {
                $examAttempt->examiners()->create(['lecturer_id' => $lecturerId, 'position' => $position]);
            }
        });

        return Redirect::route('prodi.exams.attempt', $examAttempt)->with('status', 'Penguji berhasil ditetapkan.');
    }

    public function schedule(Request $request, ExamAttempt $examAttempt): RedirectResponse
    {
        $this->ensureVisible($request, $examAttempt);
        abort_unless(in_array($examAttempt->status, [ExamAttemptStatus::Verified, ExamAttemptStatus::Scheduled], true), 403, 'Ujian ini tidak bisa dijadwalkan pada tahap sekarang.');
        abort_unless($examAttempt->examiners()->count() === 2, 403, 'Tetapkan kedua penguji terlebih dahulu.');
        abort_if($examAttempt->assessments()->exists(), 403, 'Jadwal tidak bisa diubah karena penilaian sudah dimulai.');

        $data = $request->validate([
            'room_id' => ['required', Rule::exists('rooms', 'id')->where('is_active', true)],
            'exam_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        $conflicts = ExamSchedule::conflictsFor($examAttempt, $data['exam_date'], $data['start_time'], $data['end_time'], (int) $data['room_id']);

        if ($conflicts !== []) {
            return Redirect::route('prodi.exams.attempt', $examAttempt)->withInput()->withErrors(['schedule' => $conflicts]);
        }

        $kind = $examAttempt->kind();
        $thesis = $examAttempt->exam->thesis;

        DB::transaction(function () use ($request, $examAttempt, $thesis, $kind, $data) {
            $examAttempt->schedule()->updateOrCreate([], $data);
            $examAttempt->update(['status' => ExamAttemptStatus::Scheduled]);

            if ($thesis->status === $kind->readyStatus()) {
                $thesis->transitionTo($kind->examStatus(), $request->user(), 'Ujian dijadwalkan.');
            }
        });

        $room = Room::find($data['room_id']);
        $message = "{$kind->label()} {$thesis->student->name}: {$data['exam_date']} pukul {$data['start_time']}–{$data['end_time']}, {$room->name}.";

        ThesisUpdate::send($thesis->student->user, 'Jadwal ujian', $message, route('mahasiswa.schedule', absolute: false));
        ThesisUpdate::send(
            $examAttempt->examiners()->with('lecturer.user')->get()->map->lecturer
                ->merge($thesis->approvedSupervisors()->map->lecturer)
                ->map->user,
            'Jadwal ujian',
            $message,
            route('dosen.exam-schedule', absolute: false),
        );

        return Redirect::route('prodi.exams.attempt', $examAttempt)->with('status', 'Jadwal berhasil disimpan.');
    }

    public function decide(Request $request, ExamAttempt $examAttempt): RedirectResponse
    {
        $this->ensureVisible($request, $examAttempt);
        abort_unless($examAttempt->status === ExamAttemptStatus::Scheduled, 403, 'Ujian ini tidak sedang menunggu keputusan.');

        $score = $examAttempt->calculatedScore();
        abort_if($score === null, 403, 'Penilaian dari semua penguji dan pembimbing belum lengkap.');

        $data = $request->validate([
            'decision' => ['required', Rule::in(array_map(fn (ExamDecision $decision) => $decision->value, $this->allowedDecisions($score, $examAttempt->passScore())))],
        ]);

        $decision = ExamDecision::from($data['decision']);

        if ($decision === ExamDecision::Revision && ! $examAttempt->revisions()->exists()) {
            return Redirect::route('prodi.exams.attempt', $examAttempt)->withErrors(['decision' => 'Belum ada catatan revisi dari penguji.']);
        }

        $kind = $examAttempt->kind();
        $thesis = $examAttempt->exam->thesis;

        $examAttempt->decide($decision, $score, $request->user());
        $this->storeMinutes($request, $examAttempt->fresh());

        [$title, $url] = match ($decision) {
            ExamDecision::Passed => ['Nilai tersedia: Lulus', route('mahasiswa.grades', absolute: false)],
            ExamDecision::Revision => ['Revisi ujian tersedia', route('mahasiswa.revisions', absolute: false)],
            ExamDecision::Failed => ['Ujian ulang diperlukan', route('mahasiswa.supervision', absolute: false)],
        };

        ThesisUpdate::send($thesis->student->user, $title, "{$kind->label()}: {$decision->label()} dengan nilai {$score}.", $url);

        return Redirect::route('prodi.exams.attempt', $examAttempt)->with('status', 'Keputusan ujian tersimpan.');
    }

    public function finalValidate(Request $request, Thesis $thesis): RedirectResponse
    {
        abort_unless($thesis->isVisibleToProdiStaff($request->user()), 403, 'Skripsi ini bukan dari program studi Anda.');
        abort_unless($thesis->status === ThesisStatus::FinalValidation, 403, 'Skripsi ini tidak sedang menunggu validasi akhir.');

        $thesis->transitionTo(ThesisStatus::Completed, $request->user(), 'Validasi akhir oleh Kaprodi.');

        ThesisUpdate::send(
            $thesis->student->user,
            'Skripsi selesai',
            'Selamat, skripsi Anda telah divalidasi dan dinyatakan selesai.',
            route('mahasiswa.thesis', absolute: false),
        );

        return Redirect::back()->with('status', 'Skripsi dinyatakan selesai.');
    }

    /**
     * @return list<ExamDecision>
     */
    private function allowedDecisions(float $score, float $passScore): array
    {
        return $score >= $passScore ? [ExamDecision::Passed, ExamDecision::Revision] : [ExamDecision::Failed];
    }

    private function storeMinutes(Request $request, ExamAttempt $attempt): void
    {
        $attempt->load(['exam.thesis.student.studyProgram', 'exam.thesis.title', 'examiners.lecturer', 'schedule.room', 'assessments.lecturer', 'result']);

        $thesis = $attempt->exam->thesis;
        $name = "Berita Acara {$attempt->kind()->label()} Percobaan {$attempt->attempt_number}";

        $thesis->documents()
            ->firstOrCreate(['stage' => DocumentStage::Minutes->value, 'name' => $name])
            ->addGeneratedVersion(
                view('minutes.document', ['attempt' => $attempt])->render(),
                str_replace(' ', '-', $name).'.html',
                $request->user(),
            );
    }

    private function ensureVisible(Request $request, ExamAttempt $attempt): void
    {
        abort_unless($attempt->exam->thesis->isVisibleToProdiStaff($request->user()), 403, 'Ujian ini bukan dari program studi Anda.');
    }
}
