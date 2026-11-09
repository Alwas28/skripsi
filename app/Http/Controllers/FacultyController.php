<?php

namespace App\Http\Controllers;

use App\Models\Faculty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FacultyController extends Controller
{
    public function index(): View
    {
        return view('master-data.faculties.index', [
            'faculties' => Faculty::withCount('studyPrograms')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('master-data.faculties.create', ['faculty' => new Faculty]);
    }

    public function store(Request $request): RedirectResponse
    {
        Faculty::create($this->validated($request));

        return Redirect::route('prodi.faculties.index')->with('status', 'Fakultas berhasil ditambahkan.');
    }

    public function edit(Faculty $faculty): View
    {
        return view('master-data.faculties.edit', ['faculty' => $faculty]);
    }

    public function update(Request $request, Faculty $faculty): RedirectResponse
    {
        $faculty->update($this->validated($request, $faculty));

        return Redirect::route('prodi.faculties.index')->with('status', 'Fakultas berhasil diperbarui.');
    }

    public function destroy(Faculty $faculty): RedirectResponse
    {
        if ($faculty->studyPrograms()->exists()) {
            return Redirect::route('prodi.faculties.index')->with('error', 'Fakultas masih memiliki program studi, hapus/pindahkan dulu.');
        }

        $faculty->delete();

        return Redirect::route('prodi.faculties.index')->with('status', 'Fakultas berhasil dihapus.');
    }

    private function validated(Request $request, ?Faculty $faculty = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('faculties', 'code')->ignore($faculty)],
        ]);
    }
}
