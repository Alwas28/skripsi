<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStage;
use App\Enums\ExamKind;
use App\Enums\SupervisionStatus;
use App\Models\SupervisionSession;
use App\Models\Thesis;
use App\Notifications\ThesisUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Mahasiswa's side of supervision (blueprint.pdf §6).
 */
class MySupervisionController extends Controller
{
    private const FILE_RULES = ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'];

    public function index(Request $request): View
    {
        $thesis = $request->user()->studentRecord?->thesis;

        return view('mahasiswa.supervision.index', [
            'thesis' => $thesis,
            'kind' => $thesis ? ExamKind::supervisableFor($thesis->status) : null,
            'supervisors' => $thesis ? $thesis->approvedSupervisors() : collect(),
            'sessions' => $thesis
                ? $thesis->supervisionSessions()
                    ->with(['lecturer', 'files.documentVersion', 'notes.user', 'revisions.documentVersion'])
                    ->latest('id')
                    ->get()
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $thesis = $this->ownThesis($request);
        $kind = ExamKind::supervisableFor($thesis->status);
        abort_unless($kind, 403, 'Bimbingan tidak tersedia pada status skripsi saat ini.');

        $data = $request->validate([
            'lecturer_id' => ['required', Rule::in($thesis->approvedSupervisors()->pluck('lecturer_id'))],
            'chapter' => ['required', 'string', 'max:100'],
            'topic' => ['required', 'string', 'max:255'],
            'session_date' => ['required', 'date'],
            'file' => self::FILE_RULES,
        ]);

        $session = DB::transaction(function () use ($request, $thesis, $kind, $data) {
            $this->moveThesisIntoSupervision($thesis, $kind, $request);

            $session = $thesis->supervisionSessions()->create([
                'lecturer_id' => $data['lecturer_id'],
                'phase' => $kind,
                'chapter' => $data['chapter'],
                'topic' => $data['topic'],
                'session_date' => $data['session_date'],
                'status' => SupervisionStatus::Requested,
            ]);

            $document = $thesis->documents()->firstOrCreate([
                'stage' => DocumentStage::Supervision->value,
                'name' => "Bimbingan #{$session->id} - {$data['chapter']}",
            ]);

            $session->files()->create([
                'document_version_id' => $document->addVersion($request->file('file'), $request->user())->id,
            ]);

            return $session;
        });

        ThesisUpdate::send(
            $session->lecturer->user,
            'Bimbingan baru',
            "{$thesis->student->name} mengajukan bimbingan {$session->chapter}: {$session->topic}.",
            route('dosen.supervision.show', $session, false),
        );

        return Redirect::route('mahasiswa.supervision')->with('status', 'Bimbingan berhasil diajukan.');
    }

    public function revise(Request $request, SupervisionSession $supervisionSession): RedirectResponse
    {
        $thesis = $this->ownThesis($request);
        abort_unless($supervisionSession->thesis_id === $thesis->id, 403);
        abort_unless($supervisionSession->status === SupervisionStatus::RevisionRequired, 403, 'Bimbingan ini tidak sedang menunggu revisi.');

        $data = $request->validate([
            'file' => self::FILE_RULES,
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($request, $supervisionSession, $data) {
            $document = $supervisionSession->files()->firstOrFail()->documentVersion->document;

            $supervisionSession->revisions()->create([
                'document_version_id' => $document->addVersion($request->file('file'), $request->user())->id,
                'notes' => $data['notes'] ?? null,
            ]);
            $supervisionSession->update(['status' => SupervisionStatus::Resubmitted]);
        });

        ThesisUpdate::send(
            $supervisionSession->lecturer->user,
            'Revisi bimbingan masuk',
            "{$thesis->student->name} mengirim revisi {$supervisionSession->chapter}.",
            route('dosen.supervision.show', $supervisionSession, false),
        );

        return Redirect::route('mahasiswa.supervision')->with('status', 'Revisi berhasil dikirim.');
    }

    /**
     * The first session of a stage moves the thesis into its supervision
     * status; after a failed exam it starts bimbingan ulang.
     */
    private function moveThesisIntoSupervision(Thesis $thesis, ExamKind $kind, Request $request): void
    {
        $target = in_array($thesis->status, [$kind->failedStatus(), $kind->resupervisionStatus()], true)
            ? $kind->resupervisionStatus()
            : $kind->supervisionStatus();

        if ($thesis->status !== $target) {
            $thesis->transitionTo($target, $request->user(), 'Bimbingan dimulai.');
        }
    }

    private function ownThesis(Request $request): Thesis
    {
        $thesis = $request->user()->studentRecord?->thesis;
        abort_unless($thesis, 403);

        return $thesis;
    }
}
