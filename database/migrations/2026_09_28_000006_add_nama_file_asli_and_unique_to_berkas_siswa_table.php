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
        Schema::table('berkas_siswa', function (Blueprint $table) {
            $table->string('nama_file_asli')->nullable()->after('file_path');
            $table->unique(['siswa_id', 'jenis'], 'berkas_siswa_siswa_id_jenis_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('berkas_siswa', function (Blueprint $table) {
            $table->dropUnique('berkas_siswa_siswa_id_jenis_unique');
            $table->dropColumn('nama_file_asli');
        });
    }
};
