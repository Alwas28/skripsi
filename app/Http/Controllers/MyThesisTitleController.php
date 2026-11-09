<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStage;
use App\Enums\ThesisStatus;
use App\Models\AcademicYear;
use App\Models\Document;
use App\Models\Student;
use App\Models\Thesis;
use App\Notifications\ThesisUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Mahasiswa's own "Pengajuan Judul" — a single show/update page (not a
 * resource: every student has at most one thesis), auto-provisioning the
 * thesis + title row as DRAFT on first visit. See TitleSubmissionController
 * for the Prodi-side review of these submissions.
 */
class MyThesisTitleController extends Controller
{
    private const EDITABLE_STATUSES = [ThesisStatus::Draft, ThesisStatus::RevisionRequired];

    public function show(Request $request): View
    {
        $student = $request->user()->studentRecord;

        if (! $student) {
            return view('mahasiswa.title-submission.not-linked');
        }

        $thesis = $this->resolveThesis($student);

        return view('mahasiswa.title-submission.show', [
            'thesis' => $thesis,
            'editable' => in_array($thesis->status, self::EDITABLE_STATUSES, true),
            'advisor' => $student->academicAdvisor,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $student = $request->user()->studentRecord;
        abort_unless($student, 403);

        $thesis = $this->resolveThesis($student);
        abort_unless(
            in_array($thesis->status, self::EDITABLE_STATUSES, true),
            403,
            'Pengajuan judul tidak bisa diubah pada status saat ini.'
        );

        $submitting = $request->input('action') === 'submit';

        if ($submitting && ! $student->academic_advisor_id) {
            return Redirect::route('mahasiswa.title-submission')
                ->withInput()
                ->withErrors(['advisor' => 'Dosen PA Anda belum ditentukan. Hubungi Admin Prodi sebelum mengajukan judul.']);
        }

        $data = $request->validate($this->rules($thesis, $submitting));

        if ($request->hasFile('proposal')) {
            $document = $thesis->documents()->firstOrCreate([
                'stage' => DocumentStage::TitleSubmission->value,
                'name' => Document::TITLE_PROPOSAL_NAME,
            ]);
            $data['proposal_path'] = $document->addVersion($request->file('proposal'), $request->user())->file_path;
        }
        unset($data['proposal']);

        $thesis->title->update($data);

        if (! $submitting) {
            return Redirect::route('mahasiswa.title-submission')->with('status', 'Draft berhasil disimpan.');
        }

        $thesis->title->update([
            'submitted_at' => now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_notes' => null,
        ]);
        $thesis->transitionTo(ThesisStatus::Submitted, $request->user(), 'Diajukan oleh mahasiswa.');

        ThesisUpdate::send(
            $student->academicAdvisor?->user,
            'Usulan judul menunggu persetujuan Anda',
            "{$student->name} mengajukan judul \"{$thesis->title->title}\".",
            route('dosen.advisor-titles.show', $thesis, false),
        );

        return Redirect::route('mahasiswa.title-submission')->with('status', 'Judul berhasil diajukan, menunggu persetujuan Dosen PA.');
    }

    public function downloadProposal(Request $request)
    {
        $student = $request->user()->studentRecord;
        abort_unless($student, 403);

        $thesis = $student->thesis()->with('title')->firstOrFail();
        abort_unless($thesis->title?->proposal_path, 404);

        return $this->streamProposal($thesis);
    }

    private function streamProposal(Thesis $thesis)
    {
        $extension = pathinfo($thesis->title->proposal_path, PATHINFO_EXTENSION);
        $name = Str::slug($thesis->title->title ?: 'proposal').'.'.$extension;

        return Storage::disk('local')->download($thesis->title->proposal_path, $name);
    }

    private function resolveThesis(Student $student): Thesis
    {
        /** @var Thesis $thesis */
        $thesis = $student->thesis()->with('title')->first() ?? $student->thesis()->create([
            'academic_year_id' => AcademicYear::where('is_active', true)->value('id'),
            'status' => ThesisStatus::Draft,
        ]);

        if (! $thesis->title) {
            $thesis->setRelation('title', $thesis->title()->create(['title' => '']));
        }

        return $thesis;
    }

    private function rules(Thesis $thesis, bool $submitting): array
    {
        $required = $submitting ? 'required' : 'nullable';

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'research_field' => [$required, 'string', 'max:255'],
            'background' => [$required, 'string'],
            'problem_statement' => [$required, 'string'],
            'objectives' => [$required, 'string'],
            'method' => [$required, 'string'],
            'research_object' => [$required, 'string'],
            'initial_references' => [$required, 'string'],
            'proposal' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ];

        if ($submitting && ! $thesis->title->proposal_path) {
            $rules['proposal'] = ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'];
        }

        return $rules;
    }
}
