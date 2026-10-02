<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // H1: bentrok guru (guru mengajar di dua tempat pada slot waktu sama di semester & sekolah yang sama)
        DB::statement("
            ALTER TABLE jadwal_pelajaran ADD CONSTRAINT excl_guru_bentrok
            EXCLUDE USING gist (
                sekolah_id     WITH =,
                guru_id        WITH =,
                semester_id    WITH =,
                hari           WITH =,
                int4range(jam_mulai_ke, jam_selesai_ke, '[)') WITH &&
            ) WHERE (deleted_at IS NULL);
        ");

        // H2: bentrok ruang (ruang dipakai dua rombel pada slot waktu sama di semester & sekolah yang sama)
        DB::statement("
            ALTER TABLE jadwal_pelajaran ADD CONSTRAINT excl_ruang_bentrok
            EXCLUDE USING gist (
                sekolah_id     WITH =,
                ruang_id       WITH =,
                semester_id    WITH =,
                hari           WITH =,
                int4range(jam_mulai_ke, jam_selesai_ke, '[)') WITH &&
            ) WHERE (deleted_at IS NULL AND ruang_id IS NOT NULL);
        ");

        // H3: bentrok rombel (rombel punya dua pelajaran pada slot waktu sama di semester & sekolah yang sama)
        DB::statement("
            ALTER TABLE jadwal_pelajaran ADD CONSTRAINT excl_rombel_bentrok
            EXCLUDE USING gist (
                sekolah_id     WITH =,
                rombel_id      WITH =,
                semester_id    WITH =,
                hari           WITH =,
                int4range(jam_mulai_ke, jam_selesai_ke, '[)') WITH &&
            ) WHERE (deleted_at IS NULL);
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE jadwal_pelajaran DROP CONSTRAINT IF EXISTS excl_guru_bentrok;');
        DB::statement('ALTER TABLE jadwal_pelajaran DROP CONSTRAINT IF EXISTS excl_ruang_bentrok;');
        DB::statement('ALTER TABLE jadwal_pelajaran DROP CONSTRAINT IF EXISTS excl_rombel_bentrok;');
    }
};
