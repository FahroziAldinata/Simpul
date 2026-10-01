<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Minggu 8 (T-08.01):
     *  1. Extend `jam_kerja` — tambah kolom `toleransi_menit` (catatan arsitektur Minggu 4 sudah mendokumentasikan ini).
     *  2. Buat `titik_absen` — titik check-in QR, secret terenkripsi Laravel per titik.
     *  3. Buat `absensi` — catatan kehadiran pegawai, dengan partial unique index dan client_uuid.
     *  4. Buat `idempotency_keys` — tabel idempoten untuk sinkronisasi offline (dipakai aktif Minggu 10).
     */
    public function up(): void
    {
        // 1. Extend jam_kerja: tambah toleransi_menit
        //    Kolom ini sengaja disiapkan di Minggu 8 sesuai catatan arsitektur pada migrasi Minggu 4.
        Schema::table('jam_kerja', function (Blueprint $table) {
            $table->unsignedSmallInteger('toleransi_menit')->default(0)->after('is_libur');
        });

        // 2. titik_absen — QR check-in point, secret encrypted per titik (bukan shared secret)
        Schema::create('titik_absen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('sekolah')->cascadeOnDelete();
            $table->string('nama', 100);
            // 'secret' disimpan terenkripsi via Laravel encrypted cast di model.
            // Kolom sengaja text agar menampung ciphertext yang lebih panjang dari plaintext.
            $table->text('secret');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            $table->index(['sekolah_id', 'is_aktif']);
        });

        // 3. absensi — catatan kehadiran pegawai
        Schema::create('absensi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('sekolah')->cascadeOnDelete();
            $table->foreignUuid('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('jenis', 10);          // masuk | pulang
            $table->timestamp('waktu_server')->nullable();
            $table->timestamp('waktu_perangkat')->nullable();
            $table->string('status', 20)->nullable(); // hadir | terlambat | pulang_cepat | alfa
            $table->unsignedSmallInteger('menit_terlambat')->default(0);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            // lokasi_mencurigakan: true jika di luar radius, tapi absensi tetap dicatat (tidak ditolak)
            $table->boolean('lokasi_mencurigakan')->default(false);
            $table->boolean('perlu_ditinjau')->default(false);
            $table->string('sumber', 10)->default('qr'); // qr | manual | import
            // client_uuid: disiapkan Minggu 8, dipakai aktif untuk idempoten Minggu 10
            $table->uuid('client_uuid')->nullable()->unique();
            // dicatat_oleh: nullable, diisi untuk absen manual oleh operator
            // users.id adalah bigint (bukan UUID), jadi pakai unsignedBigInteger
            $table->unsignedBigInteger('dicatat_oleh')->nullable();
            $table->foreign('dicatat_oleh')->references('id')->on('users')->nullOnDelete();
            $table->text('alasan_manual')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sekolah_id', 'pegawai_id', 'tanggal']);
        });

        // Partial unique index: satu pegawai hanya bisa punya satu catatan per jenis per hari
        // (deleted_at IS NULL — baris yang di-soft-delete tidak ikut constraint)
        DB::statement(
            'CREATE UNIQUE INDEX absensi_pegawai_tanggal_jenis_unique
             ON absensi (pegawai_id, tanggal, jenis)
             WHERE deleted_at IS NULL'
        );

        // 4. idempotency_keys — migrasi saja, dipakai aktif Minggu 10 (sinkronisasi offline)
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->string('key')->primary();       // client_uuid dari perangkat
            $table->foreignUuid('sekolah_id')->constrained('sekolah')->cascadeOnDelete();
            $table->string('endpoint', 200);
            $table->jsonb('response_body')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->timestamp('expires_at');        // TTL 7 hari

            $table->index(['sekolah_id', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');

        DB::statement('DROP INDEX IF EXISTS absensi_pegawai_tanggal_jenis_unique');
        Schema::dropIfExists('absensi');

        Schema::dropIfExists('titik_absen');

        Schema::table('jam_kerja', function (Blueprint $table) {
            $table->dropColumn('toleransi_menit');
        });
    }
};
