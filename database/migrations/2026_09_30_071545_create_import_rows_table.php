<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_rows', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('gen_random_uuid()'));
            $table->foreignUuid('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->integer('nomor_baris');
            // Data mentah dari file Excel (key = header kolom Excel)
            $table->jsonb('data_mentah');
            // Data setelah normalisasi
            $table->jsonb('data_bersih')->nullable();
            // status: valid|peringatan|gagal|diimpor|dilewati
            $table->string('status')->default('gagal');
            // Error per kolom: {"nisn": "NISN harus 10 digit", "tanggal_lahir": "Format tidak dikenal"}
            $table->jsonb('errors')->nullable();
            // Aksi resolusi duplikat: lewati|perbarui|buat_baru
            $table->string('aksi_duplikat')->nullable();
            // UUID record siswa yang terdampak (untuk update atau referensi rollback)
            $table->uuid('model_id')->nullable();
            $table->timestamps();

            // Index untuk query per status dalam satu batch
            $table->index(['import_batch_id', 'status']);
            $table->index(['import_batch_id', 'nomor_baris']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};
