<?php

namespace App\Http\Controllers;

use App\Enums\ThesisStatus;
use App\Models\Thesis;
use App\Notifications\ThesisUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * First approval step of a student's title, by their Dosen PA. Approved
 * titles move on to the Kaprodi in TitleSubmissionController.
 */
class AdvisorTitleApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $lecturer = $request->user()->lecturerRecord;

        if (! $lecturer) {
            return view('dosen.advisor-titles.not-linked');
        }

        $status = $request->query('status');
        $submittedToAdvisor = Thesis::where('status', '!=', ThesisStatus::Draft)
            ->whereHas('student', fn ($query) => $query->where('academic_advisor_id', $lecturer->id));

        return view('dosen.advisor-titles.index', [
            'theses' => (clone $submittedToAdvisor)
                ->with(['student.studyProgram', 'title'])
                ->when($status, fn ($query) => $query->where('status', $status))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'statusFilter' => $status,
            'pendingCount' => (clone $submittedToAdvisor)->where('status', ThesisStatus::Submitted)->count(),
        ]);
    }

    public function show(Request $request, Thesis $thesis): View
    {
        $this->ensureAdvisor($request, $thesis);

        $thesis->load(['student.studyProgram', 'title.reviewer', 'statusHistories.changedBy']);

        return view('dosen.advisor-titles.show', [
            'thesis' => $thesis,
            'reviewable' => $thesis->status === ThesisStatus::Submitted,
        ]);
    }

    public function approve(Request $request, Thesis $thesis): RedirectResponse
    {
        $this->ensureAdvisor($request, $thesis);
        $this->ensureReviewable($thesis);

        $thesis->title->update([
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => null,
        ]);
        $thesis->transitionTo(ThesisStatus::TitleReview, $request->user(), 'Disetujui Dosen PA.');

        ThesisUpdate::send(
            $thesis->student->user,
            'Judul disetujui Dosen PA',
            "Judul \"{$thesis->title->title}\" diteruskan ke Kaprodi.",
            route('mahasiswa.title-submission', absolute: false),
        );
        ThesisUpdate::send(
            $thesis->prodiStaffWithRole('kaprodi'),
            'Judul menunggu persetujuan Kaprodi',
            "{$thesis->student->name} mengajukan judul \"{$thesis->title->title}\" dan sudah disetujui Dosen PA.",
            route('prodi.title-submissions.show', $thesis, false),
        );

        return Redirect::route('dosen.advisor-titles.index')->with('status', "Judul \"{$thesis->title->title}\" disetujui dan diteruskan ke Kaprodi.");
    }

    public function requestRevision(Request $request, Thesis $thesis): RedirectResponse
    {
        $this->ensureAdvisor($request, $thesis);
        $this->ensureReviewable($thesis);

        $data = $request->validate([
            'review_notes' => ['required', 'string'],
        ]);

        $thesis->title->update([
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'],
        ]);
        $thesis->transitionTo(ThesisStatus::RevisionRequired, $request->user(), $data['review_notes']);

        ThesisUpdate::send(
            $thesis->student->user,
            'Judul perlu revisi',
            'Dosen PA meminta perbaikan pada pengajuan judul Anda.',
            route('mahasiswa.title-submission', absolute: false),
        );

        return Redirect::route('dosen.advisor-titles.index')->with('status', "Revisi diminta untuk \"{$thesis->title->title}\".");
    }

    public function downloadProposal(Request $request, Thesis $thesis): StreamedResponse
    {
        $this->ensureAdvisor($request, $thesis);

        $thesis->loadMissing('title');
        abort_unless($thesis->title?->proposal_path, 404);

        $extension = pathinfo($thesis->title->proposal_path, PATHINFO_EXTENSION);
        $name = Str::slug($thesis->title->title ?: 'proposal').'.'.$extension;

        return Storage::disk('local')->download($thesis->title->proposal_path, $name);
    }

    private function ensureAdvisor(Request $request, Thesis $thesis): void
    {
        $lecturer = $request->user()->lecturerRecord;

        abort_unless(
            $lecturer && $thesis->status !== ThesisStatus::Draft && $thesis->student->academicAdvisor?->is($lecturer),
            403,
            'Anda bukan Dosen PA mahasiswa ini.'
        );
    }

    private function ensureReviewable(Thesis $thesis): void
    {
        abort_unless($thesis->status === ThesisStatus::Submitted, 403, 'Pengajuan ini tidak sedang menunggu persetujuan Dosen PA.');
    }
}
