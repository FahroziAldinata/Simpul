<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\KalenderController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\SekolahAktifController;
use App\Http\Controllers\SekolahController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::post('sekolah-aktif', [SekolahAktifController::class, 'update'])->name('sekolah-aktif.update');
    Route::get('pegawai/{pegawai}', [PegawaiController::class, 'show'])->name('pegawai.show');
    Route::get('audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:audit_log.view')
        ->name('audit-logs.index');

    // Data Induk: Profil Sekolah
    Route::get('profil-sekolah', [SekolahController::class, 'edit'])->name('profil-sekolah.edit');
    Route::post('profil-sekolah', [SekolahController::class, 'update'])->name('profil-sekolah.update');
    Route::get('sekolah/{sekolah}', [SekolahController::class, 'edit'])->name('sekolah.edit');
    Route::post('sekolah/{sekolah}', [SekolahController::class, 'update'])->name('sekolah.update');

    // Data Induk: Kalender & Jam Kerja Operasional
    Route::get('kalender', [KalenderController::class, 'index'])->name('kalender.index');
    Route::post('kalender/hari-libur', [KalenderController::class, 'storeHariLibur'])
        ->middleware('permission:data_induk.create')
        ->name('kalender.hari-libur.store');
    Route::delete('kalender/hari-libur/{hariLibur}', [KalenderController::class, 'destroyHariLibur'])
        ->middleware('permission:data_induk.delete')
        ->name('kalender.hari-libur.destroy');
    Route::post('kalender/jam-kerja', [KalenderController::class, 'updateJamKerja'])
        ->middleware('permission:data_induk.update')
        ->name('kalender.jam-kerja.update');
});

require __DIR__.'/settings.php';
