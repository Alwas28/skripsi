<?php

namespace App\Http\Controllers;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamRevisionStatus;
use App\Enums\SupervisorApprovalStatus;
use App\Models\ExamAttempt;
use App\Models\ExamExaminer;
use App\Models\Lecturer;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Examiner and supervisor grading (blueprint.pdf §12).
 */
class LecturerExamController extends Controller
{
    public function examinees(Request $request): View
    {
        $lecturer = $request->user()->lecturerRecord;

        if (! $lecturer) {
            return view('dosen.advisor-titles.not-linked', ['pageTitle' => 'Mahasiswa Penguji']);
        }

        return view('shared.table', [
            'title' => 'Mahasiswa Penguji',
            'columns' => ['Mahasiswa', 'Ujian', 'Peran', 'Jadwal', 'Status'],
            'empty' => 'Belum ada mahasiswa yang Anda uji.',
            'rows' => ExamExaminer::whereBelongsTo($lecturer)
                ->with(['attempt.exam.thesis.student', 'attempt.exam.type', 'attempt.schedule'])
                ->latest('id')
                ->paginate(15)
                ->through(fn (ExamExaminer $examiner) => [
                    $examiner->attempt->exam->thesis->student->name,
                    $examiner->attempt->kind()->label(),
                    "Penguji {$examiner->position}",
                    $examiner->attempt->schedule ? $examiner->attempt->schedule->exam_date->format('d M Y').' '.$examiner->attempt->schedule->timeRange() : null,
                    ['badge' => $examiner->attempt->status->label(), 'class' => $examiner->attempt->status->badgeClass()],
                ]),
        ]);
    }

    public function index(Request $request): View
    {
        $lecturer = $request->user()->lecturerRecord;

        if (! $lecturer) {
            return view('dosen.advisor-titles.not-linked', ['pageTitle' => 'Penilaian']);
        }

        return view('shared.table', [
            'title' => 'Penilaian',
            'tableTitle' => 'Ujian yang Perlu Dinilai',
            'columns' => ['Mahasiswa', 'Ujian', 'Jadwal', 'Status Nilai', ''],
            'empty' => 'Belum ada ujian untuk dinilai.',
            'rows' => ExamAttempt::where('status', ExamAttemptStatus::Scheduled)
                ->where(fn (Builder $query) => $query
                    ->whereHas('examiners', fn (Builder $examiners) => $examiners->where('lecturer_id', $lecturer->id))
                    ->orWhereHas('exam.thesis.supervisors', fn (Builder $supervisors) => $supervisors
                        ->where('lecturer_id', $lecturer->id)
                        ->where('status', SupervisorApprovalStatus::Approved)))
                ->with(['exam.thesis.student', 'exam.type', 'schedule'])
                ->withExists(['assessments as is_graded' => fn (Builder $assessments) => $assessments->where('lecturer_id', $lecturer->id)])
                ->latest('id')
                ->paginate(15)
                ->through(fn (ExamAttempt $attempt) => [
                    $attempt->exam->thesis->student->name,
                    $attempt->kind()->label(),
                    $attempt->schedule->exam_date->format('d M Y').' '.$attempt->schedule->timeRange(),
                    $attempt->is_graded ? ['badge' => 'Sudah dinilai', 'class' => 'badge-green'] : ['badge' => 'Belum dinilai', 'class' => 'badge-amber'],
                    ['link' => route('dosen.assessment.show', $attempt), 'text' => 'Nilai'],
                ]),
        ]);
    }

    public function show(Request $request, ExamAttempt $examAttempt): View
    {
        $roles = $this->ownRoles($request, $examAttempt);
        abort_unless($roles !== [], 403, 'Anda bukan penguji atau pembimbing ujian ini.');

        $examAttempt->load(['exam.thesis.student', 'exam.thesis.title', 'schedule.room', 'assessments.details']);
        $lecturer = $request->user()->lecturerRecord;

        return view('dosen.assessment.show', [
            'attempt' => $examAttempt,
            'roles' => $roles,
            'components' => Setting::examRules($examAttempt->exam->thesis->student->study_program_id, $examAttempt->kind())['components'],
            'isOpen' => $this->isOpenForGrading($examAttempt),
            'revisionLines' => $examAttempt->revisions()->whereBelongsTo($lecturer)->get()
                ->map(fn ($revision) => "{$revision->chapter}: {$revision->notes}")->implode("\n"),
        ]);
    }

    public function store(Request $request, ExamAttempt $examAttempt): RedirectResponse
    {
        $roles = $this->ownRoles($request, $examAttempt);
        abort_unless($roles !== [], 403, 'Anda bukan penguji atau pembimbing ujian ini.');
        abort_unless($this->isOpenForGrading($examAttempt), 403, 'Penilaian dibuka pada hari ujian dan ditutup setelah keputusan.');

        $lecturer = $request->user()->lecturerRecord;
        $role = $request->input('assessor_role');
        $isExaminer = str_starts_with((string) $role, 'examiner_');
        $components = Setting::examRules($examAttempt->exam->thesis->student->study_program_id, $examAttempt->kind())['components'];

        $data = $request->validate([
            'assessor_role' => ['required', Rule::in($roles)],
            'notes' => ['nullable', 'string'],
            'score' => [Rule::requiredIf(! $isExaminer), 'nullable', 'numeric', 'min:0', 'max:100'],
            'scores' => [Rule::requiredIf($isExaminer), 'array', 'size:'.($isExaminer ? count($components) : 0)],
            'scores.*' => ['required', 'numeric', 'min:0', 'max:100'],
            'revisions' => ['nullable', 'string'],
        ]);

        $revisions = $isExaminer ? $this->parseRevisions($data['revisions'] ?? '') : [];
        $details = $isExaminer
            ? array_map(fn (array $component, int $index) => [...$component, 'score' => (float) $data['scores'][$index]], $components, array_keys($components))
            : [];
        $score = $isExaminer
            ? round(array_sum(array_map(fn (array $detail) => $detail['score'] * $detail['weight'] / 100, $details)), 2)
            : (float) $data['score'];

        DB::transaction(function () use ($examAttempt, $lecturer, $data, $isExaminer, $details, $score, $revisions) {
            $assessment = $examAttempt->assessments()->updateOrCreate(
                ['assessor_role' => $data['assessor_role']],
                ['lecturer_id' => $lecturer->id, 'score' => $score, 'notes' => $data['notes'] ?? null],
            );

            $assessment->details()->delete();

            foreach ($details as $detail) {
                $assessment->details()->create([
                    'component' => $detail['name'],
                    'weight' => $detail['weight'],
                    'score' => $detail['score'],
                ]);
            }

            if ($isExaminer) {
                $examAttempt->revisions()->whereBelongsTo($lecturer)->get()->each->delete();

                foreach ($revisions as [$chapter, $notes]) {
                    $examAttempt->revisions()->create([
                        'lecturer_id' => $lecturer->id,
                        'chapter' => $chapter,
                        'notes' => $notes,
                        'status' => ExamRevisionStatus::Open,
                    ]);
                }
            }
        });

        return Redirect::route('dosen.assessment.show', $examAttempt)->with('status', 'Penilaian tersimpan.');
    }

    /**
     * @return list<string> the assessor roles this user holds on the attempt
     */
    private function ownRoles(Request $request, ExamAttempt $attempt): array
    {
        $lecturer = $request->user()->lecturerRecord;

        return $lecturer instanceof Lecturer ? array_keys($attempt->requiredAssessors(), $lecturer->id, true) : [];
    }

    private function isOpenForGrading(ExamAttempt $attempt): bool
    {
        return $attempt->status === ExamAttemptStatus::Scheduled
            && $attempt->schedule
            && $attempt->schedule->exam_date->lte(today());
    }

    /**
     * Each line "Bab II: Perbaiki landasan teori" becomes one revision item.
     *
     * @return list<array{string, string}>
     */
    private function parseRevisions(string $text): array
    {
        $items = [];

        foreach (array_filter(array_map('trim', preg_split('/\R/', $text))) as $line) {
            if (! preg_match('/^([^:]{1,100}):\s*(.+)$/', $line, $match)) {
                throw ValidationException::withMessages(['revisions' => 'Tulis satu revisi per baris dengan format Bab: catatan.']);
            }

            $items[] = [trim($match[1]), trim($match[2])];
        }

        return $items;
    }
}
