<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tahun_ajaran', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('sekolah')->cascadeOnDelete();
            $table->string('nama', 50);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->boolean('is_aktif')->default(false);
            $table->timestamps();

            $table->index(['sekolah_id', 'is_aktif']);
        });

        // Partial unique index: Hanya boleh ada 1 tahun ajaran aktif per sekolah
        DB::statement('CREATE UNIQUE INDEX tahun_ajaran_sekolah_aktif_unique ON tahun_ajaran (sekolah_id) WHERE is_aktif = true;');

        Schema::create('semester', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('sekolah')->cascadeOnDelete();
            $table->foreignUuid('tahun_ajaran_id')->constrained('tahun_ajaran')->cascadeOnDelete();
            $table->string('nama', 20); // 'Ganjil' atau 'Genap'
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->boolean('is_aktif')->default(false);
            $table->timestamps();

            $table->index(['sekolah_id', 'is_aktif']);
            $table->index(['tahun_ajaran_id', 'is_aktif']);
        });

        // Partial unique index: Hanya boleh ada 1 semester aktif per sekolah
        DB::statement('CREATE UNIQUE INDEX semester_sekolah_aktif_unique ON semester (sekolah_id) WHERE is_aktif = true;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('semester');
        Schema::dropIfExists('tahun_ajaran');
    }
};
