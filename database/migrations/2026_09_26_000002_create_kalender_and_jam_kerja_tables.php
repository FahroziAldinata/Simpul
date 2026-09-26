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
        Schema::create('hari_libur', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('sekolah')->cascadeOnDelete();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('keterangan');
            $table->string('jenis', 20)->default('nasional'); // nasional, sekolah, ujian, kegiatan
            $table->timestamps();

            $table->index(['sekolah_id', 'tanggal_mulai']);
        });

        Schema::create('jam_kerja', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('sekolah')->cascadeOnDelete();
            $table->string('kelompok', 20)->default('umum'); // umum (operasional sekolah per hari)
            $table->unsignedTinyInteger('hari'); // 1=Senin, 2=Selasa, ..., 7=Minggu
            $table->time('jam_masuk')->nullable();
            $table->time('jam_pulang')->nullable();
            $table->boolean('is_libur')->default(false);
            $table->unsignedTinyInteger('jumlah_jam_pelajaran')->default(8); // Alokasi slot JP operasional per hari
            $table->timestamps();

            $table->unique(['sekolah_id', 'kelompok', 'hari']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jam_kerja');
        Schema::dropIfExists('hari_libur');
    }
};
