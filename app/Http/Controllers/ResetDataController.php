<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEMENTARA: menjalankan erp:kosongkan-data sekali untuk membersihkan data uji coba.
 * Sama pengamanannya dengan DeployController: hanya aktif bila storage/app/reset.token ada,
 * token dibandingkan dengan hash_equals, dan file token dihapus sebelum perintah jalan.
 * Route ini harus dihapus setelah dipakai.
 */
class ResetDataController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $file = storage_path('app/reset.token');
        $expected = is_file($file) ? trim((string) file_get_contents($file)) : '';
        $given = (string) $request->input('token', '');

        if (strlen($expected) < 40 || ! hash_equals($expected, $given)) {
            abort(404);
        }

        @unlink($file);

        $code = Artisan::call('erp:kosongkan-data', ['--confirm' => true]);

        return response("erp:kosongkan-data (exit $code)\n".trim(Artisan::output()), 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store']);
    }
}
