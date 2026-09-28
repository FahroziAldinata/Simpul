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
        Schema::create('siswa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('sekolah')->cascadeOnDelete();
            $table->string('nisn', 10);
            $table->string('nik', 16);
            $table->string('nama');
            $table->string('jenis_kelamin', 1); // L | P
            $table->string('tempat_lahir');
            $table->date('tanggal_lahir');
            $table->string('agama', 30);
            $table->text('alamat')->nullable();
            $table->string('no_hp', 20)->nullable();
            $table->string('status', 20)->default('aktif'); // aktif | lulus | pindah | do
            $table->uuid('import_batch_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sekolah_id', 'nama']);
            $table->index(['sekolah_id', 'nik']);
            $table->index(['sekolah_id', 'status']);
        });

        // Partial unique index di PostgreSQL: NISN unik global lintas sekolah untuk siswa yang tidak di-soft-delete
        DB::statement('CREATE UNIQUE INDEX siswa_nisn_unique ON siswa (nisn) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS siswa_nisn_unique');
        Schema::dropIfExists('siswa');
    }
};
