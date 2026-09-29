<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mutasi_siswa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('sekolah')->cascadeOnDelete();
            $table->foreignUuid('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignUuid('semester_id')->constrained('semester')->cascadeOnDelete();

            // 7 jenis mutasi: masuk | keluar | pindah_rombel | naik_kelas | tinggal_kelas | lulus | drop_out
            $table->string('tipe', 30);
            $table->date('tanggal');
            $table->text('alasan')->nullable();
            $table->string('asal_sekolah')->nullable();
            $table->string('sekolah_tujuan')->nullable();

            $table->foreignUuid('dari_rombel_id')->nullable()->constrained('rombel')->nullOnDelete();
            $table->foreignUuid('ke_rombel_id')->nullable()->constrained('rombel')->nullOnDelete();

            // Snapshot sebelum mutasi dieksekusi
            $table->string('status_sebelum', 30)->nullable();
            $table->foreignUuid('rombel_id_sebelum')->nullable()->constrained('rombel')->nullOnDelete();
            $table->boolean('anggota_rombel_dibuat_baru')->default(false);

            // Kolom pembatalan / koreksi mutasi
            $table->boolean('is_batal')->default(false);
            $table->text('alasan_batal')->nullable();
            $table->foreignId('dibatalkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dibatalkan_at')->nullable();

            $table->timestamps();

            $table->index(['sekolah_id', 'siswa_id']);
            $table->index(['siswa_id', 'is_batal']);
            $table->index(['siswa_id', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mutasi_siswa');
    }
};
