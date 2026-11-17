<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hanya Owner (route).
 * Password sementara ditampilkan SEKALI lewat flash session, tidak disimpan dalam bentuk asli di mana pun.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('users/index', [
            'users' => User::query()->where('email', 'not like', 'deleted-%@invalid.local')
                ->orderByDesc('is_active')->orderBy('role')->orderBy('name')->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role->value,
                    'role_label' => $u->role->label(),
                    'is_active' => $u->is_active,
                    'must_change_password' => $u->must_change_password,
                    'last_login' => $u->last_login_at?->locale('id')->diffForHumans(),
                    'is_me' => $u->id === $request->user()->id,
                ]),
            'roles' => array_map(fn (Role $r) => ['value' => $r->value, 'label' => $r->label()], Role::cases()),
        ]);
    }

    private function tempPassword(): string
    {
        return Str::password(12, symbols: false);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'lowercase', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(Role::class)],
        ], ['email.unique' => 'Email ini sudah dipakai akun lain.']);

        $temp = $this->tempPassword();
        $u = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $temp]);
        $u->role = Role::from($data['role']);
        $u->is_active = true;
        $u->must_change_password = true;
        $u->save();
        Audit::log('user.create', $u, null, ['email' => $u->email, 'role' => $u->role->value]);

        return back()->with('temp_password', ['name' => $u->name, 'email' => $u->email, 'password' => $temp]);
    }

    private function guardNotSelf(Request $request, User $user, string $what): void
    {
        if ($user->id === $request->user()->id) {
            abort(422, "Anda tidak bisa {$what} akun Anda sendiri.");
        }
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->guardNotSelf($request, $user, 'mengubah peran');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', Rule::enum(Role::class)],
        ]);
        $before = ['name' => $user->name, 'role' => $user->role->value];
        $user->name = $data['name'];
        $user->role = Role::from($data['role']);
        $user->save();
        Audit::log('user.update', $user, $before, $data);

        return back()->with('success', 'Perubahan disimpan.');
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        $this->guardNotSelf($request, $user, 'menonaktifkan');
        $user->is_active = ! $user->is_active;
        $user->save();
        if (! $user->is_active) {
            // Putus semua sesi aktifnya sekarang juga.
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        Audit::log($user->is_active ? 'user.activate' : 'user.deactivate', $user);

        return back()->with('success', $user->is_active ? "{$user->name} diaktifkan kembali." : "{$user->name} dinonaktifkan dan langsung keluar dari sistem.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->guardNotSelf($request, $user, 'mereset password');
        $temp = $this->tempPassword();
        $user->password = $temp;
        $user->must_change_password = true;
        $user->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        Audit::log('user.reset_password', $user);

        return back()->with('temp_password', ['name' => $user->name, 'email' => $user->email, 'password' => $temp]);
    }

    /**
     * "Hapus permanen" = data pribadi dihapus (nama, email, password diganti acak, akun mati),
     * tapi baris akunnya tetap ada agar riwayat pesanan yang pernah ia kerjakan tetap utuh (PRD).
     * Wajib ketik ulang password Owner.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->guardNotSelf($request, $user, 'menghapus');
        $request->validate(['password' => ['required', 'current_password']], ['password.current_password' => 'Password Anda salah.']);

        $old = ['name' => $user->name, 'email' => $user->email, 'role' => $user->role->value];
        $user->forceFill([
            'name' => 'Pengguna dihapus',
            'email' => 'deleted-'.$user->id.'-'.Str::random(8).'@invalid.local',
            'password' => Str::random(64),
            'is_active' => false,
            'remember_token' => null,
        ])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        Audit::log('user.delete', $user, $old);

        return back()->with('success', "Akun {$old['name']} dihapus. Riwayat pekerjaannya tetap tersimpan.");
    }
}
