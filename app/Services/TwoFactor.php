<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Verifikasi dua langkah (TOTP, mis. Google Authenticator / Microsoft Authenticator).
 *  - Rahasia disimpan terenkripsi (APP_KEY).
 *  - Kode yang sudah dipakai tidak bisa dipakai ulang (two_factor_last_step).
 *  - 8 kode pemulihan sekali pakai, disimpan sebagai hash.
 */
class TwoFactor
{
    public function __construct(private Google2FA $g = new Google2FA) {}

    public function newSecret(): string
    {
        return $this->g->generateSecretKey(32);
    }

    public function qrSvg(User $user, string $secret): string
    {
        $url = $this->g->getQRCodeUrl('ERP Asyrun Syumus', $user->email, $secret);
        $writer = new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd));

        return $writer->writeString($url);
    }

    /** Cek kode 6 digit terhadap rahasia; menolak kode yang sama/lebih lama dari yang terakhir dipakai. */
    public function verify(string $secret, string $code, ?int $lastStep): int|false
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return false;
        }
        $step = $this->g->verifyKeyNewer($secret, $code, $lastStep, 1);

        return $step === false ? false : (int) ($step === true ? floor(time() / 30) : $step);
    }

    /** @return list<string> kode asli (ditampilkan sekali); yang disimpan hanya hash-nya. */
    public function newRecoveryCodes(User $user): array
    {
        $codes = collect(range(1, 8))->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))->all();
        $user->forceFill(['two_factor_recovery_codes' => encrypt(array_map(fn ($c) => Hash::make($c), $codes))])->save();

        return $codes;
    }

    /** Pakai satu kode pemulihan (lalu dibuang). */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $code = Str::lower(trim($code));
        $hashes = $user->two_factor_recovery_codes ? decrypt($user->two_factor_recovery_codes) : [];
        foreach ($hashes as $i => $h) {
            if (Hash::check($code, $h)) {
                unset($hashes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => encrypt(array_values($hashes))])->save();

                return true;
            }
        }

        return false;
    }

    public function enabled(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null && $user->two_factor_secret !== null;
    }

    public function secretOf(User $user): ?string
    {
        return $user->two_factor_secret ? decrypt($user->two_factor_secret) : null;
    }
}
