<?php

namespace App\Http\Controllers;

use App\Models\ExamAttempt;
use App\Models\Thesis;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The student's read-only overviews of their own thesis.
 */
class StudentPortalController extends Controller
{
    public function thesis(Request $request): View
    {
        $thesis = $request->user()->studentRecord?->thesis;
        $thesis?->load(['title', 'statusHistories.changedBy', 'supervisors.lecturer']);

        return view('mahasiswa.thesis', ['thesis' => $thesis]);
    }

    public function schedule(Request $request): View
    {
        return view('shared.table', [
            'title' => 'Jadwal',
            'tableTitle' => 'Jadwal Ujian',
            'columns' => ['Ujian', 'Percobaan', 'Tanggal', 'Waktu', 'Ruang', 'Penguji'],
            'empty' => 'Belum ada jadwal ujian.',
            'rows' => $this->attempts($request)
                ->filter(fn (ExamAttempt $attempt) => $attempt->schedule)
                ->map(fn (ExamAttempt $attempt) => [
                    $attempt->kind()->label(),
                    $attempt->attempt_number,
                    $attempt->schedule->exam_date->format('d M Y'),
                    $attempt->schedule->timeRange(),
                    $attempt->schedule->room->name,
                    $attempt->examiners->map(fn ($examiner) => $examiner->lecturer->full_name)->implode(', '),
                ]),
        ]);
    }

    public function grades(Request $request): View
    {
        return view('shared.table', [
            'title' => 'Nilai',
            'tableTitle' => 'Rekap Nilai Ujian',
            'columns' => ['Ujian', 'Percobaan', 'Nilai Akhir', 'Keputusan', 'Tanggal'],
            'empty' => 'Belum ada nilai ujian.',
            'rows' => $this->attempts($request)
                ->filter(fn (ExamAttempt $attempt) => $attempt->result)
                ->map(fn (ExamAttempt $attempt) => [
                    $attempt->kind()->label(),
                    $attempt->attempt_number,
                    number_format($attempt->result->final_score, 2),
                    ['badge' => $attempt->result->decision->label(), 'class' => $attempt->result->decision->badgeClass()],
                    $attempt->result->decided_at->format('d M Y'),
                ]),
        ]);
    }

    public function minutes(Request $request): View
    {
        return view('shared.table', [
            'title' => 'Berita Acara',
            'columns' => ['Ujian', 'Percobaan', 'Keputusan', 'Dokumen'],
            'empty' => 'Belum ada berita acara.',
            'rows' => $this->attempts($request)
                ->filter(fn (ExamAttempt $attempt) => $attempt->result)
                ->map(fn (ExamAttempt $attempt) => [
                    $attempt->kind()->label(),
                    $attempt->attempt_number,
                    ['badge' => $attempt->result->decision->label(), 'class' => $attempt->result->decision->badgeClass()],
                    ['link' => route('minutes.show', $attempt), 'text' => 'Lihat Berita Acara'],
                ]),
        ]);
    }

    /**
     * @return Collection<int, ExamAttempt>
     */
    private function attempts(Request $request): Collection
    {
        $thesis = $request->user()->studentRecord?->thesis;

        if (! $thesis instanceof Thesis) {
            return collect();
        }

        return ExamAttempt::whereHas('exam', fn (Builder $exam) => $exam->where('thesis_id', $thesis->id))
            ->with(['exam.type', 'schedule.room', 'examiners.lecturer', 'result'])
            ->latest('id')
            ->get()
            ->toBase();
    }
}
