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
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist;');

        Schema::create('jadwal_pelajaran', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('sekolah')->cascadeOnDelete();
            $table->foreignUuid('semester_id')->constrained('semester')->cascadeOnDelete();
            $table->foreignUuid('rombel_id')->constrained('rombel')->cascadeOnDelete();
            $table->foreignUuid('mata_pelajaran_id')->constrained('mata_pelajaran')->cascadeOnDelete();
            $table->foreignUuid('guru_id')->constrained('pegawai')->cascadeOnDelete();
            $table->foreignUuid('ruang_id')->nullable()->constrained('ruang')->nullOnDelete();
            $table->unsignedSmallInteger('hari'); // 1=Senin..6=Sabtu
            $table->unsignedSmallInteger('jam_mulai_ke');
            $table->unsignedSmallInteger('jam_selesai_ke');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sekolah_id', 'semester_id']);
            $table->index(['sekolah_id', 'rombel_id']);
            $table->index(['sekolah_id', 'guru_id']);
            $table->index(['sekolah_id', 'ruang_id']);
            $table->index(['sekolah_id', 'semester_id', 'hari']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jadwal_pelajaran');
    }
};
