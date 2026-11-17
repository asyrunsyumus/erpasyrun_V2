<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BaggageController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ShopSettingsController;
use App\Http\Controllers\CancellationController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MaktabahController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\WarehouseController;
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

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
        Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:30,1')->name('orders.store');
        Route::get('orders/lookup/customers', [OrderController::class, 'lookupCustomers'])->name('orders.lookup.customers');
        Route::get('orders/lookup/products', [OrderController::class, 'lookupProducts'])->name('orders.lookup.products');
        Route::get('orders/{order}', [OrderController::class, 'show'])->whereNumber('order')->name('orders.show');
        Route::post('orders/{order}/paid', [OrderController::class, 'markPaid'])->name('orders.paid');
        Route::post('orders/{order}/label', [OrderController::class, 'uploadLabel'])->name('orders.label.upload');
        Route::get('orders/{order}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');

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

    // ---------- Belanja Mesir: Tim Mesir + Owner; koreksi & kurs default hanya Owner ----------
    Route::middleware(Navigation::roles('purchases'))->group(function () {
        Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('purchases/print', [PurchaseController::class, 'print'])->name('purchases.print');
        Route::post('purchases', [PurchaseController::class, 'store'])->middleware('throttle:60,1')->name('purchases.store');
    });
    Route::middleware('role:owner')->group(function () {
        Route::put('purchases/{purchase}', [PurchaseController::class, 'update'])->whereNumber('purchase')->name('purchases.update');
        Route::post('purchases/rate', [PurchaseController::class, 'setRate'])->name('purchases.rate');

        Route::get('baggage', [BaggageController::class, 'index'])->name('baggage.index');
        Route::post('baggage', [BaggageController::class, 'store'])->name('baggage.store');
        Route::put('baggage/{schedule}', [BaggageController::class, 'update'])->whereNumber('schedule')->name('baggage.update');
        Route::post('baggage/{schedule}/move', [BaggageController::class, 'move'])->whereNumber('schedule')->name('baggage.move');
        Route::delete('baggage/{schedule}', [BaggageController::class, 'destroy'])->whereNumber('schedule')->name('baggage.destroy');
    });

    // ---------- Gudang & Packing: HANYA Tim Gudang (Owner pun tidak, sesuai PRD) ----------
    Route::middleware('role:gudang')->group(function () {
        Route::get('warehouse', [WarehouseController::class, 'index'])->name('warehouse.index');
        Route::post('warehouse/inbound/{line}/receive', [WarehouseController::class, 'receive'])->whereNumber('line')->name('warehouse.receive');
        Route::post('warehouse/items/{item}/check', [WarehouseController::class, 'check'])->whereNumber('item')->name('warehouse.check');
        Route::post('warehouse/orders/{order}/finish', [WarehouseController::class, 'finish'])->whereNumber('order')->name('warehouse.finish');
    });

    // ---------- Stok Ready: lihat (Owner, Admin, Gudang); lapor selisih (Gudang, Owner); putuskan (Owner) ----------
    Route::middleware(Navigation::roles('stock'))->group(function () {
        Route::get('stock', [StockController::class, 'index'])->name('stock.index');
        Route::get('stock/print', [StockController::class, 'print'])->name('stock.print');
    });
    Route::post('stock/counts', [StockController::class, 'report'])->middleware('role:owner,gudang')->name('stock.report');
    Route::post('stock/counts/{count}/decide', [StockController::class, 'decide'])->whereNumber('count')->middleware('role:owner')->name('stock.decide');

    // ---------- Pembatalan: Owner & Admin ----------
    Route::middleware('role:owner,admin')->group(function () {
        Route::get('cancellations', [CancellationController::class, 'index'])->name('cancellations.index');
        Route::post('orders/{order}/cancel', [CancellationController::class, 'store'])->name('cancellations.store');
        Route::post('cancellations/{cancellation}/approve', [CancellationController::class, 'approve'])->whereNumber('cancellation')->name('cancellations.approve');
        Route::post('cancellations/{cancellation}/reject', [CancellationController::class, 'reject'])->whereNumber('cancellation')->name('cancellations.reject');
        Route::get('cancellations/{cancellation}/proof', [CancellationController::class, 'proof'])->whereNumber('cancellation')->name('cancellations.proof');
    });

    // ---------- Pengguna: hanya Owner ----------
    Route::middleware('role:owner')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('throttle:10,1')->name('users.reset');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('throttle:10,1')->name('users.destroy');
    });

    // ---------- Laporan, Biaya, Log, Pengaturan toko: hanya Owner ----------
    Route::middleware('role:owner')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->whereNumber('expense')->name('expenses.update');
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->whereNumber('expense')->name('expenses.destroy');
        Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('shop-settings', [ShopSettingsController::class, 'edit'])->name('shop.index');
        Route::put('shop-settings', [ShopSettingsController::class, 'update'])->name('shop.update');
        Route::post('backup', [BackupController::class, 'download'])->middleware('throttle:5,10')->name('backup.download');
    });

    // ---------- Notifikasi (setiap orang miliknya sendiri) ----------
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');

    // Resi: Owner, Admin, dan Gudang (mencetak saat packing).
    Route::get('orders/{order}/label', [OrderController::class, 'label'])->middleware('role:owner,admin,gudang')->name('orders.label');

    // Modul yang belum dibangun: tetap dilindungi izin peran, menampilkan halaman "sedang disiapkan".
    $built = ['dashboard', 'maktabahs', 'customers', 'products', 'orders', 'purchases', 'baggage', 'warehouse', 'stock', 'cancellations', 'users', 'reports', 'expenses', 'audit', 'shop'];
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
