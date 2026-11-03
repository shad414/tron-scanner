<?php

use App\Http\Controllers\LoginController;
use App\Http\Controllers\ScanController;
use App\Http\Middleware\RequireAdmin;
use App\Http\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'form'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware(RequirePassword::class)->group(function () {
    // Each scan costs ~50 API calls, so cap scans per visitor
    Route::get('/', [ScanController::class, 'index'])->name('scan')->middleware('throttle:scans');

    // Other visitors' scans and Telegram chat IDs: admin only
    Route::middleware(RequireAdmin::class)->group(function () {
        Route::get('/history', [ScanController::class, 'history'])->name('history');
        Route::get('/history/{scan}', [ScanController::class, 'show'])->name('history.show');

        Route::get('/watches', [ScanController::class, 'watches'])->name('watches');
        Route::post('/watches', [ScanController::class, 'watch'])->name('watches.store');
        Route::delete('/watches/{watch}', [ScanController::class, 'unwatch'])->name('watches.destroy');
        Route::post('/watches/test', [ScanController::class, 'testTelegram'])->name('watches.test')->middleware('throttle:5,1');
    });
});
