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
        Schema::create('berkas_siswa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('sekolah')->cascadeOnDelete();
            $table->foreignUuid('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->string('jenis', 30); // ijazah | akta | kk | foto
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->integer('file_size_bytes');
            $table->timestamps();

            $table->index(['sekolah_id', 'siswa_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('berkas_siswa');
    }
};
