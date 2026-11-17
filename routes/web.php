<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\SetupController;
use App\Support\Navigation;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/dashboard')->name('home');

// Pasca-deploy (migrasi + cache). Tanpa sesi & tanpa throttle karena keduanya butuh tabel
// yang justru dibuat oleh endpoint ini. Pengamannya: token acak ≥40 karakter, sekali pakai.
Route::post('_deploy', DeployController::class)
    ->withoutMiddleware([\Illuminate\Session\Middleware\StartSession::class, \Illuminate\View\Middleware\ShareErrorsFromSession::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, \App\Http\Middleware\EnsureAccountUsable::class, \App\Http\Middleware\HandleInertiaRequests::class]);

// Setup akun Owner pertama (sekali pakai, bertoken).
Route::middleware(['guest', 'throttle:10,1'])->group(function () {
    Route::get('setup/{token}', [SetupController::class, 'show'])->name('setup.show');
    Route::post('setup/{token}', [SetupController::class, 'store'])->name('setup.store');
});

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Modul yang belum dibangun: tetap dilindungi izin peran, menampilkan halaman "sedang disiapkan".
    foreach (Navigation::modules() as $key => $module) {
        if ($key === 'dashboard') {
            continue;
        }

        Route::get(ltrim($module['path'], '/'), fn () => Inertia::render('coming-soon', ['title' => $module['label']]))
            ->middleware(Navigation::roles($key))
            ->name($key.'.index');
    }
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
