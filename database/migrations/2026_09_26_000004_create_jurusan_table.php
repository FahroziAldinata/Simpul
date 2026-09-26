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
        Schema::create('jurusan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('sekolah')->cascadeOnDelete();
            $table->string('kode', 20);
            $table->string('nama', 100);
            $table->string('bidang_keahlian', 100)->nullable();
            $table->string('program_keahlian', 100)->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            $table->unique(['sekolah_id', 'kode']);
            $table->index(['sekolah_id', 'is_aktif']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jurusan');
    }
};
