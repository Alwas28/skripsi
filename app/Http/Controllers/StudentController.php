<?php

namespace App\Http\Controllers;

use App\Models\Lecturer;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentController extends Controller
{
    private const STATUSES = ['aktif', 'cuti', 'nonaktif', 'lulus'];

    public function index(): View
    {
        return view('master-data.students.index', [
            'students' => Student::with(['studyProgram', 'user', 'academicAdvisor'])->orderBy('name')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('master-data.students.create', [
            'student' => new Student,
            'studyPrograms' => StudyProgram::orderBy('name')->get(),
            'advisors' => $this->availableAdvisors(),
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            Student::create([
                ...Arr::except($data, 'email'),
                'user_id' => $this->createAccount($data['name'], $data['email'], $data['nim'])->id,
            ]);
        });

        return Redirect::route('prodi.students.index')->with('status', 'Mahasiswa berhasil ditambahkan. Password awal adalah NIM.');
    }

    public function edit(Student $student): View
    {
        return view('master-data.students.edit', [
            'student' => $student,
            'studyPrograms' => StudyProgram::orderBy('name')->get(),
            'advisors' => $this->availableAdvisors($student),
            'statuses' => self::STATUSES,
        ]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $data = $this->validated($request, $student);

        DB::transaction(function () use ($student, $data) {
            $student->update(Arr::except($data, 'email'));
            $user = $student->user;

            if (! $user) {
                $student->update(['user_id' => $this->createAccount($data['name'], $data['email'], $data['nim'])->id]);

                return;
            }

            $user->fill(['name' => $data['name'], 'email' => $data['email']]);

            // The default password follows a corrected NIM until it's changed.
            if ($user->must_change_password) {
                $user->password = $data['nim'];
            }

            $user->save();
        });

        return Redirect::route('prodi.students.index')->with('status', 'Mahasiswa berhasil diperbarui.');
    }

    /**
     * The login account goes with the student, unless it also serves another
     * role (e.g. a student who is staff).
     */
    public function destroy(Student $student): RedirectResponse
    {
        DB::transaction(function () use ($student) {
            $user = $student->user;
            $student->delete();

            if ($user && $user->roles()->where('slug', '!=', 'mahasiswa')->doesntExist()) {
                $user->delete();
            }
        });

        return Redirect::route('prodi.students.index')->with('status', 'Mahasiswa berhasil dihapus.');
    }

    private function createAccount(string $name, string $email, string $nim): User
    {
        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => $nim,
            'must_change_password' => true,
        ])->save();

        $user->syncRolesWithAudit(Role::where('slug', 'mahasiswa')->pluck('id'));

        return $user;
    }

    /**
     * Active lecturers holding the Dosen PA role (plus the student's current
     * advisor, when editing, so one who has since lost either stays selected
     * instead of silently clearing).
     */
    private function availableAdvisors(?Student $student = null)
    {
        return Lecturer::where(fn ($query) => $query->where('is_active', true)->holdingRole(Role::DOSEN_PA))
            ->when($student?->academic_advisor_id, fn ($query, $advisorId) => $query->orWhere('id', $advisorId))
            ->orderBy('name')
            ->get();
    }

    private function validated(Request $request, ?Student $student = null): array
    {
        return $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($student?->user_id)],
            'study_program_id' => ['required', 'exists:study_programs,id'],
            'academic_advisor_id' => ['nullable', Rule::in($this->availableAdvisors($student)->pluck('id'))],
            'nim' => ['required', 'string', 'max:30', Rule::unique('students', 'nim')->ignore($student)],
            'name' => ['required', 'string', 'max:255'],
            'no_wa' => ['nullable', 'string', 'max:30'],
            'entry_year' => ['required', 'digits:4'],
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);
    }
}
