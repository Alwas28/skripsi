<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::with('roles')->orderBy('name')->paginate(15);

        return view('users.index', ['users' => $users]);
    }

    public function create(): View
    {
        return view('users.create', ['user' => new User, 'roles' => Role::orderBy('name')->get(), 'studyPrograms' => StudyProgram::orderBy('name')->get()]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $user = User::create([
                ...$request->safe()->except(['password', 'roles']),
                'password' => Hash::make($request->validated('password')),
            ]);

            $user->syncRolesWithAudit($request->validated('roles', []));
        });

        return Redirect::route('users.index')->with('status', 'User berhasil ditambahkan.');
    }

    public function edit(User $user): View
    {
        return view('users.edit', ['user' => $user, 'roles' => Role::orderBy('name')->get(), 'studyPrograms' => StudyProgram::orderBy('name')->get()]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user) {
            $user->update([
                ...$request->safe()->except(['password', 'roles']),
                'password' => $request->validated('password') ? Hash::make($request->validated('password')) : $user->password,
            ]);

            $user->syncRolesWithAudit($request->validated('roles', []));
        });

        return Redirect::route('users.index')->with('status', 'User berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->is(request()->user())) {
            return Redirect::route('users.index')->with('error', 'Anda tidak bisa menghapus akun Anda sendiri.');
        }

        $user->delete();

        return Redirect::route('users.index')->with('status', 'User berhasil dihapus.');
    }
}
