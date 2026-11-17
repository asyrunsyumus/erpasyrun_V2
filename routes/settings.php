<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/two-factor', [TwoFactorController::class, 'show'])->name('two-factor.show');
    Route::post('settings/two-factor/start', [TwoFactorController::class, 'start'])->name('two-factor.start');
    Route::post('settings/two-factor/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:10,1')->name('two-factor.confirm');
    Route::post('settings/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateCodes'])->middleware('throttle:5,1')->name('two-factor.codes');
    Route::delete('settings/two-factor', [TwoFactorController::class, 'disable'])->middleware('throttle:5,1')->name('two-factor.disable');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance');
});
