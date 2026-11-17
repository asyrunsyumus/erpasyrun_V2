<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeployController;
use App\Support\Navigation;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/dashboard')->name('home');

// Pasca-deploy (migrasi + cache). Tanpa sesi agar bisa jalan sebelum tabel sessions ada.
Route::post('_deploy', DeployController::class)
    ->withoutMiddleware([\Illuminate\Session\Middleware\StartSession::class, \Illuminate\View\Middleware\ShareErrorsFromSession::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, \App\Http\Middleware\EnsureAccountUsable::class, \App\Http\Middleware\HandleInertiaRequests::class])
    ->middleware('throttle:5,1');

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
