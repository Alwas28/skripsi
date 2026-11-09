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

/**
 * Kaprodi's final approval of student titles, after the Dosen PA has
 * approved them in AdvisorTitleApprovalController. Other prodi roles only
 * get read access (see RolePermissionSeeder).
 */
class TitleSubmissionController extends Controller
{
    private const REVIEWABLE_STATUSES = [ThesisStatus::TitleReview];

    public function index(Request $request): View
    {
        $status = $request->query('status');

        $theses = Thesis::visibleToProdiStaff($request->user())
            ->with(['student.studyProgram', 'title'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('prodi.title-submissions.index', [
            'theses' => $theses,
            'statusFilter' => $status,
            'pendingCount' => Thesis::visibleToProdiStaff($request->user())->whereIn('status', self::REVIEWABLE_STATUSES)->count(),
        ]);
    }

    public function show(Request $request, Thesis $thesis): View
    {
        $this->ensureVisible($request, $thesis);

        $thesis->load(['student.studyProgram', 'student.academicAdvisor', 'title.reviewer', 'statusHistories.changedBy']);

        return view('prodi.title-submissions.show', [
            'thesis' => $thesis,
            'reviewable' => in_array($thesis->status, self::REVIEWABLE_STATUSES, true),
        ]);
    }

    public function approve(Request $request, Thesis $thesis): RedirectResponse
    {
        $this->ensureVisible($request, $thesis);
        abort_unless(in_array($thesis->status, self::REVIEWABLE_STATUSES, true), 403, 'Pengajuan ini tidak sedang menunggu review.');

        $thesis->title->update([
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => null,
        ]);
        $thesis->transitionTo(ThesisStatus::TitleApproved, $request->user());

        ThesisUpdate::send(
            $thesis->student->user,
            'Judul disetujui',
            "Judul \"{$thesis->title->title}\" disetujui Kaprodi. Tahap berikutnya penetapan pembimbing.",
            route('mahasiswa.title-submission', absolute: false),
        );
        ThesisUpdate::send(
            $thesis->prodiStaffWithRole('admin_prodi'),
            'Judul siap ditetapkan pembimbing',
            "Judul {$thesis->student->name} sudah disetujui Kaprodi. Silakan usulkan pembimbing.",
            route('prodi.supervisors.show', $thesis, false),
        );

        return Redirect::route('prodi.title-submissions.index')->with('status', "Judul \"{$thesis->title->title}\" disetujui.");
    }

    public function requestRevision(Request $request, Thesis $thesis): RedirectResponse
    {
        $this->ensureVisible($request, $thesis);
        abort_unless(in_array($thesis->status, self::REVIEWABLE_STATUSES, true), 403, 'Pengajuan ini tidak sedang menunggu review.');

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
            'Kaprodi meminta perbaikan pada pengajuan judul Anda.',
            route('mahasiswa.title-submission', absolute: false),
        );

        return Redirect::route('prodi.title-submissions.index')->with('status', "Revisi diminta untuk \"{$thesis->title->title}\".");
    }

    public function downloadProposal(Request $request, Thesis $thesis)
    {
        $this->ensureVisible($request, $thesis);

        $thesis->loadMissing('title');
        abort_unless($thesis->title?->proposal_path, 404);

        $extension = pathinfo($thesis->title->proposal_path, PATHINFO_EXTENSION);
        $name = Str::slug($thesis->title->title ?: 'proposal').'.'.$extension;

        return Storage::disk('local')->download($thesis->title->proposal_path, $name);
    }

    private function ensureVisible(Request $request, Thesis $thesis): void
    {
        abort_unless($thesis->isVisibleToProdiStaff($request->user()), 403, 'Pengajuan ini bukan dari program studi Anda.');
    }
}
