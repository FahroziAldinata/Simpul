<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-09.03 — Tabel langkah persetujuan berjenjang.
 *
 * Satu baris per langkah persetujuan per pengajuan.
 * Urutan menentukan siapa yang harus menyetujui dahulu.
 *
 * approver_role: role yang berhak di langkah ini (bukan user spesifik)
 * approver_id: user yang SUDAH memutuskan (null = belum)
 *
 * Catatan: approver_id nullable karena baris dibuat saat pengajuan dibuat,
 * sebelum ada yang memutuskan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persetujuan_izin', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pengajuan_izin_id');
            $table->foreign('pengajuan_izin_id')->references('id')->on('pengajuan_izin')->cascadeOnDelete();

            $table->unsignedSmallInteger('urutan');         // 1, 2, 3 ...
            $table->string('approver_role');                // role slug: 'kepsek', 'super_admin', dst
            $table->unsignedBigInteger('approver_id')->nullable(); // user_id yang memutuskan (null = belum)
            $table->foreign('approver_id')->references('id')->on('users')->nullOnDelete();

            $table->string('status')->default('menunggu'); // menunggu|disetujui|ditolak
            $table->text('catatan')->nullable();
            $table->timestamp('diputuskan_pada')->nullable();
            $table->timestamps();

            // Satu langkah per pengajuan
            $table->unique(['pengajuan_izin_id', 'urutan']);
            $table->index(['pengajuan_izin_id', 'urutan', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persetujuan_izin');
    }
};
