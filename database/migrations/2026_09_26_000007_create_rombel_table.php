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
        Schema::create('rombel', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('sekolah')->cascadeOnDelete();
            $table->foreignUuid('semester_id')->constrained('semester')->cascadeOnDelete();
            $table->foreignUuid('jurusan_id')->nullable()->constrained('jurusan')->nullOnDelete();
            $table->foreignUuid('wali_kelas_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->foreignUuid('ruang_id')->nullable()->constrained('ruang')->nullOnDelete();
            $table->string('nama', 50);
            $table->unsignedSmallInteger('tingkat');
            $table->unsignedSmallInteger('kuota')->default(36);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            // Constraint PRD: Nama rombel unik per semester
            $table->unique(['semester_id', 'nama']);

            // Constraint PRD: 1 wali kelas hanya boleh memegang 1 rombel per semester
            $table->unique(['semester_id', 'wali_kelas_id']);

            $table->index(['sekolah_id', 'semester_id']);
            $table->index(['sekolah_id', 'tingkat']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rombel');
    }
};
