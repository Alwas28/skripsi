<?php

namespace App\Http\Controllers;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamDecision;
use App\Enums\ExamRevisionStatus;
use App\Enums\SupervisionStatus;
use App\Enums\SupervisorApprovalStatus;
use App\Enums\ThesisStatus;
use App\Models\ExamRevision;
use App\Models\ExamSchedule;
use App\Models\Lecturer;
use App\Models\SupervisionSession;
use App\Models\Thesis;
use App\Models\ThesisSupervisor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One dashboard, with a section per menu group the user's roles belong to
 * (blueprint.pdf §17–19).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $groups = $user->menuGroups();

        return view('dashboard', [
            'studentPanel' => in_array('mahasiswa', $groups, true) ? $this->studentPanel($user) : null,
            'isStudent' => in_array('mahasiswa', $groups, true),
            'lecturerPanel' => in_array('dosen', $groups, true) && $user->lecturerRecord ? $this->lecturerPanel($user->lecturerRecord) : null,
            'prodiPanel' => in_array('prodi', $groups, true) ? $this->prodiPanel($user) : null,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function studentPanel(User $user): ?array
    {
        $thesis = $user->studentRecord?->thesis;

        if (! $thesis) {
            return null;
        }

        return [
            'thesis' => $thesis,
            'milestones' => $thesis->status->milestones(),
            'percent' => $thesis->status->progressPercent(),
            'nextSession' => $thesis->supervisionSessions()
                ->whereDate('session_date', '>=', today())
                ->where('status', '!=', SupervisionStatus::Approved)
                ->orderBy('session_date')
                ->first(),
            'openRevisions' => ExamRevision::whereHas('attempt.exam', fn (Builder $exam) => $exam->where('thesis_id', $thesis->id))
                ->whereHas('attempt.result', fn (Builder $result) => $result->where('decision', ExamDecision::Revision))
                ->where('status', ExamRevisionStatus::Open)
                ->count(),
            'nextExam' => ExamSchedule::whereHas('attempt', fn (Builder $attempt) => $attempt
                ->where('status', ExamAttemptStatus::Scheduled)
                ->whereHas('exam', fn (Builder $exam) => $exam->where('thesis_id', $thesis->id)))
                ->whereDate('exam_date', '>=', today())
                ->with('room')
                ->orderBy('exam_date')
                ->first(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lecturerPanel(Lecturer $lecturer): array
    {
        $supervisees = ThesisSupervisor::whereBelongsTo($lecturer)
            ->where('status', SupervisorApprovalStatus::Approved)
            ->whereHas('thesis', fn (Builder $thesis) => $thesis->where('status', '!=', ThesisStatus::Completed))
            ->with('thesis.student')
            ->get();

        $sessions = SupervisionSession::whereBelongsTo($lecturer);

        return [
            'stats' => [
                'Mahasiswa Bimbingan' => $supervisees->count(),
                'Menunggu Review' => (clone $sessions)->whereIn('status', [SupervisionStatus::Requested, SupervisionStatus::Reviewed, SupervisionStatus::Resubmitted])->count(),
                'Bimbingan Hari Ini' => (clone $sessions)->whereDate('session_date', today())->count(),
                'Revisi Menunggu Validasi' => ExamRevision::whereBelongsTo($lecturer)->where('status', ExamRevisionStatus::Submitted)->count(),
                'Menunggu Persetujuan Judul' => Thesis::where('status', ThesisStatus::Submitted)
                    ->whereHas('student', fn (Builder $student) => $student->where('academic_advisor_id', $lecturer->id))
                    ->count(),
            ],
            'students' => $supervisees->map(fn (ThesisSupervisor $supervisor) => [
                'name' => $supervisor->thesis->student->name,
                'percent' => $supervisor->thesis->status->progressPercent(),
                'status' => $supervisor->thesis->status->label(),
            ]),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function prodiPanel(User $user): array
    {
        $counts = Thesis::visibleToProdiStaff($user)
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $sum = fn (ThesisStatus ...$statuses) => (int) collect($statuses)->sum(fn (ThesisStatus $status) => $counts[$status->value] ?? 0);

        return [
            'Mahasiswa Skripsi' => (int) $counts->sum(),
            'Pengajuan Judul' => $sum(ThesisStatus::Submitted, ThesisStatus::TitleReview, ThesisStatus::RevisionRequired, ThesisStatus::TitleApproved),
            'Bimbingan' => $sum(ThesisStatus::SupervisorAssigned, ThesisStatus::Supervision, ThesisStatus::Research, ThesisStatus::ResultSupervision, ThesisStatus::FinalSupervision, ThesisStatus::ProposalResupervision, ThesisStatus::ResultResupervision, ThesisStatus::ThesisResupervision),
            'Ujian Proposal' => $sum(ThesisStatus::ReadyProposalExam, ThesisStatus::ProposalExam),
            'Ujian Hasil' => $sum(ThesisStatus::ReadyResultExam, ThesisStatus::ResultExam),
            'Ujian Skripsi' => $sum(ThesisStatus::ReadyThesisExam, ThesisStatus::ThesisExam, ThesisStatus::FinalValidation),
            'Selesai' => $sum(ThesisStatus::Completed),
            'Ujian Tidak Lulus' => $sum(ThesisStatus::ProposalFailed, ThesisStatus::ResultFailed, ThesisStatus::ThesisFailed),
            'Ujian Ulang' => $sum(ThesisStatus::ProposalReexam, ThesisStatus::ResultReexam, ThesisStatus::ThesisReexam),
            'Revisi' => $sum(ThesisStatus::ProposalRevision, ThesisStatus::ResultRevision, ThesisStatus::ThesisRevision),
        ];
    }
}
