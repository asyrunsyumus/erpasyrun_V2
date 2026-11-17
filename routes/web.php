<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MaktabahController;
use App\Http\Controllers\ProductController;
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

    // ---------- Maktabah: Owner kelola, Tim Mesir hanya lihat ----------
    Route::get('maktabahs', [MaktabahController::class, 'index'])->middleware(Navigation::roles('maktabahs'))->name('maktabahs.index');
    Route::middleware('role:owner')->group(function () {
        Route::post('maktabahs', [MaktabahController::class, 'store'])->name('maktabahs.store');
        Route::put('maktabahs/{maktabah}', [MaktabahController::class, 'update'])->name('maktabahs.update');
        Route::delete('maktabahs/{maktabah}', [MaktabahController::class, 'destroy'])->name('maktabahs.destroy');
    });

    // ---------- Customer & Produk: Owner + Admin; hapus hanya Owner ----------
    Route::middleware('role:owner,admin')->group(function () {
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');

        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        // POST (bukan PUT) agar bisa membawa file foto.
        Route::post('products/{product}', [ProductController::class, 'update'])->name('products.update');

        Route::get('imports', [ImportController::class, 'create'])->name('imports.create');
        Route::get('imports/template/{type}', [ImportController::class, 'template'])->name('imports.template');
        Route::post('imports', [ImportController::class, 'upload'])->middleware('throttle:20,1')->name('imports.upload');
        Route::get('imports/{import}', [ImportController::class, 'show'])->whereNumber('import')->name('imports.show');
        Route::post('imports/{import}/confirm', [ImportController::class, 'confirm'])->whereNumber('import')->name('imports.confirm');
    });
    Route::middleware('role:owner')->group(function () {
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    });

    // Foto kitab: semua peran yang login boleh melihat (Tim Mesir & Gudang mencocokkan fisik kitab).
    Route::get('products/{product}/photo', [ProductController::class, 'photo'])->name('products.photo');

    // Modul yang belum dibangun: tetap dilindungi izin peran, menampilkan halaman "sedang disiapkan".
    $built = ['dashboard', 'maktabahs', 'customers', 'products'];
    foreach (Navigation::modules() as $key => $module) {
        if (in_array($key, $built, true)) {
            continue;
        }

        Route::get(ltrim($module['path'], '/'), fn () => Inertia::render('coming-soon', ['title' => $module['label']]))
            ->middleware(Navigation::roles($key))
            ->name($key.'.index');
    }
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
