<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-09.06 — Tabel kuota cuti tahunan per pegawai per tahun ajaran.
 *
 * kuota_hari: kuota yang diberikan (default 12)
 * terpakai: dihitung dari hari pengajuan disetujui dengan mengurangi_kuota_cuti=true
 *
 * Catatan (ADR-0000 konsekuensi): reset otomatis saat rollover tahun ajaran
 * dicatat sebagai backlog, belum dijadwalkan. Untuk Minggu 9, baris baru dibuat manual
 * oleh Operator atau saat Rollover.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kuota_cuti', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sekolah_id');
            $table->foreign('sekolah_id')->references('id')->on('sekolah')->cascadeOnDelete();
            $table->uuid('pegawai_id');
            $table->foreign('pegawai_id')->references('id')->on('pegawai')->cascadeOnDelete();
            $table->uuid('tahun_ajaran_id');
            $table->foreign('tahun_ajaran_id')->references('id')->on('tahun_ajaran')->cascadeOnDelete();

            $table->unsignedSmallInteger('kuota_hari')->default(12);
            $table->unsignedSmallInteger('terpakai')->default(0); // diupdate saat pengajuan disetujui
            $table->timestamps();

            $table->unique(['pegawai_id', 'tahun_ajaran_id']);
            $table->index(['sekolah_id', 'tahun_ajaran_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kuota_cuti');
    }
};
