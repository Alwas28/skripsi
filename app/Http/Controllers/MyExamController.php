<?php

namespace App\Http\Controllers;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamKind;
use App\Models\ExamType;
use App\Notifications\ThesisUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

/**
 * Mahasiswa's exam page, shared by all three exams.
 */
class MyExamController extends Controller
{
    public function show(Request $request, ExamKind $examKind): View
    {
        $thesis = $request->user()->studentRecord?->thesis;
        $canRequest = $thesis && $thesis->canRequestExam($examKind);
        $checks = $canRequest ? $thesis->examRequirementChecks($examKind) : [];

        return view('mahasiswa.exams.show', [
            'kind' => $examKind,
            'thesis' => $thesis,
            'canRequest' => $canRequest,
            'checks' => $checks,
            'allMet' => collect($checks)->every('met'),
            'attempts' => $thesis?->examOf($examKind)?->attempts()
                ->with(['examiners.lecturer', 'schedule.room', 'result'])
                ->latest('id')
                ->get() ?? collect(),
        ]);
    }

    public function request(Request $request, ExamKind $examKind): RedirectResponse
    {
        $thesis = $request->user()->studentRecord?->thesis;
        abort_unless($thesis && $thesis->canRequestExam($examKind), 403, 'Ujian ini belum bisa diajukan pada tahap sekarang.');
        abort_unless(collect($thesis->examRequirementChecks($examKind))->every('met'), 403, 'Persyaratan ujian belum terpenuhi.');

        DB::transaction(function () use ($request, $thesis, $examKind) {
            $exam = $thesis->exams()->firstOrCreate([
                'exam_type_id' => ExamType::where('code', $examKind->value)->value('id'),
            ]);

            $exam->attempts()->create([
                'attempt_number' => $thesis->nextAttemptNumber($examKind),
                'status' => ExamAttemptStatus::Requested,
            ]);

            $thesis->transitionTo(
                $thesis->status === $examKind->resupervisionStatus() ? $examKind->reexamStatus() : $examKind->readyStatus(),
                $request->user(),
                "Mengajukan {$examKind->label()}.",
            );
        });

        $attempt = $thesis->latestAttemptOf($examKind);

        ThesisUpdate::send(
            $thesis->prodiStaffWithRole('admin_prodi'),
            "Pengajuan {$examKind->label()} baru",
            "{$thesis->student->name} mengajukan {$examKind->label()} (percobaan {$attempt->attempt_number}).",
            route('prodi.exams.attempt', $attempt, false),
        );

        return Redirect::route("mahasiswa.exams.{$examKind->value}")->with('status', 'Pengajuan ujian berhasil dikirim ke Prodi.');
    }
}
