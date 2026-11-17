<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 1. Akun yang dinonaktifkan Owner langsung keluar dengan pesan jelas
 *    (sistem lama menampilkan "terlalu banyak pengalihan").
 * 2. Akun dengan password sementara wajib menggantinya dulu.
 */
class EnsureAccountUsable
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Akun Anda sudah dinonaktifkan. Hubungi Owner.',
            ]);
        }

        if ($user && $user->must_change_password
            && ! $request->routeIs('password.edit', 'password.update', 'logout')) {
            return redirect()->route('password.edit')
                ->with('status', 'Silakan ganti password sementara Anda sebelum mulai bekerja.');
        }

        return $next($request);
    }
}
