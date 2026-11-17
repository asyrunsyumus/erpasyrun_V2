<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cadangan database (.sql) yang bisa diunduh Owner, lalu disimpan di luar server (mis. Google Drive).
 * Dibuat dengan PHP murni karena hosting tidak mengizinkan menjalankan mysqldump.
 * Tabel teknis (sesi login, cache, antrean) tidak ikut — isinya sementara dan sesi berisi token login.
 */
class BackupController extends Controller
{
    private const SKIP = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'];

    public function download(Request $request): StreamedResponse
    {
        $request->validate(['password' => ['required', 'current_password']], ['password.current_password' => 'Password salah.']);
        Audit::log('backup.download', null, null, ['tables' => 'all']);

        $driver = DB::getDriverName();
        $name = 'cadangan-erp-'.now()->format('Y-m-d-His').'.sql';

        return response()->streamDownload(function () use ($driver) {
            $pdo = DB::connection()->getPdo();
            echo "-- Cadangan ERP Asyrun Syumus\n-- Dibuat ".now()->toDateTimeString()." (".config('app.timezone').")\n-- RAHASIA: berisi data pembeli & akun. Simpan di tempat aman.\n\n";

            if ($driver === 'mysql') {
                echo "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n";
                $tables = array_map(fn ($r) => array_values((array) $r)[0], DB::select('SHOW TABLES'));
            } else {
                $tables = array_map(fn ($r) => $r->name, DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"));
            }

            foreach ($tables as $table) {
                if (in_array($table, self::SKIP, true)) {
                    continue;
                }
                $q = $driver === 'mysql' ? "`{$table}`" : "\"{$table}\"";
                echo "-- ----------------------------------------\n-- Tabel {$table}\n";
                if ($driver === 'mysql') {
                    $create = array_values((array) DB::selectOne("SHOW CREATE TABLE {$q}"))[1];
                    echo "DROP TABLE IF EXISTS {$q};\n{$create};\n";
                } else {
                    echo DB::selectOne("SELECT sql FROM sqlite_master WHERE name = ?", [$table])->sql.";\n";
                }

                foreach (DB::table($table)->orderByRaw('1')->lazy(500) as $row) {
                    $vals = array_map(fn ($v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v)), (array) $row);
                    echo "INSERT INTO {$q} VALUES (".implode(',', $vals).");\n";
                }
                echo "\n";
                flush();
            }

            if ($driver === 'mysql') {
                echo "SET FOREIGN_KEY_CHECKS=1;\n";
            }
        }, $name, ['Content-Type' => 'application/sql; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
