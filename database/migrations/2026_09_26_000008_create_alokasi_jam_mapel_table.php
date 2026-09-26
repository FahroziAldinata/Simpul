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
        Schema::create('alokasi_jam_mapel', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('sekolah')->cascadeOnDelete();
            $table->foreignUuid('rombel_id')->constrained('rombel')->cascadeOnDelete();
            $table->foreignUuid('mata_pelajaran_id')->constrained('mata_pelajaran')->cascadeOnDelete();
            $table->foreignUuid('guru_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->unsignedSmallInteger('jam_per_minggu');
            $table->timestamps();

            $table->unique(['rombel_id', 'mata_pelajaran_id']);
            $table->index(['sekolah_id', 'rombel_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alokasi_jam_mapel');
    }
};
