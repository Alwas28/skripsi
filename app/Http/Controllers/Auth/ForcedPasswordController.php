<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The page a student is held on until they replace the default NIM password.
 */
class ForcedPasswordController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return Redirect::route('dashboard');
        }

        return view('auth.force-change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->must_change_password, 403);

        $nim = $request->user()->studentRecord?->nim;

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults(), function (string $attribute, mixed $value, Closure $fail) use ($nim) {
                if ($value === $nim) {
                    $fail('Password baru tidak boleh sama dengan NIM.');
                }
            }],
        ]);

        $request->user()->update(['password' => $validated['password']]);

        return Redirect::route('dashboard')->with('status', 'Password berhasil diubah.');
    }
}
