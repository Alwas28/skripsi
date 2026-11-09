<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStage;
use App\Enums\ExamDecision;
use App\Enums\ExamRevisionStatus;
use App\Models\ExamRevision;
use App\Notifications\ThesisUpdate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exam revision items (blueprint.pdf §13): the student submits, the examiner
 * who wrote the item validates it.
 */
class ExamRevisionController extends Controller
{
    public function studentIndex(Request $request): View
    {
        $thesis = $request->user()->studentRecord?->thesis;

        return view('mahasiswa.revisions.index', [
            'revisions' => $thesis
                ? $this->active()
                    ->whereHas('attempt.exam', fn (Builder $exam) => $exam->where('thesis_id', $thesis->id))
                    ->with(['lecturer', 'attempt.exam.type', 'documentVersion', 'validations'])
                    ->latest('id')
                    ->get()
                : collect(),
        ]);
    }

    public function submit(Request $request, ExamRevision $examRevision): RedirectResponse
    {
        $thesis = $request->user()->studentRecord?->thesis;
        abort_unless($thesis && $examRevision->attempt->exam->thesis_id === $thesis->id, 403);
        $this->ensureOpenForWork($examRevision);
        abort_unless($examRevision->status === ExamRevisionStatus::Open, 403, 'Item revisi ini tidak sedang terbuka.');

        $request->validate(['file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240']]);

        DB::transaction(function () use ($request, $thesis, $examRevision) {
            $version = $thesis->documents()
                ->firstOrCreate([
                    'stage' => DocumentStage::Revision->value,
                    'name' => "Revisi {$examRevision->attempt->kind()->label()} #{$examRevision->id} - {$examRevision->chapter}",
                ])
                ->addVersion($request->file('file'), $request->user());

            $examRevision->update(['document_version_id' => $version->id, 'status' => ExamRevisionStatus::Submitted]);
        });

        ThesisUpdate::send(
            $examRevision->lecturer->user,
            'Revisi ujian menunggu validasi',
            "{$thesis->student->name} mengirim revisi {$examRevision->chapter}.",
            route('dosen.revisions', absolute: false),
        );

        return Redirect::route('mahasiswa.revisions')->with('status', 'Revisi berhasil dikirim.');
    }

    public function lecturerIndex(Request $request): View
    {
        $lecturer = $request->user()->lecturerRecord;

        if (! $lecturer) {
            return view('dosen.advisor-titles.not-linked', ['pageTitle' => 'Revisi']);
        }

        return view('dosen.revisions.index', [
            'revisions' => $this->active()
                ->whereBelongsTo($lecturer)
                ->with(['attempt.exam.thesis.student', 'attempt.exam.type', 'documentVersion'])
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function validateRevision(Request $request, ExamRevision $examRevision): RedirectResponse
    {
        $this->ensureOwnItem($request, $examRevision);
        $this->ensureOpenForWork($examRevision);
        abort_unless($examRevision->status === ExamRevisionStatus::Submitted, 403, 'Item revisi ini tidak sedang menunggu validasi.');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'revision'])],
            'notes' => ['nullable', 'required_if:decision,revision', 'string'],
        ]);

        $approved = $data['decision'] === 'approved';
        $attempt = $examRevision->attempt;
        $student = $attempt->exam->thesis->student;

        $completed = DB::transaction(function () use ($request, $examRevision, $attempt, $data, $approved) {
            $examRevision->validations()->create([
                'validated_by' => $request->user()->id,
                'decision' => $data['decision'],
                'notes' => $data['notes'] ?? null,
            ]);
            $examRevision->update(['status' => $approved ? ExamRevisionStatus::Approved : ExamRevisionStatus::Open]);

            return $approved && $attempt->completeRevisionsIfDone($request->user());
        });

        ThesisUpdate::send(
            $student->user,
            $completed ? 'Revisi selesai' : ($approved ? 'Revisi disetujui' : 'Revisi perlu diperbaiki'),
            $completed ? "Seluruh revisi {$attempt->kind()->label()} telah disetujui." : "Item {$examRevision->chapter}: ".($approved ? 'disetujui.' : $data['notes']),
            route('mahasiswa.revisions', absolute: false),
        );

        return Redirect::route('dosen.revisions')->with('status', 'Validasi revisi tersimpan.');
    }

    public function download(Request $request, ExamRevision $examRevision): StreamedResponse
    {
        $this->ensureOwnItem($request, $examRevision);
        abort_unless($examRevision->documentVersion, 404);

        return Storage::disk('local')->download($examRevision->documentVersion->file_path, $examRevision->documentVersion->original_name);
    }

    /**
     * Items only exist for the student once the exam was decided as Revisi.
     *
     * @return Builder<ExamRevision>
     */
    private function active(): Builder
    {
        return ExamRevision::whereHas('attempt.result', fn (Builder $result) => $result->where('decision', ExamDecision::Revision));
    }

    private function ensureOwnItem(Request $request, ExamRevision $revision): void
    {
        $lecturer = $request->user()->lecturerRecord;
        abort_unless($lecturer && $revision->lecturer_id === $lecturer->id, 403, 'Item revisi ini bukan milik Anda.');
    }

    private function ensureOpenForWork(ExamRevision $revision): void
    {
        $attempt = $revision->attempt;
        abort_unless($attempt->result?->decision === ExamDecision::Revision, 403, 'Ujian ini tidak dalam tahap revisi.');
        abort_unless($attempt->exam->thesis->status === $attempt->kind()->revisionStatus(), 403, 'Tahap revisi ujian ini sudah selesai.');
    }
}
