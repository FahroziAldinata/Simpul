<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-09.05 — Extend StatusAbsensi enum untuk mencakup status hari izin.
 *
 * Nilai baru ditambahkan ke kolom status di tabel absensi:
 *  - izin:   Izin umum/urusan keluarga
 *  - sakit:  Sakit (bisa ada surat)
 *  - cuti:   Cuti tahunan (mengurangi kuota)
 *  - dinas:  Dinas luar / perjalanan dinas
 *
 * Tidak ada perubahan constraint DB karena status disimpan sebagai string (aturan D7).
 * PHP Enum StatusAbsensi diupdate terpisah.
 *
 * Untuk baris absensi izin:
 *  - jenis = 'masuk'    (satu baris per tanggal, tidak ada baris 'pulang')
 *  - sumber = 'izin'    (tambah nilai baru di kolom sumber)
 *  - waktu_server = null
 *  - menit_terlambat = 0
 */
return new class extends Migration
{
    public function up(): void
    {
        // Kolom sumber perlu tambah nilai 'izin' — karena string (D7) tidak perlu ALTER TYPE
        // Cukup pastikan dokumentasi/enum PHP konsisten. Tidak ada DDL yang diperlukan.
        // Kolom status di absensi juga sudah string, tidak perlu alter.

        // Tambah kolom sumber_izin_id ke absensi untuk traceability:
        // kalau absensi berasal dari izin yang disetujui, simpan referensinya
        Schema::table('absensi', function (Blueprint $table) {
            $table->uuid('pengajuan_izin_id')->nullable()->after('alasan_manual');
            $table->foreign('pengajuan_izin_id')->references('id')->on('pengajuan_izin')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            $table->dropForeign(['pengajuan_izin_id']);
            $table->dropColumn('pengajuan_izin_id');
        });
    }
};
