<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-09.02 — Tabel pengajuan izin/cuti/sakit/dinas.
 *
 * Status flow:
 *   draft → menunggu → disetujui | ditolak | dibatalkan
 *
 * 'draft' dipakai saat pengajuan disimpan tapi belum disubmit (future).
 * Untuk Minggu 9 semua pengajuan langsung 'menunggu'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_izin', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sekolah_id');
            $table->foreign('sekolah_id')->references('id')->on('sekolah')->cascadeOnDelete();
            $table->uuid('pegawai_id');
            $table->foreign('pegawai_id')->references('id')->on('pegawai')->cascadeOnDelete();
            $table->uuid('jenis_izin_id');
            $table->foreign('jenis_izin_id')->references('id')->on('jenis_izin')->restrictOnDelete();

            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->text('alasan');
            $table->string('lampiran_path')->nullable();
            $table->string('lampiran_mime')->nullable();    // untuk verifikasi MIME asli
            $table->string('status')->default('menunggu'); // draft|menunggu|disetujui|ditolak|dibatalkan
            $table->timestamps();

            $table->index(['sekolah_id', 'pegawai_id', 'status']);
            $table->index(['sekolah_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_izin');
    }
};
