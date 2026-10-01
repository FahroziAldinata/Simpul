<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-09.01 — Tabel konfigurasi jenis izin per sekolah.
 *
 * Kolom penting:
 *  - butuh_lampiran: wajib upload surat/dokumen
 *  - butuh_persetujuan: jika false → langsung disetujui saat dibuat
 *  - mengurangi_kuota_cuti: true untuk Cuti Tahunan, false untuk Sakit/Izin
 *  - urutan_approval: jsonb array of role slugs, contoh ["waka_kurikulum","kepsek"]
 *    Kosong / null hanya valid kalau butuh_persetujuan = false.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_izin', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sekolah_id')->nullable(); // null = default global (seed), not null = custom per sekolah
            $table->foreign('sekolah_id')->references('id')->on('sekolah')->cascadeOnDelete();

            $table->string('nama');                                // "Cuti Tahunan", "Sakit", "Dinas Luar"
            $table->string('kode')->nullable();                    // "cuti", "sakit", "dinas", "izin"
            $table->boolean('butuh_lampiran')->default(false);
            $table->boolean('butuh_persetujuan')->default(true);
            $table->boolean('mengurangi_kuota_cuti')->default(false);
            $table->jsonb('urutan_approval')->default('[]');       // ["waka_kurikulum","kepsek"]
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            // Nama harus unik per sekolah (atau per null=global)
            $table->unique(['sekolah_id', 'nama']);
            $table->unique(['sekolah_id', 'kode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jenis_izin');
    }
};
