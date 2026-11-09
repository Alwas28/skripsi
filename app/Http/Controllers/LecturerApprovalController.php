<?php

namespace App\Http\Controllers;

use App\Enums\ExamKind;
use App\Enums\SupervisorApprovalStatus;
use App\Models\Thesis;
use App\Notifications\ThesisUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

/**
 * Persetujuan: the supervisor's "layak diuji" sign-off for the next exam.
 */
class LecturerApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $lecturer = $request->user()->lecturerRecord;

        if (! $lecturer) {
            return view('dosen.advisor-titles.not-linked', ['pageTitle' => 'Persetujuan']);
        }

        $items = Thesis::whereHas('supervisors', fn ($query) => $query
            ->where('lecturer_id', $lecturer->id)
            ->where('status', SupervisorApprovalStatus::Approved))
            ->with(['student', 'title'])
            ->latest()
            ->get()
            ->flatMap(fn (Thesis $thesis) => collect(ExamKind::cases())
                ->filter(fn (ExamKind $kind) => $thesis->canRequestExam($kind))
                ->map(fn (ExamKind $kind) => [
                    'thesis' => $thesis,
                    'kind' => $kind,
                    'checks' => $thesis->examRequirementChecks($kind),
                    'approved' => $thesis->readinessApprovals()
                        ->where('exam_kind', $kind->value)
                        ->where('attempt_number', $thesis->nextAttemptNumber($kind))
                        ->where('lecturer_id', $lecturer->id)
                        ->exists(),
                ]));

        return view('dosen.approvals.index', ['items' => $items]);
    }

    public function approve(Request $request, Thesis $thesis, ExamKind $examKind): RedirectResponse
    {
        $lecturer = $request->user()->lecturerRecord;
        abort_unless($lecturer && $thesis->isSupervisedBy($lecturer), 403, 'Mahasiswa ini bukan bimbingan Anda.');
        abort_unless($thesis->canRequestExam($examKind), 403, 'Skripsi ini belum berada pada tahap tersebut.');

        $thesis->readinessApprovals()->firstOrCreate([
            'exam_kind' => $examKind->value,
            'attempt_number' => $thesis->nextAttemptNumber($examKind),
            'lecturer_id' => $lecturer->id,
        ]);

        ThesisUpdate::send(
            $thesis->student->user,
            'Pembimbing menyetujui kelayakan ujian',
            "{$lecturer->full_name} menyetujui Anda untuk {$examKind->label()}.",
            route('mahasiswa.supervision', absolute: false),
        );

        return Redirect::route('dosen.approvals')->with('status', 'Persetujuan tersimpan.');
    }
}
