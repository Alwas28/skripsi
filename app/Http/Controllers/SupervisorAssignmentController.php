<?php

namespace App\Http\Controllers;

use App\Enums\SupervisorApprovalStatus;
use App\Enums\ThesisStatus;
use App\Models\Lecturer;
use App\Models\Role;
use App\Models\Thesis;
use App\Models\ThesisSupervisor;
use App\Notifications\ThesisUpdate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Penetapan Pembimbing (blueprint.pdf §5): Admin Prodi proposes Pembimbing 1
 * and 2 for an approved title, the Kaprodi approves or rejects the pair.
 */
class SupervisorAssignmentController extends Controller
{
    private const BEFORE_TITLE_APPROVAL = [
        ThesisStatus::Draft,
        ThesisStatus::Submitted,
        ThesisStatus::TitleReview,
        ThesisStatus::RevisionRequired,
    ];

    public function index(Request $request): View
    {
        $theses = Thesis::visibleToProdiStaff($request->user())->whereNotIn('status', self::BEFORE_TITLE_APPROVAL);

        return view('prodi.supervisors.index', [
            'theses' => (clone $theses)
                ->with(['student.studyProgram', 'title', 'supervisors.lecturer'])
                ->latest()
                ->paginate(15),
            'awaitingProposalCount' => (clone $theses)
                ->where('status', ThesisStatus::TitleApproved)
                ->whereDoesntHave('supervisors', fn (Builder $query) => $query->where('status', SupervisorApprovalStatus::Pending))
                ->count(),
            'awaitingApprovalCount' => (clone $theses)
                ->whereHas('supervisors', fn (Builder $query) => $query->where('status', SupervisorApprovalStatus::Pending))
                ->count(),
        ]);
    }

    public function show(Request $request, Thesis $thesis): View
    {
        $this->ensureVisible($request, $thesis);
        abort_if(in_array($thesis->status, self::BEFORE_TITLE_APPROVAL, true), 404);

        $thesis->load(['student.studyProgram', 'student.academicAdvisor', 'title', 'supervisors.lecturer']);

        $lecturers = Lecturer::where('study_program_id', $thesis->student->study_program_id)->withCount(['supervisorAssignments as supervision_load' => fn (Builder $query) => $query
            ->where('status', SupervisorApprovalStatus::Approved)
            ->whereHas('thesis', fn (Builder $thesisQuery) => $thesisQuery->where('status', '!=', ThesisStatus::Completed)),
        ])->orderBy('name')->get();

        $isOpen = $thesis->status === ThesisStatus::TitleApproved;
        $isPending = $thesis->supervisors->contains('status', SupervisorApprovalStatus::Pending);

        return view('prodi.supervisors.show', [
            'thesis' => $thesis,
            'lecturers' => $lecturers,
            'eligibleLecturers' => $lecturers->whereIn('id', $this->eligibleSupervisorIds($thesis)),
            'canPropose' => $isOpen && $request->user()->hasPermission('tambah.supervisor_assignment'),
            'canDecide' => $isOpen && $isPending && $request->user()->hasPermission('edit.supervisor_assignment'),
        ]);
    }

    public function store(Request $request, Thesis $thesis): RedirectResponse
    {
        $this->ensureVisible($request, $thesis);
        $this->ensureOpen($thesis);

        $eligibleSupervisor = Rule::in($this->eligibleSupervisorIds($thesis));
        $ineligibleMessage = 'Pilih dosen aktif dari program studi mahasiswa yang memiliki role Dosen Pembimbing.';
        $data = $request->validate([
            'supervisor_1_id' => ['required', $eligibleSupervisor],
            'supervisor_2_id' => ['required', 'different:supervisor_1_id', $eligibleSupervisor],
        ], [
            'supervisor_1_id.in' => $ineligibleMessage,
            'supervisor_2_id.in' => $ineligibleMessage,
            'supervisor_2_id.different' => 'Pembimbing 2 harus berbeda dari Pembimbing 1.',
        ]);

        DB::transaction(function () use ($request, $thesis, $data) {
            $thesis->supervisors->each->delete();

            foreach ([1 => $data['supervisor_1_id'], 2 => $data['supervisor_2_id']] as $position => $lecturerId) {
                $thesis->supervisors()->create([
                    'lecturer_id' => $lecturerId,
                    'position' => $position,
                    'status' => SupervisorApprovalStatus::Pending,
                    'assigned_by' => $request->user()->id,
                ]);
            }
        });

        ThesisUpdate::send(
            $thesis->prodiStaffWithRole('kaprodi'),
            'Usulan pembimbing menunggu persetujuan',
            "Admin Prodi mengusulkan pembimbing untuk {$thesis->student->name}.",
            route('prodi.supervisors.show', $thesis, false),
        );

        return Redirect::route('prodi.supervisors.show', $thesis)->with('status', 'Usulan pembimbing dikirim ke Kaprodi.');
    }

    public function approve(Request $request, Thesis $thesis): RedirectResponse
    {
        $this->ensureVisible($request, $thesis);
        $this->ensureOpen($thesis);
        $this->ensurePendingProposal($thesis);

        DB::transaction(function () use ($request, $thesis) {
            $thesis->supervisors->each->update([
                'status' => SupervisorApprovalStatus::Approved,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_notes' => null,
            ]);
            $thesis->transitionTo(ThesisStatus::SupervisorAssigned, $request->user(), 'Pembimbing disetujui Kaprodi.');
        });

        ThesisUpdate::send(
            $thesis->student->user,
            'Pembimbing ditetapkan',
            'Pembimbing 1 dan Pembimbing 2 Anda sudah ditetapkan.',
            route('mahasiswa.supervisor', absolute: false),
        );
        ThesisUpdate::send(
            $thesis->supervisors->map(fn (ThesisSupervisor $supervisor) => $supervisor->lecturer->user),
            'Mahasiswa bimbingan baru',
            "Anda ditetapkan sebagai pembimbing {$thesis->student->name}.",
            route('dosen.supervisees', absolute: false),
        );

        return Redirect::route('prodi.supervisors')->with('status', 'Pembimbing berhasil ditetapkan.');
    }

    public function reject(Request $request, Thesis $thesis): RedirectResponse
    {
        $this->ensureVisible($request, $thesis);
        $this->ensureOpen($thesis);
        $this->ensurePendingProposal($thesis);

        $data = $request->validate([
            'review_notes' => ['required', 'string'],
        ]);

        $thesis->supervisors->each->update([
            'status' => SupervisorApprovalStatus::Rejected,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'],
        ]);

        ThesisUpdate::send(
            $thesis->prodiStaffWithRole('admin_prodi'),
            'Usulan pembimbing ditolak',
            "Kaprodi menolak usulan pembimbing untuk {$thesis->student->name}: {$data['review_notes']}",
            route('prodi.supervisors.show', $thesis, false),
        );

        return Redirect::route('prodi.supervisors')->with('status', 'Usulan pembimbing dikembalikan ke Admin Prodi.');
    }

    /**
     * Supervisors must come from the student's own study program.
     *
     * @return Collection<int, int>
     */
    private function eligibleSupervisorIds(Thesis $thesis): Collection
    {
        return Lecturer::where('study_program_id', $thesis->student->study_program_id)
            ->where('is_active', true)
            ->holdingRole(Role::DOSEN_PEMBIMBING)
            ->pluck('id');
    }

    private function ensureVisible(Request $request, Thesis $thesis): void
    {
        abort_unless($thesis->isVisibleToProdiStaff($request->user()), 403, 'Skripsi ini bukan dari program studi Anda.');
    }

    private function ensureOpen(Thesis $thesis): void
    {
        abort_unless($thesis->status === ThesisStatus::TitleApproved, 403, 'Pembimbing tidak bisa diubah pada status skripsi saat ini.');
    }

    private function ensurePendingProposal(Thesis $thesis): void
    {
        abort_unless(
            $thesis->supervisors()->where('status', SupervisorApprovalStatus::Pending)->exists(),
            403,
            'Belum ada usulan pembimbing yang menunggu persetujuan.'
        );
    }
}
