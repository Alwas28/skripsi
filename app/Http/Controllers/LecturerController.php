<?php

namespace App\Http\Controllers;

use App\Models\Lecturer;
use App\Models\Role;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class LecturerController extends Controller
{
    public function index(): View
    {
        // If the quick-connect modal's submission just failed validation,
        // the redirect-back gives us its hidden lecturer id via old() — used
        // to re-open the right modal with its errors instead of losing them.
        $reopenConnectFor = old('_lecturer_id') ? Lecturer::find(old('_lecturer_id')) : null;

        return view('master-data.lecturers.index', [
            'lecturers' => Lecturer::with(['studyProgram', 'user'])->orderBy('name')->paginate(15),
            'roles' => Role::orderBy('name')->get(),
            'reopenConnectFor' => $reopenConnectFor,
        ]);
    }

    public function create(): View
    {
        return view('master-data.lecturers.create', [
            'lecturer' => new Lecturer,
            'studyPrograms' => StudyProgram::orderBy('name')->get(),
            'availableUsers' => $this->availableUsers(),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $lecturer = Lecturer::create($this->validated($request));
            $this->maybeCreateAccount($request, $lecturer);
        });

        return Redirect::route('prodi.lecturers.index')->with('status', 'Dosen berhasil ditambahkan.');
    }

    public function edit(Lecturer $lecturer): View
    {
        return view('master-data.lecturers.edit', [
            'lecturer' => $lecturer,
            'studyPrograms' => StudyProgram::orderBy('name')->get(),
            'availableUsers' => $this->availableUsers($lecturer),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Lecturer $lecturer): RedirectResponse
    {
        DB::transaction(function () use ($request, $lecturer) {
            $lecturer->update($this->validated($request, $lecturer));
            $this->maybeCreateAccount($request, $lecturer);
        });

        return Redirect::route('prodi.lecturers.index')->with('status', 'Dosen berhasil diperbarui.');
    }

    public function destroy(Lecturer $lecturer): RedirectResponse
    {
        if ($lecturer->supervisorAssignments()->exists() || $lecturer->examinerAssignments()->exists()) {
            return Redirect::route('prodi.lecturers.index')->with('error', 'Dosen masih tercatat sebagai pembimbing atau penguji, nonaktifkan saja datanya.');
        }

        $lecturer->delete();

        return Redirect::route('prodi.lecturers.index')->with('status', 'Dosen berhasil dihapus.');
    }

    /**
     * Quick-connect modal on the index page, for a lecturer that has no
     * linked account yet — same account-creation logic as the inline form,
     * just reachable without opening the full edit page.
     */
    public function connectAccount(Request $request, Lecturer $lecturer): RedirectResponse
    {
        if ($lecturer->user_id) {
            return Redirect::route('prodi.lecturers.index')->with('error', "{$lecturer->name} sudah terhubung ke akun.");
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,id'],
        ]);

        $this->createAndLinkAccount($lecturer, $data['email'], $data['password'], $data['roles'] ?? []);

        return Redirect::route('prodi.lecturers.index')->with('status', "Akun untuk {$lecturer->name} berhasil dibuat dan dihubungkan.");
    }

    /**
     * Users not yet linked to a lecturer record (plus the lecturer's own
     * current user, when editing, so it stays selectable).
     */
    private function availableUsers(?Lecturer $lecturer = null)
    {
        return User::whereDoesntHave('lecturerRecord')
            ->when($lecturer?->user_id, fn ($query, $userId) => $query->orWhere('id', $userId))
            ->orderBy('name')
            ->get();
    }

    private function validated(Request $request, ?Lecturer $lecturer = null): array
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id', Rule::unique('lecturers', 'user_id')->ignore($lecturer)],
            'study_program_id' => ['nullable', 'exists:study_programs,id'],
            'nidn' => ['required', 'string', 'max:30', Rule::unique('lecturers', 'nidn')->ignore($lecturer)],
            'gelar_depan' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'gelar_belakang' => ['nullable', 'string', 'max:30'],
            'expertise_field' => ['nullable', 'string', 'max:255'],
            'no_wa' => ['nullable', 'string', 'max:30'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    /**
     * Only relevant while the lecturer has no account yet (mirrors the form:
     * fields are hidden once linked) — filling email+password here
     * provisions a fresh login account and links it, in the same request
     * that saves the lecturer, instead of a separate step.
     */
    private function maybeCreateAccount(Request $request, Lecturer $lecturer): void
    {
        if ($lecturer->user_id || ! $request->filled('new_account_email')) {
            return;
        }

        $data = $request->validate([
            'new_account_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'new_account_password' => ['required', 'confirmed', Password::defaults()],
            'new_account_roles' => ['nullable', 'array'],
            'new_account_roles.*' => ['exists:roles,id'],
        ]);

        $this->createAndLinkAccount($lecturer, $data['new_account_email'], $data['new_account_password'], $data['new_account_roles'] ?? []);
    }

    /**
     * Defaults to the three dosen roles — a role picker is offered (both here
     * and in the modal) because an admin_prodi granted only edit.lecturer can
     * reach this, and a lecturer occasionally needs a different role (e.g.
     * also Kaprodi). See config/menu.php's Kontrol Akses gating for the
     * separate, stricter path into general user/role management.
     *
     * @param  array<int, int|string>  $roleIds
     */
    private function createAndLinkAccount(Lecturer $lecturer, string $email, string $password, array $roleIds): User
    {
        $user = User::create([
            'name' => $lecturer->name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $user->syncRolesWithAudit($roleIds ?: Role::whereIn('slug', Role::DOSEN_ROLES)->pluck('id'));

        $lecturer->update(['user_id' => $user->id]);

        return $user;
    }
}
