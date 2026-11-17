<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactor;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Langkah kedua login: kode 6 digit dari aplikasi authenticator, atau satu kode pemulihan. */
class TwoFactorChallengeController extends Controller
{
    private function pending(Request $request): ?User
    {
        $p = $request->session()->get('login.2fa');
        if (! $p || $p['expires'] < now()->timestamp) {
            $request->session()->forget('login.2fa');

            return null;
        }

        return User::where('id', $p['id'])->where('is_active', true)->first();
    }

    public function show(Request $request): Response|RedirectResponse
    {
        if (! $this->pending($request)) {
            return redirect()->route('login')->withErrors(['email' => 'Sesi verifikasi habis. Silakan masuk lagi.']);
        }

        return Inertia::render('auth/two-factor-challenge');
    }

    public function store(Request $request, TwoFactor $tf): RedirectResponse
    {
        $user = $this->pending($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'Sesi verifikasi habis. Silakan masuk lagi.']);
        }

        $key = '2fa:'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Terlalu banyak kode salah. Coba lagi dalam '.ceil(RateLimiter::availableIn($key) / 60).' menit.']);
        }

        $ok = false;
        if ($request->filled('recovery_code')) {
            $ok = $tf->useRecoveryCode($user, (string) $request->input('recovery_code'));
            if ($ok) {
                Audit::log('user.2fa_recovery_used', $user);
            }
        } else {
            $step = $tf->verify($tf->secretOf($user), (string) $request->input('code'), $user->two_factor_last_step);
            if ($step !== false) {
                $user->forceFill(['two_factor_last_step' => $step])->save();
                $ok = true;
            }
        }

        if (! $ok) {
            RateLimiter::hit($key, 15 * 60);
            throw ValidationException::withMessages([$request->filled('recovery_code') ? 'recovery_code' : 'code' => 'Kode salah atau sudah dipakai.']);
        }

        RateLimiter::clear($key);
        $remember = (bool) $request->session()->pull('login.2fa')['remember'];
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
