<?php

use App\Http\Controllers\AlokasiJamMapelController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BerkasSiswaController;
use App\Http\Controllers\JurusanController;
use App\Http\Controllers\KalenderController;
use App\Http\Controllers\KartuPegawaiController;
use App\Http\Controllers\KartuSiswaController;
use App\Http\Controllers\MataPelajaranController;
use App\Http\Controllers\MutasiSiswaController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\PeriodeAktifController;
use App\Http\Controllers\RombelController;
use App\Http\Controllers\RuangController;
use App\Http\Controllers\SekolahAktifController;
use App\Http\Controllers\SekolahController;
use App\Http\Controllers\SiswaAksiMassalController;
use App\Http\Controllers\SiswaController;
use App\Http\Controllers\TahunAjaranController;
use App\Http\Controllers\UserPreferenceController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::post('sekolah-aktif', [SekolahAktifController::class, 'update'])->name('sekolah-aktif.update');
    Route::post('periode-aktif', [PeriodeAktifController::class, 'update'])->name('periode-aktif.update');
    // Data Kepegawaian (T-06.04 & T-06.05) & Kartu Pegawai (T-06.06)
    Route::get('pegawai', [PegawaiController::class, 'index'])->name('pegawai.index');
    Route::post('pegawai', [PegawaiController::class, 'store'])->name('pegawai.store');
    Route::get('pegawai/kartu/cetak-massal', [KartuPegawaiController::class, 'cetakMassal'])->name('pegawai.kartu.massal');
    Route::get('pegawai/{pegawai}', [PegawaiController::class, 'show'])->name('pegawai.show');
    Route::get('pegawai/{pegawai}/kartu', [KartuPegawaiController::class, 'show'])->name('pegawai.kartu');
    Route::put('pegawai/{pegawai}', [PegawaiController::class, 'update'])->name('pegawai.update');
    Route::delete('pegawai/{pegawai}', [PegawaiController::class, 'destroy'])->name('pegawai.destroy');
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

    // Data Induk: Tahun Ajaran & Semester
    Route::get('tahun-ajaran', [TahunAjaranController::class, 'index'])->name('tahun-ajaran.index');
    Route::post('tahun-ajaran', [TahunAjaranController::class, 'store'])
        ->middleware('permission:data_induk.create')
        ->name('tahun-ajaran.store');
    Route::put('tahun-ajaran/{tahunAjaran}', [TahunAjaranController::class, 'update'])
        ->middleware('permission:data_induk.update')
        ->name('tahun-ajaran.update');
    Route::delete('tahun-ajaran/{tahunAjaran}', [TahunAjaranController::class, 'destroy'])
        ->middleware('permission:data_induk.delete')
        ->name('tahun-ajaran.destroy');
    Route::post('semester/{semester}/aktifkan', [TahunAjaranController::class, 'activateSemester'])
        ->middleware('permission:data_induk.update')
        ->name('semester.activate');

    // Data Induk: Jurusan / Program Keahlian
    Route::get('jurusan', [JurusanController::class, 'index'])->name('jurusan.index');
    Route::post('jurusan', [JurusanController::class, 'store'])
        ->middleware('permission:data_induk.create')
        ->name('jurusan.store');
    Route::put('jurusan/{jurusan}', [JurusanController::class, 'update'])
        ->middleware('permission:data_induk.update')
        ->name('jurusan.update');
    Route::delete('jurusan/{jurusan}', [JurusanController::class, 'destroy'])
        ->middleware('permission:data_induk.delete')
        ->name('jurusan.destroy');

    // Data Induk: Ruang & Fasilitas
    Route::get('ruang', [RuangController::class, 'index'])->name('ruang.index');
    Route::post('ruang', [RuangController::class, 'store'])
        ->middleware('permission:data_induk.create')
        ->name('ruang.store');
    Route::put('ruang/{ruang}', [RuangController::class, 'update'])
        ->middleware('permission:data_induk.update')
        ->name('ruang.update');
    Route::delete('ruang/{ruang}', [RuangController::class, 'destroy'])
        ->middleware('permission:data_induk.delete')
        ->name('ruang.destroy');

    // Data Induk: Mata Pelajaran
    Route::get('mata-pelajaran', [MataPelajaranController::class, 'index'])->name('mata-pelajaran.index');
    Route::post('mata-pelajaran', [MataPelajaranController::class, 'store'])
        ->middleware('permission:data_induk.create')
        ->name('mata-pelajaran.store');
    Route::put('mata-pelajaran/{mataPelajaran}', [MataPelajaranController::class, 'update'])
        ->middleware('permission:data_induk.update')
        ->name('mata-pelajaran.update');
    Route::delete('mata-pelajaran/{mataPelajaran}', [MataPelajaranController::class, 'destroy'])
        ->middleware('permission:data_induk.delete')
        ->name('mata-pelajaran.destroy');

    // Data Induk: Rombel / Rombongan Belajar
    Route::get('rombel', [RombelController::class, 'index'])->name('rombel.index');
    Route::post('rombel', [RombelController::class, 'store'])
        ->middleware('permission:data_induk.create')
        ->name('rombel.store');
    Route::put('rombel/{rombel}', [RombelController::class, 'update'])
        ->middleware('permission:data_induk.update')
        ->name('rombel.update');
    Route::delete('rombel/{rombel}', [RombelController::class, 'destroy'])
        ->middleware('permission:data_induk.delete')
        ->name('rombel.destroy');

    // Data Induk: Alokasi Jam Mapel per Rombel
    Route::get('alokasi-jam', [AlokasiJamMapelController::class, 'index'])->name('alokasi-jam.index');
    Route::post('alokasi-jam', [AlokasiJamMapelController::class, 'store'])
        ->middleware('permission:data_induk.create')
        ->name('alokasi-jam.store');
    Route::put('alokasi-jam/{alokasiJamMapel}', [AlokasiJamMapelController::class, 'update'])
        ->middleware('permission:data_induk.update')
        ->name('alokasi-jam.update');
    Route::delete('alokasi-jam/{alokasiJamMapel}', [AlokasiJamMapelController::class, 'destroy'])
        ->middleware('permission:data_induk.delete')
        ->name('alokasi-jam.destroy');

    // Preferensi User (Kolom tabel, dsb)
    Route::patch('user/preferences', [UserPreferenceController::class, 'update'])
        ->name('user.preferences.update');

    // Data Siswa (T-05.01 s/d T-05.08)
    Route::post('siswa/check-nisn', [SiswaController::class, 'checkNisn'])
        ->middleware('throttle:30,1')
        ->name('siswa.check-nisn');
    Route::get('siswa', [SiswaController::class, 'index'])->name('siswa.index');
    Route::post('siswa', [SiswaController::class, 'store'])->name('siswa.store');
    Route::get('siswa/{siswa}', [SiswaController::class, 'show'])->name('siswa.show');
    Route::get('siswa/{siswa}/edit', [SiswaController::class, 'edit'])->name('siswa.edit');
    Route::put('siswa/{siswa}', [SiswaController::class, 'update'])->name('siswa.update');
    Route::delete('siswa/{siswa}', [SiswaController::class, 'destroy'])->name('siswa.destroy');

    // Berkas Siswa (T-06.01)
    Route::post('siswa/{siswa}/berkas', [BerkasSiswaController::class, 'store'])->name('siswa.berkas.store');
    Route::get('siswa/{siswa}/berkas/{jenis}/url', [BerkasSiswaController::class, 'showUrl'])->name('siswa.berkas.url');
    Route::delete('siswa/{siswa}/berkas/{jenis}', [BerkasSiswaController::class, 'destroy'])->name('siswa.berkas.destroy');

    // Mutasi Siswa & Riwayat Kelas (T-06.02 & T-06.03)
    Route::get('siswa/{siswa}/mutasi', [MutasiSiswaController::class, 'index'])->name('siswa.mutasi.index');
    Route::post('siswa/{siswa}/mutasi', [MutasiSiswaController::class, 'store'])->name('siswa.mutasi.store');
    Route::post('mutasi/{mutasi}/batal', [MutasiSiswaController::class, 'batalkan'])->name('siswa.mutasi.batal');

    // Kartu Digital PDF Siswa & Rombel (T-06.06)
    Route::get('siswa/{siswa}/kartu', [KartuSiswaController::class, 'show'])->name('siswa.kartu');
    Route::get('rombel/{rombel}/kartu', [KartuSiswaController::class, 'rombel'])->name('rombel.kartu');

    // Aksi Massal Data Siswa (T-06.07)
    Route::post('siswa/aksi-massal/rombel', [SiswaAksiMassalController::class, 'ubahRombel'])->name('siswa.aksi-massal.rombel');
    Route::post('siswa/aksi-massal/status', [SiswaAksiMassalController::class, 'ubahStatus'])->name('siswa.aksi-massal.status');
    Route::match(['get', 'post'], 'siswa/aksi-massal/ekspor', [SiswaAksiMassalController::class, 'ekspor'])->name('siswa.aksi-massal.ekspor');
});

require __DIR__.'/settings.php';
