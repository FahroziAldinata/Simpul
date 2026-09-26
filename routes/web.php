<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\SekolahAktifController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::post('sekolah-aktif', [SekolahAktifController::class, 'update'])->name('sekolah-aktif.update');
    Route::get('pegawai/{pegawai}', [PegawaiController::class, 'show'])->name('pegawai.show');
    Route::get('audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:audit_log.view')
        ->name('audit-logs.index');
});

require __DIR__.'/settings.php';
