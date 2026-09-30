<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('gen_random_uuid()'));
            $table->foreignUuid('sekolah_id')->constrained('sekolah')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            // Tipe entitas yang diimpor (saat ini hanya siswa, bisa diperluas nanti)
            $table->string('tipe')->default('siswa');
            $table->string('nama_file');
            $table->string('path');
            // Pemetaan kolom: {"nisn": "A", "nama": "B", ...}
            $table->jsonb('pemetaan_kolom')->nullable();
            // Statistik baris
            $table->integer('total_baris')->default(0);
            $table->integer('valid')->default(0);
            $table->integer('peringatan')->default(0);
            $table->integer('gagal')->default(0);
            $table->integer('dibuat')->default(0);
            $table->integer('diperbarui')->default(0);
            $table->integer('dilewati')->default(0);
            // Status pipeline: uploaded|mapping|validating|preview|importing|done|failed|rolled_back
            $table->string('status')->default('uploaded');
            // Rollback aktif 24 jam sejak impor selesai
            $table->timestamp('dapat_dirollback_hingga')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
