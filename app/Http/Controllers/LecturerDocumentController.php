<?php

namespace App\Http\Controllers;

use App\Enums\DocumentReviewStatus;
use App\Enums\SupervisorApprovalStatus;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Thesis;
use App\Notifications\ThesisUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Review Dokumen: a supervisor reads and reviews their students' documents.
 */
class LecturerDocumentController extends Controller
{
    public function index(Request $request): View
    {
        $lecturer = $request->user()->lecturerRecord;

        if (! $lecturer) {
            return view('dosen.advisor-titles.not-linked', ['pageTitle' => 'Review Dokumen']);
        }

        return view('shared.table', [
            'title' => 'Review Dokumen',
            'tableTitle' => 'Dokumen Mahasiswa Bimbingan',
            'columns' => ['Mahasiswa', 'Judul', 'Jumlah Dokumen', 'Status', ''],
            'empty' => 'Belum ada dokumen dari mahasiswa bimbingan.',
            'rows' => Thesis::whereHas('supervisors', fn ($query) => $query
                ->where('lecturer_id', $lecturer->id)
                ->where('status', SupervisorApprovalStatus::Approved))
                ->has('documents')
                ->with(['student', 'title'])
                ->withCount('documents')
                ->latest()
                ->paginate(15)
                ->through(fn (Thesis $thesis) => [
                    $thesis->student->name,
                    $thesis->title?->title,
                    $thesis->documents_count,
                    ['badge' => $thesis->status->label(), 'class' => $thesis->status->badgeClass()],
                    ['link' => route('dosen.document-review.show', $thesis), 'text' => 'Buka'],
                ]),
        ]);
    }

    public function show(Request $request, Thesis $thesis): View
    {
        $this->ensureSupervisor($request, $thesis);

        $thesis->load([
            'student',
            'title',
            'documents.versions' => fn ($query) => $query->orderByDesc('version_number')->with('uploader'),
        ]);

        return view('dosen.document-review.show', [
            'thesis' => $thesis,
            'documentsByStage' => $thesis->documents->groupBy(fn (Document $document) => $document->stage->value),
        ]);
    }

    public function review(Request $request, DocumentVersion $documentVersion): RedirectResponse
    {
        $thesis = $documentVersion->document->thesis;
        $this->ensureSupervisor($request, $thesis);
        abort_unless($documentVersion->is_active, 403, 'Hanya versi aktif yang bisa direview.');

        $data = $request->validate([
            'review_status' => ['required', Rule::enum(DocumentReviewStatus::class)],
            'review_notes' => ['nullable', 'required_if:review_status,needs_revision', 'string'],
        ]);

        $documentVersion->update([
            ...$data,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        ThesisUpdate::send(
            $thesis->student->user,
            'Dokumen sudah direview',
            "{$documentVersion->document->name}: ".DocumentReviewStatus::from($data['review_status'])->label().'.',
            route('mahasiswa.documents', absolute: false),
        );

        return Redirect::route('dosen.document-review.show', $thesis)->with('status', 'Review dokumen tersimpan.');
    }

    public function download(Request $request, DocumentVersion $documentVersion): StreamedResponse
    {
        $this->ensureSupervisor($request, $documentVersion->document->thesis);

        return Storage::disk('local')->download($documentVersion->file_path, $documentVersion->original_name);
    }

    private function ensureSupervisor(Request $request, Thesis $thesis): void
    {
        $lecturer = $request->user()->lecturerRecord;
        abort_unless($lecturer && $thesis->isSupervisedBy($lecturer), 403, 'Mahasiswa ini bukan bimbingan Anda.');
    }
}
