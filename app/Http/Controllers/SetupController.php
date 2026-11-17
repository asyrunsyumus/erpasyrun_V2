<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Membuat akun Owner PERTAMA lewat browser (hosting tanpa SSH).
 *
 * Hanya bisa dibuka bila:
 *  - belum ada satu pun pengguna, DAN
 *  - token cocok dengan file storage/app/setup.token (diunggah manual, tidak di Git).
 * Setelah Owner dibuat, file token dihapus dan halaman ini selamanya 404.
 */
class SetupController extends Controller
{
    private function authorizeSetup(string $token): void
    {
        $file = storage_path('app/setup.token');
        $expected = is_file($file) ? trim((string) file_get_contents($file)) : '';

        if (User::query()->exists() || strlen($expected) < 40 || ! hash_equals($expected, $token)) {
            abort(404);
        }
    }

    public function show(string $token): Response
    {
        $this->authorizeSetup($token);

        return Inertia::render('auth/setup', ['token' => $token]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $this->authorizeSetup($token);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($data) {
            abort_if(User::query()->lockForUpdate()->exists(), 404);

            $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            $user->role = Role::Owner;
            $user->is_active = true;
            $user->must_change_password = false;
            $user->save();

            return $user;
        });

        @unlink(storage_path('app/setup.token'));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Akun Owner berhasil dibuat. Selamat datang!');
    }
}
