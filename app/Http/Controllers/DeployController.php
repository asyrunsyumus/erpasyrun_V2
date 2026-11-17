<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjalankan migrasi & cache setelah deploy, untuk hosting tanpa SSH/cron yang bisa dipakai.
 *
 * Aman karena:
 *  - Hanya aktif bila file storage/app/deploy.token ada (diunggah manual ke server, tidak di Git).
 *  - Token dibandingkan dengan hash_equals (anti timing attack) dan minimal 40 karakter.
 *  - File token LANGSUNG dihapus sebelum perintah dijalankan → sekali pakai.
 *  - Selain itu endpoint selalu menjawab 404, tanpa membocorkan bahwa ia ada.
 */
class DeployController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $file = storage_path('app/deploy.token');
        $expected = is_file($file) ? trim((string) file_get_contents($file)) : '';
        $given = (string) $request->input('token', '');

        if (strlen($expected) < 40 || ! hash_equals($expected, $given)) {
            abort(404);
        }

        @unlink($file);

        $commands = [['migrate', ['--force' => true]]];
        // Saat tes otomatis, jangan menulis cache config/route (akan "membekukan" config tes ke laptop).
        if (! app()->runningUnitTests()) {
            array_push($commands, ['optimize:clear', []], ['config:cache', []], ['route:cache', []], ['view:cache', []]);
        }

        $log = [];
        foreach ($commands as [$cmd, $args]) {
            $code = Artisan::call($cmd, $args);
            $log[] = "$ php artisan $cmd (exit $code)\n".trim(Artisan::output());
        }

        return response(implode("\n\n", $log), 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store']);
    }
}
