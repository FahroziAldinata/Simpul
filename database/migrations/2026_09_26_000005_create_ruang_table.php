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
        Schema::create('ruang', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('sekolah')->cascadeOnDelete();
            $table->string('kode', 30);
            $table->string('nama', 100);
            $table->string('kategori', 30); // kelas, laboratorium, bengkel, lapangan, perpustakaan, lainnya
            $table->unsignedSmallInteger('kapasitas')->default(36);
            $table->string('lokasi', 100)->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            $table->unique(['sekolah_id', 'kode']);
            $table->index(['sekolah_id', 'kategori']);
            $table->index(['sekolah_id', 'is_aktif']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ruang');
    }
};
