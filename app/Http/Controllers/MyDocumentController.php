<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStage;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Thesis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mahasiswa's documents per stage (blueprint.pdf §15). There is no delete:
 * replacing a file adds a version and keeps the old one as history.
 */
class MyDocumentController extends Controller
{
    private const FILE_RULES = ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'];

    public function index(Request $request): View
    {
        $thesis = $request->user()->studentRecord?->thesis()->with([
            'documents.versions' => fn ($query) => $query->orderByDesc('version_number')->with('uploader'),
        ])->first();

        return view('mahasiswa.documents.index', [
            'thesis' => $thesis,
            'openStages' => $thesis ? DocumentStage::openFor($thesis->status) : [],
            'documentsByStage' => $thesis?->documents->groupBy(fn (Document $document) => $document->stage->value) ?? collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $thesis = $this->ownThesis($request);

        $data = $request->validate([
            'stage' => ['required', Rule::in(array_column(DocumentStage::openFor($thesis->status), 'value'))],
            'name' => ['required', 'string', 'max:255'],
            'file' => self::FILE_RULES,
        ]);

        $thesis->documents()
            ->firstOrCreate(['stage' => $data['stage'], 'name' => $data['name']])
            ->addVersion($request->file('file'), $request->user());

        return Redirect::route('mahasiswa.documents')->with('status', 'Dokumen berhasil diunggah.');
    }

    public function storeVersion(Request $request, Document $document): RedirectResponse
    {
        $thesis = $this->ownThesis($request);
        abort_unless($document->thesis_id === $thesis->id, 403);
        abort_unless($document->stage->isOpenFor($thesis->status), 403, 'Tahapan dokumen ini tidak sedang terbuka.');

        $request->validate(['file' => self::FILE_RULES]);

        $document->addVersion($request->file('file'), $request->user());

        return Redirect::route('mahasiswa.documents')->with('status', 'Versi baru berhasil diunggah.');
    }

    public function download(Request $request, DocumentVersion $documentVersion): StreamedResponse
    {
        abort_unless($documentVersion->document->thesis_id === $this->ownThesis($request)->id, 403);

        return Storage::disk('local')->download($documentVersion->file_path, $documentVersion->original_name);
    }

    private function ownThesis(Request $request): Thesis
    {
        $thesis = $request->user()->studentRecord?->thesis;
        abort_unless($thesis, 403);

        return $thesis;
    }
}
