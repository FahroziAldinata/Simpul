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
        Schema::create('mata_pelajaran', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('sekolah')->cascadeOnDelete();
            $table->string('kode', 30);
            $table->string('nama', 100);
            $table->string('kelompok', 30); // umum_a, umum_b, peminatan, kejuruan, muatan_lokal, lainnya
            $table->unsignedSmallInteger('tingkat')->nullable(); // misal 7, 8, 9, 10, 11, 12 atau null
            $table->foreignUuid('jurusan_id')->nullable()->constrained('jurusan')->nullOnDelete();
            $table->string('bobot_beban_kognitif', 20)->default('sedang'); // ringan, sedang, berat
            $table->string('butuh_ruang_kategori', 30)->nullable(); // kelas, laboratorium, bengkel, lapangan, perpustakaan, lainnya
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            $table->unique(['sekolah_id', 'kode']);
            $table->index(['sekolah_id', 'kelompok']);
            $table->index(['sekolah_id', 'is_aktif']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mata_pelajaran');
    }
};
