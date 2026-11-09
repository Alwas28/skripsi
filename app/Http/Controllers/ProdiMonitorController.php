<?php

namespace App\Http\Controllers;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamDecision;
use App\Enums\ExamKind;
use App\Enums\ThesisStatus;
use App\Models\ExamAttempt;
use App\Models\ExamRevision;
use App\Models\Lecturer;
use App\Models\Setting;
use App\Models\SupervisionSession;
use App\Models\Thesis;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Read-only prodi overviews, limited to the user's own study program.
 */
class ProdiMonitorController extends Controller
{
    private const REPORT_TABS = ['mahasiswa' => 'Laporan Mahasiswa', 'dosen' => 'Laporan Dosen', 'ujian' => 'Laporan Ujian', 'prodi' => 'Laporan Prodi'];

    public function supervision(Request $request): View
    {
        return view('shared.table', [
            'title' => 'Bimbingan',
            'tableTitle' => 'Aktivitas Bimbingan Mahasiswa',
            'needsStudyProgram' => true,
            'columns' => ['Mahasiswa', 'Pembimbing', 'Tahap', 'Bab', 'Topik', 'Tanggal', 'Status'],
            'empty' => 'Belum ada sesi bimbingan.',
            'rows' => SupervisionSession::whereHas('thesis', fn ($query) => $query->visibleToProdiStaff($request->user()))
                ->with(['thesis.student', 'lecturer'])
                ->latest('id')
                ->paginate(20)
                ->through(fn (SupervisionSession $session) => [
                    $session->thesis->student->name,
                    $session->lecturer->full_name,
                    $session->phase->supervisionLabel(),
                    $session->chapter,
                    $session->topic,
                    $session->session_date->format('d M Y'),
                    ['badge' => $session->status->label(), 'class' => $session->status->badgeClass()],
                ]),
        ]);
    }

    public function schedule(Request $request): View
    {
        return view('shared.table', [
            'title' => 'Jadwal',
            'tableTitle' => 'Jadwal Ujian',
            'needsStudyProgram' => true,
            'columns' => ['Tanggal', 'Waktu', 'Ruang', 'Mahasiswa', 'Ujian', 'Penguji', ''],
            'empty' => 'Belum ada jadwal ujian.',
            'rows' => $this->attemptQuery($request)
                ->whereHas('schedule')
                ->with(['schedule.room', 'examiners.lecturer'])
                ->join('exam_schedules', 'exam_schedules.exam_attempt_id', '=', 'exam_attempts.id')
                ->orderByDesc('exam_schedules.exam_date')
                ->select('exam_attempts.*')
                ->paginate(20)
                ->through(fn (ExamAttempt $attempt) => [
                    $attempt->schedule->exam_date->format('d M Y'),
                    $attempt->schedule->timeRange(),
                    $attempt->schedule->room->name,
                    $attempt->exam->thesis->student->name,
                    $attempt->kind()->label(),
                    $attempt->examiners->map(fn ($examiner) => $examiner->lecturer->full_name)->implode(', '),
                    ['link' => route('prodi.exams.attempt', $attempt), 'text' => 'Kelola'],
                ]),
        ]);
    }

    public function examiners(Request $request): View
    {
        return view('shared.table', [
            'title' => 'Penguji',
            'tableTitle' => 'Penugasan Penguji',
            'needsStudyProgram' => true,
            'columns' => ['Mahasiswa', 'Ujian', 'Penguji 1', 'Penguji 2', 'Status', ''],
            'empty' => 'Belum ada ujian yang terverifikasi.',
            'rows' => $this->attemptQuery($request)
                ->whereIn('status', [ExamAttemptStatus::Verified, ExamAttemptStatus::Scheduled, ExamAttemptStatus::Decided])
                ->with('examiners.lecturer')
                ->latest('id')
                ->paginate(20)
                ->through(fn (ExamAttempt $attempt) => [
                    $attempt->exam->thesis->student->name,
                    "{$attempt->kind()->label()} #{$attempt->attempt_number}",
                    $attempt->examiners->firstWhere('position', 1)?->lecturer->full_name,
                    $attempt->examiners->firstWhere('position', 2)?->lecturer->full_name,
                    ['badge' => $attempt->status->label(), 'class' => $attempt->status->badgeClass()],
                    ['link' => route('prodi.exams.attempt', $attempt), 'text' => 'Kelola'],
                ]),
        ]);
    }

    public function assessment(Request $request): View
    {
        return view('shared.table', [
            'title' => 'Penilaian',
            'tableTitle' => 'Rekap Nilai Ujian',
            'needsStudyProgram' => true,
            'columns' => ['Mahasiswa', 'Ujian', 'Penilaian Masuk', 'Nilai Akhir', 'Keputusan', ''],
            'empty' => 'Belum ada ujian yang dijadwalkan.',
            'rows' => $this->attemptQuery($request)
                ->whereIn('status', [ExamAttemptStatus::Scheduled, ExamAttemptStatus::Decided])
                ->with(['assessments', 'examiners', 'exam.thesis.supervisors'])
                ->latest('id')
                ->paginate(20)
                ->through(fn (ExamAttempt $attempt) => [
                    $attempt->exam->thesis->student->name,
                    "{$attempt->kind()->label()} #{$attempt->attempt_number}",
                    $attempt->assessments->count().' dari '.count($attempt->requiredAssessors()),
                    $attempt->result ? number_format($attempt->result->final_score, 2) : null,
                    $attempt->result ? ['badge' => $attempt->result->decision->label(), 'class' => $attempt->result->decision->badgeClass()] : null,
                    ['link' => route('prodi.exams.attempt', $attempt), 'text' => 'Detail'],
                ]),
        ]);
    }

    public function revisions(Request $request): View
    {
        return view('shared.table', [
            'title' => 'Revisi',
            'tableTitle' => 'Status Revisi Ujian',
            'needsStudyProgram' => true,
            'columns' => ['Mahasiswa', 'Ujian', 'Bab', 'Penguji', 'Catatan', 'Status'],
            'empty' => 'Belum ada revisi ujian.',
            'rows' => ExamRevision::whereHas('attempt.result', fn (Builder $result) => $result->where('decision', ExamDecision::Revision))
                ->whereHas('attempt.exam.thesis', fn ($thesis) => $thesis->visibleToProdiStaff($request->user()))
                ->with(['lecturer', 'attempt.exam.thesis.student', 'attempt.exam.type'])
                ->latest('id')
                ->paginate(20)
                ->through(fn (ExamRevision $revision) => [
                    $revision->attempt->exam->thesis->student->name,
                    $revision->attempt->kind()->label(),
                    $revision->chapter,
                    $revision->lecturer->full_name,
                    $revision->notes,
                    ['badge' => $revision->status->label(), 'class' => $revision->status->badgeClass()],
                ]),
        ]);
    }

    /**
     * Early warning of students in trouble (blueprint.pdf §20), worst first.
     */
    public function monitoring(Request $request): View
    {
        $rows = Thesis::visibleToProdiStaff($request->user())
            ->where('status', '!=', ThesisStatus::Completed)
            ->with(['student', 'title'])
            ->withMax('supervisionSessions as last_session_at', 'session_date')
            ->get()
            ->map(function (Thesis $thesis) {
                $indicators = $this->indicators($thesis);

                return ['count' => count($indicators), 'row' => [
                    $thesis->student->name,
                    $thesis->status->label(),
                    $thesis->last_session_at ? Carbon::parse($thesis->last_session_at)->format('d M Y') : null,
                    $thesis->created_at->diffInDays(now(), true) >= 1 ? (int) $thesis->created_at->diffInDays(now(), true).' hari' : 'Hari ini',
                    $indicators === []
                        ? ['badge' => 'Progress normal', 'class' => 'badge-green']
                        : ['badge' => implode(' · ', $indicators), 'class' => 'badge-red'],
                ]];
            })
            ->sortByDesc('count')
            ->pluck('row')
            ->values();

        return view('shared.table', [
            'title' => 'Monitoring',
            'tableTitle' => 'Mahasiswa Bermasalah',
            'needsStudyProgram' => true,
            'columns' => ['Mahasiswa', 'Status', 'Bimbingan Terakhir', 'Lama Pengerjaan', 'Indikator'],
            'empty' => 'Belum ada mahasiswa skripsi.',
            'rows' => $rows,
        ]);
    }

    public function reports(Request $request): View
    {
        $tab = array_key_exists((string) $request->query('tab'), self::REPORT_TABS) ? $request->query('tab') : 'mahasiswa';

        [$columns, $rows] = match ($tab) {
            'mahasiswa' => $this->studentReport($request->user()),
            'dosen' => $this->lecturerReport($request->user()),
            'ujian' => $this->examReport($request->user()),
            'prodi' => $this->programReport($request->user()),
        };

        return view('shared.table', [
            'title' => 'Laporan',
            'tableTitle' => self::REPORT_TABS[$tab],
            'needsStudyProgram' => true,
            'tabs' => collect(self::REPORT_TABS)->map(fn (string $label, string $key) => [
                'label' => $label,
                'url' => route('prodi.reports', ['tab' => $key]),
                'active' => $key === $tab,
            ])->values()->all(),
            'columns' => $columns,
            'rows' => $rows,
        ]);
    }

    /**
     * @return list<string>
     */
    private function indicators(Thesis $thesis): array
    {
        $rules = Setting::monitoringRules($thesis->student->study_program_id);
        $status = $thesis->status;
        $indicators = [];

        if (ExamKind::supervisableFor($status)) {
            $lastActivity = $thesis->last_session_at ? Carbon::parse($thesis->last_session_at) : $thesis->created_at;

            if ($lastActivity->diffInDays(now(), true) > $rules['no_supervision_days']) {
                $indicators[] = "Tidak bimbingan > {$rules['no_supervision_days']} hari";
            }
        }

        if (in_array($status, array_map(fn (ExamKind $kind) => $kind->revisionStatus(), ExamKind::cases()), true)) {
            $indicators[] = 'Revisi belum diselesaikan';
        }

        if (in_array($status, array_map(fn (ExamKind $kind) => $kind->failedStatus(), ExamKind::cases()), true)) {
            $indicators[] = 'Ujian tidak lulus';
        }

        if ($thesis->created_at->diffInDays(now(), true) > $rules['max_duration_days']) {
            $indicators[] = 'Melebihi batas waktu';
        }

        return $indicators;
    }

    /**
     * @return Builder<ExamAttempt>
     */
    private function attemptQuery(Request $request): Builder
    {
        return ExamAttempt::whereHas('exam.thesis', fn ($thesis) => $thesis->visibleToProdiStaff($request->user()))
            ->with(['exam.thesis.student', 'exam.type', 'result']);
    }

    /**
     * @return array{list<string>, Collection<int, array<int, mixed>>}
     */
    private function studentReport(User $user): array
    {
        $rows = Thesis::visibleToProdiStaff($user)
            ->with(['student', 'exams.type', 'exams.attempts.result'])
            ->withCount('supervisionSessions')
            ->latest()
            ->get()
            ->map(fn (Thesis $thesis) => [
                $thesis->student->nim,
                $thesis->student->name,
                $thesis->status->label(),
                $thesis->status->progressPercent().'%',
                (int) $thesis->created_at->diffInDays(now(), true).' hari',
                $thesis->supervision_sessions_count,
                $thesis->exams->flatMap(fn ($exam) => $exam->attempts->filter->result->map(
                    fn (ExamAttempt $attempt) => "{$exam->type->name} #{$attempt->attempt_number}: {$attempt->result->decision->label()}"
                ))->implode('; '),
            ]);

        return [['NIM', 'Nama', 'Status', 'Progres', 'Lama Pengerjaan', 'Jumlah Bimbingan', 'Riwayat Ujian'], $rows->toBase()];
    }

    /**
     * @return array{list<string>, Collection<int, array<int, mixed>>}
     */
    private function lecturerReport(User $user): array
    {
        $rows = Lecturer::when(! $user->isSuperAdmin(), fn ($query) => $query->where('study_program_id', $user->study_program_id ?? 0))
            ->withCount([
                'supervisorAssignments as supervisee_count' => fn ($query) => $query->where('status', 'approved'),
                'supervisorAssignments as active_load' => fn ($query) => $query->where('status', 'approved')
                    ->whereHas('thesis', fn ($thesis) => $thesis->where('status', '!=', ThesisStatus::Completed)),
                'examinerAssignments as examiner_count',
                'examinerAssignments as active_examiner_load' => fn ($query) => $query
                    ->whereHas('attempt', fn ($attempt) => $attempt->where('status', '!=', ExamAttemptStatus::Decided)),
                'supervisionSessions as session_count',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Lecturer $lecturer) => [
                $lecturer->full_name,
                $lecturer->supervisee_count,
                $lecturer->session_count,
                $lecturer->examiner_count,
                $lecturer->active_load,
                $lecturer->active_examiner_load,
            ]);

        return [['Dosen', 'Mahasiswa Bimbingan', 'Jumlah Bimbingan', 'Jumlah Penguji', 'Beban Pembimbing Aktif', 'Beban Penguji Aktif'], $rows->toBase()];
    }

    /**
     * @return array{list<string>, Collection<int, array<int, mixed>>}
     */
    private function examReport(User $user): array
    {
        $rows = collect(ExamKind::cases())->map(function (ExamKind $kind) use ($user) {
            $attempts = ExamAttempt::whereHas('exam', fn (Builder $exam) => $exam
                ->whereHas('type', fn (Builder $type) => $type->where('code', $kind->value))
                ->whereHas('thesis', fn ($thesis) => $thesis->visibleToProdiStaff($user)))
                ->where('status', ExamAttemptStatus::Decided)
                ->with('result')
                ->get();
            $count = fn (ExamDecision $decision) => $attempts->filter(fn (ExamAttempt $attempt) => $attempt->result->decision === $decision)->count();
            $average = $attempts->avg(fn (ExamAttempt $attempt) => $attempt->result->final_score);

            return [
                $kind->label(),
                $attempts->count(),
                $count(ExamDecision::Passed),
                $count(ExamDecision::Revision),
                $count(ExamDecision::Failed),
                $attempts->where('attempt_number', '>', 1)->count(),
                $average === null ? null : number_format($average, 2),
            ];
        });

        return [['Ujian', 'Total', 'Lulus', 'Lulus dengan Revisi', 'Tidak Lulus', 'Ujian Ulang', 'Rata-rata Nilai'], $rows];
    }

    /**
     * @return array{list<string>, Collection<int, array<int, mixed>>}
     */
    private function programReport(User $user): array
    {
        $failed = array_map(fn (ExamKind $kind) => $kind->failedStatus(), ExamKind::cases());

        $rows = Thesis::visibleToProdiStaff($user)
            ->with(['student', 'academicYear'])
            ->get()
            ->groupBy(fn (Thesis $thesis) => $thesis->academicYear ? "{$thesis->academicYear->name} {$thesis->academicYear->semester}" : 'Tanpa tahun akademik')
            ->map(function ($theses, string $year) use ($failed) {
                $late = $theses->filter(fn (Thesis $thesis) => $thesis->status !== ThesisStatus::Completed
                    && $thesis->created_at->diffInDays(now(), true) > Setting::monitoringRules($thesis->student->study_program_id)['max_duration_days']);

                return [
                    $year,
                    $theses->count(),
                    $theses->where('status', '!=', ThesisStatus::Completed)->count(),
                    $late->count(),
                    $theses->filter(fn (Thesis $thesis) => in_array($thesis->status, $failed, true))->count(),
                    $theses->where('status', ThesisStatus::Completed)->count(),
                ];
            })
            ->values();

        return [['Tahun Akademik', 'Total Mahasiswa', 'Aktif', 'Terlambat', 'Tidak Lulus', 'Selesai'], $rows->toBase()];
    }
}
