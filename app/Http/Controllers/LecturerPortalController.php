<?php

namespace App\Http\Controllers;

use App\Enums\SupervisorApprovalStatus;
use App\Models\ExamExaminer;
use App\Models\ExamSchedule;
use App\Models\SupervisionSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LecturerPortalController extends Controller
{
    public function schedule(Request $request): View
    {
        $lecturer = $request->user()->lecturerRecord;

        if (! $lecturer) {
            return view('dosen.advisor-titles.not-linked', ['pageTitle' => 'Jadwal Ujian']);
        }

        return view('shared.table', [
            'title' => 'Jadwal Ujian',
            'columns' => ['Tanggal', 'Waktu', 'Ruang', 'Mahasiswa', 'Ujian'],
            'empty' => 'Belum ada jadwal ujian.',
            'rows' => ExamSchedule::whereHas('attempt', fn (Builder $attempt) => $attempt
                ->whereHas('examiners', fn (Builder $examiners) => $examiners->where('lecturer_id', $lecturer->id))
                ->orWhereHas('exam.thesis.supervisors', fn (Builder $supervisors) => $supervisors
                    ->where('lecturer_id', $lecturer->id)
                    ->where('status', SupervisorApprovalStatus::Approved)))
                ->with(['room', 'attempt.exam.thesis.student', 'attempt.exam.type'])
                ->orderByDesc('exam_date')
                ->paginate(15)
                ->through(fn (ExamSchedule $schedule) => [
                    $schedule->exam_date->format('d M Y'),
                    $schedule->timeRange(),
                    $schedule->room->name,
                    $schedule->attempt->exam->thesis->student->name,
                    $schedule->attempt->kind()->label(),
                ]),
        ]);
    }

    public function history(Request $request): View
    {
        $lecturer = $request->user()->lecturerRecord;

        if (! $lecturer) {
            return view('dosen.advisor-titles.not-linked', ['pageTitle' => 'Riwayat']);
        }

        $sessions = SupervisionSession::whereBelongsTo($lecturer)
            ->with('thesis.student')
            ->get()
            ->map(fn (SupervisionSession $session) => [
                'at' => $session->session_date,
                'row' => [
                    $session->session_date->format('d M Y'),
                    $session->thesis->student->name,
                    'Bimbingan',
                    "{$session->phase->supervisionLabel()}: {$session->chapter}",
                    ['badge' => $session->status->label(), 'class' => $session->status->badgeClass()],
                ],
            ]);

        $exams = ExamExaminer::whereBelongsTo($lecturer)
            ->with(['attempt.exam.thesis.student', 'attempt.exam.type', 'attempt.schedule', 'attempt.result'])
            ->get()
            ->map(fn (ExamExaminer $examiner) => [
                'at' => $examiner->attempt->schedule?->exam_date ?? $examiner->created_at,
                'row' => [
                    ($examiner->attempt->schedule?->exam_date ?? $examiner->created_at)->format('d M Y'),
                    $examiner->attempt->exam->thesis->student->name,
                    "Penguji {$examiner->position}",
                    "{$examiner->attempt->kind()->label()} #{$examiner->attempt->attempt_number}",
                    $examiner->attempt->result
                        ? ['badge' => $examiner->attempt->result->decision->label(), 'class' => $examiner->attempt->result->decision->badgeClass()]
                        : ['badge' => $examiner->attempt->status->label(), 'class' => $examiner->attempt->status->badgeClass()],
                ],
            ]);

        return view('shared.table', [
            'title' => 'Riwayat',
            'tableTitle' => 'Riwayat Bimbingan dan Ujian',
            'columns' => ['Tanggal', 'Mahasiswa', 'Peran', 'Kegiatan', 'Status'],
            'empty' => 'Belum ada riwayat.',
            'rows' => $sessions->toBase()->merge($exams)->sortByDesc('at')->pluck('row')->values(),
        ]);
    }
}
