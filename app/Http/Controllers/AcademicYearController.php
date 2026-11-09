<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AcademicYearController extends Controller
{
    private const SEMESTERS = ['Ganjil', 'Genap'];

    public function index(): View
    {
        return view('master-data.academic-years.index', [
            'academicYears' => AcademicYear::orderByDesc('name')->orderBy('semester')->get(),
        ]);
    }

    public function create(): View
    {
        return view('master-data.academic-years.create', [
            'academicYear' => new AcademicYear,
            'semesters' => self::SEMESTERS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if ($data['is_active'] ?? false) {
            AcademicYear::query()->update(['is_active' => false]);
        }

        AcademicYear::create($data);

        return Redirect::route('prodi.academic-years.index')->with('status', 'Tahun akademik berhasil ditambahkan.');
    }

    public function edit(AcademicYear $academicYear): View
    {
        return view('master-data.academic-years.edit', [
            'academicYear' => $academicYear,
            'semesters' => self::SEMESTERS,
        ]);
    }

    public function update(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $data = $this->validated($request, $academicYear);

        if (($data['is_active'] ?? false) && ! $academicYear->is_active) {
            AcademicYear::query()->update(['is_active' => false]);
        }

        $academicYear->update($data);

        return Redirect::route('prodi.academic-years.index')->with('status', 'Tahun akademik berhasil diperbarui.');
    }

    public function destroy(AcademicYear $academicYear): RedirectResponse
    {
        $academicYear->delete();

        return Redirect::route('prodi.academic-years.index')->with('status', 'Tahun akademik berhasil dihapus.');
    }

    private function validated(Request $request, ?AcademicYear $academicYear = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:20', Rule::unique('academic_years', 'name')->ignore($academicYear)->where(fn ($query) => $query->where('semester', $request->input('semester')))],
            'semester' => ['required', Rule::in(self::SEMESTERS)],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
