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
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();

            // Drop standard unique indexes to replace with partial unique indexes (WHERE deleted_at IS NULL)
            $table->dropUnique('users_email_unique');
            $table->dropUnique('users_sekolah_id_nip_unique');
        });

        // Create partial unique indexes allowing multiple soft-deleted rows with same email/nip
        DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX users_sekolah_id_nip_unique ON users (sekolah_id, nip) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_email_unique');
        DB::statement('DROP INDEX IF EXISTS users_sekolah_id_nip_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();

            $table->unique('email', 'users_email_unique');
            $table->unique(['sekolah_id', 'nip'], 'users_sekolah_id_nip_unique');
        });
    }
};
