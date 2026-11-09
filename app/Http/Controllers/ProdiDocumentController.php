<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Thesis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only view of student documents for prodi staff, limited to their own
 * study program.
 */
class ProdiDocumentController extends Controller
{
    public function index(Request $request): View
    {
        return view('prodi.documents.index', [
            'theses' => Thesis::visibleToProdiStaff($request->user())
                ->has('documents')
                ->with(['student.studyProgram', 'title'])
                ->withCount('documents')
                ->latest()
                ->paginate(15),
        ]);
    }

    public function show(Request $request, Thesis $thesis): View
    {
        $this->ensureVisible($request, $thesis);

        $thesis->load([
            'student.studyProgram',
            'title',
            'documents.versions' => fn ($query) => $query->orderByDesc('version_number')->with('uploader'),
        ]);

        return view('prodi.documents.show', [
            'thesis' => $thesis,
            'documentsByStage' => $thesis->documents->groupBy(fn (Document $document) => $document->stage->value),
        ]);
    }

    public function download(Request $request, DocumentVersion $documentVersion): StreamedResponse
    {
        $this->ensureVisible($request, $documentVersion->document->thesis);

        return Storage::disk('local')->download($documentVersion->file_path, $documentVersion->original_name);
    }

    private function ensureVisible(Request $request, Thesis $thesis): void
    {
        abort_unless($thesis->isVisibleToProdiStaff($request->user()), 403, 'Dokumen ini bukan dari program studi Anda.');
    }
}
