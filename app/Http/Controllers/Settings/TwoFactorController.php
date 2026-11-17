<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\TwoFactor;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Pengaturan 2FA milik sendiri. Wajib untuk Owner, opsional untuk peran lain. */
class TwoFactorController extends Controller
{
    public function show(Request $request, TwoFactor $tf): Response
    {
        $user = $request->user();
        $pendingSecret = $request->session()->get('2fa.setup');

        return Inertia::render('settings/two-factor', [
            'enabled' => $tf->enabled($user),
            'required' => $user->isOwner() && config('erp.require_owner_2fa'),
            'setup' => $pendingSecret ? ['qr' => $tf->qrSvg($user, $pendingSecret), 'secret' => trim(chunk_split($pendingSecret, 4, ' '))] : null,
            'recoveryLeft' => $user->two_factor_recovery_codes ? count(decrypt($user->two_factor_recovery_codes)) : 0,
            'recoveryCodes' => $request->session()->get('2fa.codes'),
        ]);
    }

    /** Langkah 1: buat rahasia baru (belum aktif sampai dikonfirmasi). */
    public function start(Request $request, TwoFactor $tf): RedirectResponse
    {
        $request->session()->put('2fa.setup', $tf->newSecret());

        return back();
    }

    /** Langkah 2: konfirmasi dengan kode dari aplikasi → aktif + 8 kode pemulihan (tampil sekali). */
    public function confirm(Request $request, TwoFactor $tf): RedirectResponse
    {
        $secret = $request->session()->get('2fa.setup');
        abort_unless($secret, 409);
        $step = $tf->verify($secret, (string) $request->input('code'), null);
        if ($step === false) {
            throw ValidationException::withMessages(['code' => 'Kode tidak cocok. Pastikan jam di HP benar, lalu coba kode terbaru.']);
        }

        $user = $request->user();
        $user->forceFill(['two_factor_secret' => encrypt($secret), 'two_factor_confirmed_at' => now(), 'two_factor_last_step' => $step])->save();
        $codes = $tf->newRecoveryCodes($user);
        $request->session()->forget('2fa.setup');
        Audit::log('user.2fa_enable', $user);

        return back()->with('2fa.codes', $codes)->with('success', 'Verifikasi dua langkah aktif.');
    }

    public function regenerateCodes(Request $request, TwoFactor $tf): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']], ['password.current_password' => 'Password salah.']);
        abort_unless($tf->enabled($request->user()), 409);
        $codes = $tf->newRecoveryCodes($request->user());
        Audit::log('user.2fa_new_codes', $request->user());

        return back()->with('2fa.codes', $codes);
    }

    /** Owner tidak boleh mematikan 2FA (wajib); peran lain boleh dengan password. */
    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->isOwner() && config('erp.require_owner_2fa')) {
            abort(403, 'Verifikasi dua langkah wajib untuk Owner.');
        }
        $request->validate(['password' => ['required', 'current_password']], ['password.current_password' => 'Password salah.']);
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->save();
        Audit::log('user.2fa_disable', $user);

        return back()->with('success', 'Verifikasi dua langkah dimatikan.');
    }
}
