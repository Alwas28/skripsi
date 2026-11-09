<?php

namespace App\Http\Controllers;

use App\Enums\SupervisionStatus;
use App\Models\Lecturer;
use App\Models\SupervisionSession;
use App\Notifications\ThesisUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LecturerSupervisionController extends Controller
{
    private const DECISIONS = [SupervisionStatus::Reviewed, SupervisionStatus::RevisionRequired, SupervisionStatus::Approved];

    public function index(Request $request): View
    {
        $lecturer = $request->user()->lecturerRecord;

        if (! $lecturer) {
            return view('dosen.advisor-titles.not-linked', ['pageTitle' => 'Bimbingan']);
        }

        return view('shared.table', [
            'title' => 'Bimbingan',
            'tableTitle' => 'Sesi Bimbingan Mahasiswa',
            'columns' => ['Mahasiswa', 'Tahap', 'Bab', 'Topik', 'Tanggal', 'Status', ''],
            'empty' => 'Belum ada sesi bimbingan.',
            'rows' => SupervisionSession::whereBelongsTo($lecturer)
                ->with('thesis.student')
                ->latest('id')
                ->paginate(15)
                ->through(fn (SupervisionSession $session) => [
                    $session->thesis->student->name,
                    $session->phase->supervisionLabel(),
                    $session->chapter,
                    $session->topic,
                    $session->session_date->format('d M Y'),
                    ['badge' => $session->status->label(), 'class' => $session->status->badgeClass()],
                    ['link' => route('dosen.supervision.show', $session), 'text' => 'Buka'],
                ]),
        ]);
    }

    public function show(Request $request, SupervisionSession $supervisionSession): View
    {
        $this->ensureOwnSession($request, $supervisionSession);

        $supervisionSession->load(['thesis.student', 'thesis.title', 'files.documentVersion', 'notes.user', 'revisions.documentVersion']);

        return view('dosen.supervision.show', ['session' => $supervisionSession]);
    }

    public function review(Request $request, SupervisionSession $supervisionSession): RedirectResponse
    {
        $this->ensureOwnSession($request, $supervisionSession);
        abort_unless($supervisionSession->status->awaitsLecturer(), 403, 'Bimbingan ini tidak sedang menunggu review.');

        $data = $request->validate([
            'decision' => ['required', Rule::enum(SupervisionStatus::class)->only(self::DECISIONS)],
            'note' => ['nullable', 'required_unless:decision,approved', 'string'],
        ]);

        $decision = SupervisionStatus::from($data['decision']);

        DB::transaction(function () use ($request, $supervisionSession, $decision, $data) {
            $supervisionSession->update([
                'status' => $decision,
                'approved_at' => $decision === SupervisionStatus::Approved ? now() : null,
            ]);
            $supervisionSession->notes()->create([
                'user_id' => $request->user()->id,
                'decision' => $decision,
                'note' => $data['note'] ?? null,
            ]);
        });

        [$title, $message] = match ($decision) {
            SupervisionStatus::Approved => ['Bimbingan disetujui', "Bimbingan {$supervisionSession->chapter} telah disetujui."],
            SupervisionStatus::RevisionRequired => ['Revisi bimbingan tersedia', "Pembimbing meminta revisi pada {$supervisionSession->chapter}."],
            default => ['Bimbingan sudah direview', "Pembimbing memberi catatan pada {$supervisionSession->chapter}."],
        };

        ThesisUpdate::send($supervisionSession->thesis->student->user, $title, $message, route('mahasiswa.supervision', absolute: false));

        return Redirect::route('dosen.supervision.show', $supervisionSession)->with('status', 'Review tersimpan.');
    }

    private function ensureOwnSession(Request $request, SupervisionSession $session): Lecturer
    {
        $lecturer = $request->user()->lecturerRecord;
        abort_unless($lecturer && $session->lecturer_id === $lecturer->id, 403, 'Sesi ini bukan bimbingan Anda.');

        return $lecturer;
    }
}
