<?php

namespace App\Http\Controllers;

use App\Models\Faculty;
use App\Models\StudyProgram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudyProgramController extends Controller
{
    private const DEGREE_LEVELS = ['D3', 'D4', 'S1', 'S2', 'S3'];

    public function index(): View
    {
        return view('master-data.study-programs.index', [
            'studyPrograms' => StudyProgram::with('faculty')->withCount(['students', 'lecturers'])->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('master-data.study-programs.create', [
            'studyProgram' => new StudyProgram,
            'faculties' => Faculty::orderBy('name')->get(),
            'degreeLevels' => self::DEGREE_LEVELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        StudyProgram::create($this->validated($request));

        return Redirect::route('prodi.study-programs.index')->with('status', 'Program studi berhasil ditambahkan.');
    }

    public function edit(StudyProgram $studyProgram): View
    {
        return view('master-data.study-programs.edit', [
            'studyProgram' => $studyProgram,
            'faculties' => Faculty::orderBy('name')->get(),
            'degreeLevels' => self::DEGREE_LEVELS,
        ]);
    }

    public function update(Request $request, StudyProgram $studyProgram): RedirectResponse
    {
        $studyProgram->update($this->validated($request, $studyProgram));

        return Redirect::route('prodi.study-programs.index')->with('status', 'Program studi berhasil diperbarui.');
    }

    public function destroy(StudyProgram $studyProgram): RedirectResponse
    {
        if ($studyProgram->students()->exists() || $studyProgram->lecturers()->exists()) {
            return Redirect::route('prodi.study-programs.index')->with('error', 'Program studi masih memiliki mahasiswa/dosen, pindahkan dulu.');
        }

        $studyProgram->delete();

        return Redirect::route('prodi.study-programs.index')->with('status', 'Program studi berhasil dihapus.');
    }

    private function validated(Request $request, ?StudyProgram $studyProgram = null): array
    {
        return $request->validate([
            'faculty_id' => ['required', 'exists:faculties,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('study_programs', 'code')->ignore($studyProgram)],
            'degree_level' => ['nullable', Rule::in(self::DEGREE_LEVELS)],
        ]);
    }
}
