<?php

namespace App\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;

/**
 * Pengganti "@php artisan package:discover" di composer.json.
 *
 * Shared hosting Hostinger mematikan proc_open, sehingga Composer tidak bisa menjalankan
 * "@php artisan ..." sebagai proses terpisah. Fungsi ini membangun manifest paket
 * di dalam proses Composer itu sendiri.
 */
class ComposerHooks
{
    public static function discoverPackages(): void
    {
        $base = getcwd();
        $cache = $base.'/bootstrap/cache';

        if (! is_dir($cache)) {
            mkdir($cache, 0755, true);
        }

        @unlink($cache.'/services.php');

        (new PackageManifest(new Filesystem, $base, $cache.'/packages.php'))->build();
    }
}
