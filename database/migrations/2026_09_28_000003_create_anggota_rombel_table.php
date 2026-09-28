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
        Schema::create('anggota_rombel', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('sekolah')->cascadeOnDelete();
            $table->foreignUuid('rombel_id')->constrained('rombel')->cascadeOnDelete();
            $table->foreignUuid('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignUuid('semester_id')->constrained('semester')->cascadeOnDelete();
            $table->integer('nomor_absen')->nullable();
            $table->timestamps();

            // Constraint: satu siswa hanya terdaftar di satu rombel per semester
            $table->unique(['semester_id', 'siswa_id']);
            $table->index(['rombel_id', 'nomor_absen']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anggota_rombel');
    }
};
