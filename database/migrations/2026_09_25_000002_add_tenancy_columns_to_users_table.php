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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('sekolah_id')
                ->nullable()
                ->after('id')
                ->constrained('sekolah')
                ->nullOnDelete();
            $table->string('nip')->nullable()->after('name');
            $table->string('nama_lengkap')->nullable()->after('name');

            $table->unique(['sekolah_id', 'nip']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['sekolah_id', 'nip']);
            $table->dropForeign(['sekolah_id']);
            $table->dropColumn(['sekolah_id', 'nip', 'nama_lengkap']);
        });
    }
};
