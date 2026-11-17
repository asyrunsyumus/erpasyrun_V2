<?php

use App\Http\Controllers\DashboardController;
use App\Support\Navigation;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/dashboard')->name('home');

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
